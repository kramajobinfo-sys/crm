<?php
namespace App\Services;

use App\Models\Call;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\Quotation;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\TimelineActivity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ActivityService
{
    /** Short aliases the API accepts for polymorphic links, mapped to model classes. */
    public const RELATED_MAP = [
        'deal' => Deal::class,
        'lead' => Lead::class,
        'customer' => Customer::class,
        'contact' => Contact::class,
        'quotation' => Quotation::class,
    ];

    // ---- Tasks -----------------------------------------------------------

    public function paginateTasks(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Task::query()
            ->with(['assignee:id,name', 'related'])
            ->when(!empty($filters['q']), fn ($q) => $q->where('title', 'like', '%'.$filters['q'].'%'))
            // 'open' means Task::scopeOpen (open + in_progress), matching stats() and the
            // unified feed. Previously both clauses fired for status=open: the first pinned
            // status='open' and the second could only narrow, so the scope was dead code and
            // the list silently disagreed with the dashboard tile by every in_progress task.
            ->when(!empty($filters['status']) && !in_array($filters['status'], ['all', 'open'], true),
                fn ($q) => $q->where('status', $filters['status']))
            ->when(($filters['status'] ?? null) === 'open', fn ($q) => $q->open())
            ->when(!empty($filters['priority']), fn ($q) => $q->where('priority', $filters['priority']))
            ->when(!empty($filters['assigned_to']), function ($q) use ($filters) {
                return $filters['assigned_to'] === 'me'
                    ? $q->where('assigned_to', auth()->id())
                    : $q->where('assigned_to', $filters['assigned_to']);
            })
            ->when(($filters['due'] ?? null) === 'overdue', fn ($q) => $q->open()->where('due_at', '<', now()))
            ->when(($filters['due'] ?? null) === 'today', fn ($q) => $q->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()]))
            ->orderByRaw('due_at is null, due_at asc')->orderByDesc('id')
            ->paginate($perPage);
    }

    public function createTask(array $data): Task
    {
        return DB::transaction(function () use ($data) {
            $this->normaliseRelated($data);
            $data['created_by'] ??= auth()->id();
            $data['assigned_to'] ??= auth()->id();
            $task = Task::create($data);
            $this->touchTimeline($task, 'note', 'Task: '.$task->title);
            return $task->load(['assignee:id,name', 'related']);
        });
    }

    public function updateTask(Task $task, array $data): Task
    {
        $this->normaliseRelated($data);
        $wasDone = $task->status === 'done';
        // Completing/reopening keeps completed_at honest regardless of who toggles it.
        if (array_key_exists('status', $data)) {
            $data['completed_at'] = in_array($data['status'], ['done', 'cancelled'], true) ? ($task->completed_at ?? now()) : null;
        }
        $task->update($data);
        if (!$wasDone && $task->status === 'done') $this->spawnNextOccurrence($task);
        return $task->load(['assignee:id,name', 'related']);
    }

    public function completeTask(Task $task): Task
    {
        $wasDone = $task->status === 'done';
        $task->forceFill(['status' => 'done', 'completed_at' => now()])->save();
        $this->touchTimeline($task, 'note', 'Task completed: '.$task->title);
        if (!$wasDone) $this->spawnNextOccurrence($task);
        return $task->load(['assignee:id,name', 'related']);
    }

    /** When a recurring task is completed, create the next occurrence (bounded by recurrence_until). */
    private function spawnNextOccurrence(Task $task): void
    {
        if (!in_array($task->recurrence, Task::RECURRENCES, true)) return;
        $base = $task->due_at ?? now();
        $next = match ($task->recurrence) {
            'daily' => $base->copy()->addDay(),
            'weekly' => $base->copy()->addWeek(),
            'monthly' => $base->copy()->addMonthNoOverflow(),
            default => null,
        };
        if (!$next) return;
        if ($task->recurrence_until && $next->copy()->startOfDay()->gt($task->recurrence_until->copy()->endOfDay())) return;

        Task::create([
            'company_id' => $task->company_id, 'title' => $task->title, 'description' => $task->description,
            'priority' => $task->priority, 'assigned_to' => $task->assigned_to, 'created_by' => $task->created_by,
            'related_type' => $task->related_type, 'related_id' => $task->related_id,
            'due_at' => $next, 'status' => 'open',
            'recurrence' => $task->recurrence, 'recurrence_until' => $task->recurrence_until,
            'recurrence_parent_id' => $task->recurrence_parent_id ?? $task->id,
        ]);
    }

    // ---- Meetings --------------------------------------------------------

    public function paginateMeetings(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Meeting::query()
            ->with(['organizer:id,name', 'participants.user:id,name', 'related'])
            ->when(!empty($filters['q']), fn ($q) => $q->where('title', 'like', '%'.$filters['q'].'%'))
            ->when(!empty($filters['status']) && $filters['status'] !== 'all', fn ($q) => $q->where('status', $filters['status']))
            ->when(($filters['when'] ?? null) === 'upcoming', fn ($q) => $q->where('start_at', '>=', now()))
            ->when(($filters['when'] ?? null) === 'past', fn ($q) => $q->where('start_at', '<', now()))
            ->when(!empty($filters['organizer_id']) && $filters['organizer_id'] === 'me', fn ($q) => $q->where('organizer_id', auth()->id()))
            ->orderByDesc('start_at')
            ->paginate($perPage);
    }

    public function createMeeting(array $data): Meeting
    {
        return DB::transaction(function () use ($data) {
            $participants = $data['participants'] ?? null;
            unset($data['participants']);
            $this->normaliseRelated($data);
            $data['organizer_id'] ??= auth()->id();
            $meeting = Meeting::create($data);
            $this->syncParticipants($meeting, is_array($participants) ? $participants : []);
            $this->touchTimeline($meeting, 'meeting', 'Meeting: '.$meeting->title);
            return $meeting->load(['organizer:id,name', 'participants.user:id,name', 'related']);
        });
    }

    public function updateMeeting(Meeting $meeting, array $data): Meeting
    {
        return DB::transaction(function () use ($meeting, $data) {
            $participants = $data['participants'] ?? null;
            unset($data['participants']);
            $this->normaliseRelated($data);
            $meeting->update($data);
            if (is_array($participants)) $this->syncParticipants($meeting, $participants);
            return $meeting->load(['organizer:id,name', 'participants.user:id,name', 'related']);
        });
    }

    private function syncParticipants(Meeting $meeting, array $participants): void
    {
        $meeting->participants()->delete();
        foreach ($participants as $p) {
            if (empty($p['user_id']) && empty($p['name']) && empty($p['email'])) continue;
            $meeting->participants()->create([
                'company_id' => $meeting->company_id,
                'user_id' => $p['user_id'] ?? null,
                'name' => $p['name'] ?? null,
                'email' => $p['email'] ?? null,
                'response' => $p['response'] ?? 'invited',
            ]);
        }
    }

    // ---- Calls -----------------------------------------------------------

    public function paginateCalls(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Call::query()
            ->with(['user:id,name', 'related'])
            ->when(!empty($filters['q']), fn ($q) => $q->where('subject', 'like', '%'.$filters['q'].'%'))
            ->when(!empty($filters['direction']), fn ($q) => $q->where('direction', $filters['direction']))
            ->when(!empty($filters['status']) && $filters['status'] !== 'all', fn ($q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['user_id']) && $filters['user_id'] === 'me', fn ($q) => $q->where('user_id', auth()->id()))
            ->orderByRaw('coalesce(occurred_at, scheduled_at, created_at) desc')
            ->paginate($perPage);
    }

    public function createCall(array $data): Call
    {
        return DB::transaction(function () use ($data) {
            $this->normaliseRelated($data);
            $data['user_id'] ??= auth()->id();
            // A completed call with no explicit time happened now.
            if (($data['status'] ?? 'completed') === 'completed' && empty($data['occurred_at'])) {
                $data['occurred_at'] = now();
            }
            $call = Call::create($data);
            $this->touchTimeline($call, 'call', 'Call: '.$call->subject, $call->notes);
            return $call->load(['user:id,name', 'related']);
        });
    }

    public function updateCall(Call $call, array $data): Call
    {
        $this->normaliseRelated($data);
        $call->update($data);
        return $call->load(['user:id,name', 'related']);
    }

    // ---- Reminders -------------------------------------------------------

    public function listReminders(bool $mineOnly = true): Collection
    {
        return Reminder::query()
            // `related` matches paginateTasks/Meetings/Calls — ReminderResource resolves it
            // through ResolvesRelated, whose comment already assumes the service eager-loads
            // it. This was the one list path that didn't, costing one query per reminder.
            ->with(['related'])
            ->when($mineOnly, fn ($q) => $q->where('user_id', auth()->id()))
            ->where('is_sent', false)
            ->orderBy('remind_at')
            ->limit(100)->get();
    }

    public function createReminder(array $data): Reminder
    {
        $this->normaliseRelated($data);
        $data['user_id'] ??= auth()->id();
        return Reminder::create($data);
    }

    public function markReminderSent(Reminder $reminder): Reminder
    {
        $reminder->forceFill(['is_sent' => true, 'sent_at' => now()])->save();
        return $reminder;
    }

    // ---- Cross-type feed + stats ----------------------------------------

    /** Unified upcoming feed: open tasks, scheduled meetings, scheduled calls. */
    public function feed(bool $mineOnly = true, int $limit = 50): array
    {
        $uid = auth()->id();

        $tasks = Task::open()
            ->when($mineOnly, fn ($q) => $q->where('assigned_to', $uid))
            ->with('assignee:id,name')->whereNotNull('due_at')
            ->orderBy('due_at')->limit($limit)->get()
            ->map(fn (Task $t) => [
                'kind' => 'task', 'id' => $t->id, 'title' => $t->title,
                'at' => $t->due_at?->toIso8601String(), 'when_human' => $t->due_at?->diffForHumans(),
                'status' => $t->status, 'priority' => $t->priority, 'overdue' => $t->isOverdue(),
                'who' => $t->assignee?->name,
            ]);

        $meetings = Meeting::where('status', 'scheduled')
            ->when($mineOnly, fn ($q) => $q->where('organizer_id', $uid))
            ->where('start_at', '>=', now()->subDay())
            ->orderBy('start_at')->limit($limit)->get()
            ->map(fn (Meeting $m) => [
                'kind' => 'meeting', 'id' => $m->id, 'title' => $m->title,
                'at' => $m->start_at?->toIso8601String(), 'when_human' => $m->start_at?->diffForHumans(),
                'status' => $m->status, 'location' => $m->location, 'who' => null,
            ]);

        $calls = Call::where('status', 'scheduled')
            ->when($mineOnly, fn ($q) => $q->where('user_id', $uid))
            ->whereNotNull('scheduled_at')
            ->orderBy('scheduled_at')->limit($limit)->get()
            ->map(fn (Call $c) => [
                'kind' => 'call', 'id' => $c->id, 'title' => $c->subject,
                'at' => $c->scheduled_at?->toIso8601String(), 'when_human' => $c->scheduled_at?->diffForHumans(),
                'status' => $c->status, 'direction' => $c->direction, 'who' => null,
            ]);

        return $tasks->concat($meetings)->concat($calls)
            ->sortBy('at')->values()->take($limit)->all();
    }

    public function stats(): array
    {
        $uid = auth()->id();
        $myTasks = Task::where('assigned_to', $uid);
        return [
            'my_open_tasks'    => (clone $myTasks)->open()->count(),
            'overdue'          => (clone $myTasks)->open()->where('due_at', '<', now())->count(),
            'due_today'        => (clone $myTasks)->open()->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])->count(),
            'completed_this_week' => (clone $myTasks)->where('status', 'done')->where('completed_at', '>=', now()->startOfWeek())->count(),
            'upcoming_meetings' => Meeting::where('status', 'scheduled')->where('start_at', '>=', now())->count(),
            'calls_logged'     => Call::where('user_id', $uid)->where('status', 'completed')->whereMonth('occurred_at', now()->month)->count(),
        ];
    }

    // ---- helpers ---------------------------------------------------------

    /** Translate a `related` short alias ('deal') into a stored morph class, or clear it. */
    private function normaliseRelated(array &$data): void
    {
        if (!array_key_exists('related_type', $data)) return;
        $alias = $data['related_type'];
        if ($alias === null || $alias === '') {
            $data['related_type'] = null;
            $data['related_id'] = null;
            return;
        }
        $data['related_type'] = self::RELATED_MAP[$alias] ?? $alias;
    }

    private function touchTimeline($activity, string $type, string $title, ?string $body = null): void
    {
        if (!$activity->related_type || !$activity->related_id) return;
        $related = $activity->related()->first();
        if ($related) TimelineActivity::record($related, $type, $title, $body);
    }
}
