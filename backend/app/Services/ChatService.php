<?php
namespace App\Services;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ChatService
{
    /** Inbox listing. Filters are all optional; company scoping comes from BelongsToCompany. */
    public function conversations(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return ChatConversation::query()
            ->with(['channel:id,type,name', 'contact:id,display_name,avatar_url', 'assignee:id,name'])
            ->status($filters['status'] ?? null)
            ->when(!empty($filters['channel_id']), fn ($q) => $q->where('channel_id', $filters['channel_id']))
            ->when(!empty($filters['channel_type']), fn ($q) => $q->whereHas('channel', fn ($c) => $c->where('type', $filters['channel_type'])))
            ->when(!empty($filters['assigned_to']), function ($q) use ($filters) {
                return $filters['assigned_to'] === 'me'
                    ? $q->where('assigned_to', auth()->id())
                    : ($filters['assigned_to'] === 'unassigned'
                        ? $q->whereNull('assigned_to')
                        : $q->where('assigned_to', $filters['assigned_to']));
            })
            ->when(!empty($filters['unread']), fn ($q) => $q->where('unread_count', '>', 0))
            ->when(!empty($filters['q']), function ($q) use ($filters) {
                $term = '%'.$filters['q'].'%';
                return $q->where(fn ($w) => $w->where('subject', 'like', $term)
                    ->orWhere('last_message_preview', 'like', $term)
                    ->orWhereHas('contact', fn ($c) => $c->where('display_name', 'like', $term)));
            })
            ->inbox()
            ->paginate($perPage);
    }

    public function thread(int $conversationId, int $limit = 100): ChatConversation
    {
        $conversation = ChatConversation::with(['channel', 'contact', 'assignee:id,name'])->findOrFail($conversationId);
        $conversation->setRelation(
            'messages',
            $conversation->messages()->with(['sender:id,name', 'attachments'])
                ->orderBy('created_at')->limit($limit)->get()
        );
        return $conversation;
    }

    /**
     * Record an outbound reply or private note, with optional media.
     * Delivery to the provider is deliberately not attempted here — no channel
     * credentials are configured yet, so the message is persisted and left for
     * a future dispatcher to transmit.
     *
     * @param  UploadedFile[]  $files
     */
    public function reply(
        int $conversationId,
        ?string $body,
        string $direction = 'outbound',
        array $files = [],
        array $meta = []
    ): ChatMessage {
        // Files are written outside the transaction so a storage failure can't leave a
        // half-committed message; the DB write below is what makes them visible.
        $stored = [];
        foreach ($files as $file) {
            $path = $file->store('chat/'.date('Y/m'), 'local');
            $stored[] = [
                'disk' => 'local',
                'path' => $path,
                'name' => $file->getClientOriginalName(),
                // Content-guessed, not client-claimed: getClientMimeType() is spoofable and
                // the stored value drives the UI's image/video/file branch.
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ];
        }

        return DB::transaction(function () use ($conversationId, $body, $direction, $stored, $meta) {
            $conversation = ChatConversation::lockForUpdate()->findOrFail($conversationId);

            $contentType = 'text';
            if ($stored) {
                $first = $stored[0]['mime'] ?? '';
                $contentType = match (true) {
                    str_starts_with($first, 'image/') => 'image',
                    str_starts_with($first, 'video/') => 'video',
                    str_starts_with($first, 'audio/') => 'audio',
                    default                           => 'file',
                };
            }

            $message = ChatMessage::create([
                'company_id'      => $conversation->company_id,
                'conversation_id' => $conversation->id,
                'user_id'         => auth()->id(),
                'direction'       => $direction,
                'content_type'    => $contentType,
                'body'            => $body,
                'status'          => $direction === 'note' ? 'sent' : 'queued',
                'meta'            => $meta ?: null,
                'sent_at'         => now(),
            ]);

            foreach ($stored as $attachment) {
                $message->attachments()->create($attachment);
            }

            // Private notes are internal: they must not surface as the thread's last activity.
            if ($direction !== 'note') {
                $preview = $body !== null && $body !== ''
                    ? mb_substr($body, 0, 191)
                    : '['.count($stored).' '.$contentType.']';   // media-only messages still need a preview
                $conversation->forceFill([
                    'last_message_preview' => $preview,
                    'last_message_at'      => now(),
                ]);
            }
            if ($conversation->status === 'resolved' || $conversation->status === 'closed') {
                $conversation->status = 'open';   // replying reopens a settled thread
            }
            $conversation->save();

            return $message->load(['sender:id,name', 'attachments']);
        });
    }

    public function assign(int $conversationId, ?int $userId): ChatConversation
    {
        $conversation = ChatConversation::findOrFail($conversationId);
        $conversation->assigned_to = $userId;
        if ($userId && $conversation->status === 'pending') $conversation->status = 'open';
        $conversation->save();
        return $conversation->load(['channel:id,type,name', 'contact:id,display_name,avatar_url', 'assignee:id,name']);
    }

    public function setStatus(int $conversationId, string $status): ChatConversation
    {
        $conversation = ChatConversation::findOrFail($conversationId);
        $conversation->status = $status;
        $conversation->save();
        return $conversation->load(['channel:id,type,name', 'contact:id,display_name,avatar_url', 'assignee:id,name']);
    }

    public function markRead(int $conversationId): ChatConversation
    {
        $conversation = ChatConversation::findOrFail($conversationId);
        $conversation->unread_count = 0;
        $conversation->save();
        return $conversation;
    }

    /** Counters for the inbox filter rail. */
    public function counts(): array
    {
        $base = fn () => ChatConversation::query();
        return [
            'all'        => $base()->count(),
            'open'       => $base()->where('status', 'open')->count(),
            'pending'    => $base()->where('status', 'pending')->count(),
            'resolved'   => $base()->where('status', 'resolved')->count(),
            'mine'       => $base()->where('assigned_to', auth()->id())->whereIn('status', ['open','pending'])->count(),
            'unassigned' => $base()->whereNull('assigned_to')->whereIn('status', ['open','pending'])->count(),
            'unread'     => $base()->where('unread_count', '>', 0)->count(),
        ];
    }
}
