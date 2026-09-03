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
use App\Models\Meeting;
use App\Models\Reminder;
use App\Models\Task;
use App\Services\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function __construct(private readonly ActivityService $activities) {}

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
