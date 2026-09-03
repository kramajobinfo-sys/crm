<?php
namespace App\Http\Controllers\Api\V1\System;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $user = auth()->user();
        return $this->success([
            'unread_count' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->limit(30)->get()->map(fn ($n) => [
                'id'=>$n->id,'type'=>class_basename($n->type),'data'=>$n->data,
                'read'=>$n->read_at !== null,'created'=>$n->created_at->diffForHumans(),
            ]),
        ]);
    }
    public function markRead(string $id): JsonResponse
    {
        auth()->user()->notifications()->where('id',$id)->firstOrFail()->markAsRead();
        return $this->success(null, 'Marked read');
    }
    public function markAllRead(): JsonResponse
    {
        auth()->user()->unreadNotifications->markAsRead();
        return $this->success(null, 'All notifications marked read');
    }
}
