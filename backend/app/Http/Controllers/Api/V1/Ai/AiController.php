<?php
namespace App\Http\Controllers\Api\V1\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiInsight;
use App\Services\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function __construct(private readonly AiService $ai) {}

    // ---- chat ------------------------------------------------------------

    public function conversations(): JsonResponse
    {
        return $this->success($this->ai->conversations()->map(fn ($c) => [
            'id' => $c->id, 'title' => $c->title, 'messages_count' => $c->messages_count,
            'updated_at' => $c->updated_at?->toIso8601String(),
        ]));
    }

    public function conversation(int $id): JsonResponse
    {
        return $this->success($this->serializeConversation($this->ai->findConversation($id)));
    }

    public function createConversation(): JsonResponse
    {
        return $this->success($this->serializeConversation($this->ai->createConversation()), 'Conversation started', 201);
    }

    public function send(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['content' => 'required|string|max:4000']);
        $conversation = AiConversation::where('user_id', auth()->id())->findOrFail($id);
        $this->ai->sendMessage($conversation, $data['content']);
        return $this->success($this->serializeConversation($this->ai->findConversation($id)), 'Sent', 201);
    }

    public function destroyConversation(int $id): JsonResponse
    {
        AiConversation::where('user_id', auth()->id())->findOrFail($id)->delete();
        return $this->success(null, 'Conversation deleted');
    }

    private function serializeConversation(AiConversation $c): array
    {
        return [
            'id' => $c->id, 'title' => $c->title,
            'messages' => $c->messages->map(fn ($m) => [
                'id' => $m->id, 'role' => $m->role, 'content' => $m->content,
                'created_at' => $m->created_at?->toIso8601String(),
            ]),
        ];
    }

    // ---- insights --------------------------------------------------------

    public function insights(): JsonResponse
    {
        return $this->success($this->ai->insights()->map(fn ($i) => $this->serializeInsight($i)));
    }

    public function generateInsights(): JsonResponse
    {
        return $this->success($this->ai->generateInsights()->map(fn ($i) => $this->serializeInsight($i)), 'Insights refreshed');
    }

    public function dismissInsight(int $id): JsonResponse
    {
        $insight = AiInsight::findOrFail($id);
        $this->ai->dismissInsight($insight);
        return $this->success(null, 'Insight dismissed');
    }

    private function serializeInsight(AiInsight $i): array
    {
        return [
            'id' => $i->id, 'type' => $i->type, 'level' => $i->level,
            'title' => $i->title, 'body' => $i->body, 'meta' => $i->meta,
            'generated_at' => $i->generated_at?->toIso8601String(),
        ];
    }

    // ---- predictions -----------------------------------------------------

    public function predictions(): JsonResponse
    {
        return $this->success($this->ai->predictions()->map(fn ($p) => [
            'id' => $p->id, 'type' => $p->type, 'title' => $p->title, 'value' => $p->value,
            'generated_at' => $p->generated_at?->toIso8601String(),
        ]));
    }

    public function generatePredictions(): JsonResponse
    {
        return $this->success($this->ai->generatePredictions()->map(fn ($p) => [
            'id' => $p->id, 'type' => $p->type, 'title' => $p->title, 'value' => $p->value,
        ]), 'Predictions refreshed');
    }
}
