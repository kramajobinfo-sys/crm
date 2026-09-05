<?php
namespace App\Http\Controllers\Api\V1\Activities;

use App\Http\Controllers\Controller;
use App\Http\Requests\Activities\StoreCallRequest;
use App\Http\Requests\Activities\StoreMeetingRequest;
use App\Http\Requests\Activities\StoreReminderRequest;
use App\Http\Requests\Activities\StoreTaskRequest;
use App\Http\Requests\Activities\UpdateCallRequest;
use App\Http\Requests\Activities\UpdateMeetingRequest;
use App\Http\Requests\Activities\UpdateTaskRequest;
use App\Http\Resources\CallResource;
use App\Http\Resources\MeetingResource;
use App\Http\Resources\ReminderResource;
use App\Http\Resources\TaskResource;
use App\Models\Call;
use App\Services\TelephonyService;
use Illuminate\Support\Facades\Crypt;
use App\Models\Meeting;
use App\Models\Reminder;
use App\Models\Task;
use App\Services\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function __construct(private readonly ActivityService $activities) {}

    /** Click-to-call: ring the agent's phone, then bridge to the customer. */
    public function clickToCall(Request $request, TelephonyService $tel): JsonResponse
    {
        $data = $request->validate([
            'to' => 'required|string|max:32',
            'subject' => 'nullable|string|max:191',
            'related_type' => 'nullable|string|max:191',
            'related_id' => 'nullable|integer',
        ]);
        $companyId = auth()->user()->company_id;
        $agent = trim((string) (auth()->user()->phone ?? ''));
        if ($agent === '') {
            return response()->json(['success' => false, 'message' => 'Your profile has no phone number to call from.'], 422);
        }
        if (!$tel->isConfigured($companyId)) {
            return response()->json(['success' => false, 'message' => 'Telephony is not configured — add a Twilio provider with a voice-capable from number in Settings.'], 422);
        }
        $provider = $tel->provider($companyId);

        $call = Call::create([
            'company_id' => $companyId, 'subject' => $data['subject'] ?? 'Outbound call',
            'direction' => 'outbound', 'status' => 'scheduled', 'phone' => $data['to'],
            'duration_seconds' => 0, 'user_id' => auth()->id(), 'occurred_at' => now(),
            'related_type' => $data['related_type'] ?? null, 'related_id' => $data['related_id'] ?? null,
        ]);
        $token = $this->voiceToken($companyId, $data['to'], $tel->voiceFrom($provider), $call->id);
        $twimlUrl = route('calls.twiml', ['token' => $token]);
        try {
            $sid = $tel->dial($provider, $agent, $twimlUrl);
            $call->forceFill(['notes' => trim(($call->notes ?? '') . "\nprovider_sid: {$sid}")])->save();
            return $this->success(['call_id' => $call->id, 'agent_phone' => $agent],
                'Calling your phone now — you will be connected to ' . $data['to'] . ' when you answer.');
        } catch (\Throwable $e) {
            $call->forceFill(['status' => 'cancelled'])->save();
            return response()->json(['success' => false, 'message' => substr($e->getMessage(), 0, 200)], 502);
        }
    }

    /** Public TwiML Twilio fetches when the agent answers — dials the customer. */
    public function twiml(string $token)
    {
        $d = $this->voiceDecode($token);
        if (!$d) {
            return response('<Response><Say>Invalid call token.</Say></Response>', 400)->header('Content-Type', 'text/xml');
        }
        $to = htmlspecialchars((string) $d['to'], ENT_XML1);
        $from = htmlspecialchars((string) ($d['from'] ?? ''), ENT_XML1);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Response><Say>Connecting your call now.</Say>'
            . '<Dial callerId="' . $from . '">' . $to . '</Dial></Response>';
        return response($xml, 200)->header('Content-Type', 'text/xml; charset=UTF-8');
    }

    private function voiceToken(int $companyId, string $to, ?string $from, int $callId): string
    {
        $json = json_encode(['c' => $companyId, 'to' => $to, 'from' => $from, 'k' => $callId]);
        return rtrim(strtr(base64_encode(Crypt::encryptString($json)), '+/', '-_'), '=');
    }

    private function voiceDecode(string $token): ?array
    {
        try {
            $b64 = strtr($token, '-_', '+/');
            $b64 .= str_repeat('=', (4 - strlen($b64) % 4) % 4);
            $d = json_decode(Crypt::decryptString(base64_decode($b64)), true);
            return (is_array($d) && isset($d['to'])) ? $d : null;
        } catch (\Throwable) { return null; }
    }

    // ---- overview --------------------------------------------------------

    public function feed(Request $request): JsonResponse
    {
        $data = $request->validate(['scope' => 'nullable|in:me,all']);
        return $this->success($this->activities->feed(($data['scope'] ?? 'me') === 'me'));
    }

    public function stats(): JsonResponse
    {
        return $this->success($this->activities->stats());
    }

    public function meta(): JsonResponse
    {
        return $this->success([
            'task_statuses'   => Task::STATUSES,
            'task_priorities' => Task::PRIORITIES,
            'meeting_statuses' => Meeting::STATUSES,
            'call_directions' => Call::DIRECTIONS,
            'call_statuses'   => Call::STATUSES,
            'related_types'   => array_keys(ActivityService::RELATED_MAP),
        ]);
    }

    // ---- tasks -----------------------------------------------------------

    public function tasks(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:191',
            'status' => 'nullable|string|in:all,open,in_progress,done,cancelled',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|string',
            'due' => 'nullable|string|in:overdue,today',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated($this->activities->paginateTasks($filters, (int) ($filters['per_page'] ?? 25)), TaskResource::class);
    }

    public function storeTask(StoreTaskRequest $request): JsonResponse
    {
        return $this->success(new TaskResource($this->activities->createTask($request->validated())), 'Task created', 201);
    }

    public function updateTask(UpdateTaskRequest $request, int $id): JsonResponse
    {
        $task = Task::findOrFail($id);
        return $this->success(new TaskResource($this->activities->updateTask($task, $request->validated())), 'Task updated');
    }

    public function completeTask(int $id): JsonResponse
    {
        $task = Task::findOrFail($id);
        return $this->success(new TaskResource($this->activities->completeTask($task)), 'Task completed');
    }

    public function destroyTask(int $id): JsonResponse
    {
        Task::findOrFail($id)->delete();
        return $this->success(null, 'Task deleted');
    }

    // ---- meetings --------------------------------------------------------

    public function meetings(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:191',
            'status' => 'nullable|string|in:all,scheduled,completed,cancelled',
            'when' => 'nullable|string|in:upcoming,past',
            'organizer_id' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated($this->activities->paginateMeetings($filters, (int) ($filters['per_page'] ?? 25)), MeetingResource::class);
    }

    public function storeMeeting(StoreMeetingRequest $request): JsonResponse
    {
        return $this->success(new MeetingResource($this->activities->createMeeting($request->validated())), 'Meeting scheduled', 201);
    }

    public function updateMeeting(UpdateMeetingRequest $request, int $id): JsonResponse
    {
        $meeting = Meeting::findOrFail($id);
        return $this->success(new MeetingResource($this->activities->updateMeeting($meeting, $request->validated())), 'Meeting updated');
    }

    public function destroyMeeting(int $id): JsonResponse
    {
        Meeting::findOrFail($id)->delete();
        return $this->success(null, 'Meeting deleted');
    }

    // ---- calls -----------------------------------------------------------

    public function calls(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:191',
            'direction' => 'nullable|string|in:inbound,outbound',
            'status' => 'nullable|string|in:all,scheduled,completed,missed,cancelled',
            'user_id' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated($this->activities->paginateCalls($filters, (int) ($filters['per_page'] ?? 25)), CallResource::class);
    }

    public function storeCall(StoreCallRequest $request): JsonResponse
    {
        return $this->success(new CallResource($this->activities->createCall($request->validated())), 'Call logged', 201);
    }

    public function updateCall(UpdateCallRequest $request, int $id): JsonResponse
    {
        $call = Call::findOrFail($id);
        return $this->success(new CallResource($this->activities->updateCall($call, $request->validated())), 'Call updated');
    }

    public function destroyCall(int $id): JsonResponse
    {
        Call::findOrFail($id)->delete();
        return $this->success(null, 'Call deleted');
    }

    // ---- reminders -------------------------------------------------------

    public function reminders(): JsonResponse
    {
        return $this->success(ReminderResource::collection($this->activities->listReminders()));
    }

    public function storeReminder(StoreReminderRequest $request): JsonResponse
    {
        return $this->success(new ReminderResource($this->activities->createReminder($request->validated())), 'Reminder set', 201);
    }

    public function completeReminder(int $id): JsonResponse
    {
        $reminder = Reminder::findOrFail($id);
        return $this->success(new ReminderResource($this->activities->markReminderSent($reminder)), 'Reminder dismissed');
    }

    public function destroyReminder(int $id): JsonResponse
    {
        Reminder::findOrFail($id)->delete();
        return $this->success(null, 'Reminder deleted');
    }
}
