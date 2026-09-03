<?php
namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChatConversationResource;
use App\Http\Resources\ChatMessageResource;
use App\Models\ChatCannedResponse;
use App\Models\ChatChannel;
use App\Models\ChatConversation;
use App\Services\ChatService;
use App\Services\ChatCrmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChatController extends Controller
{
    public function __construct(private readonly ChatService $chat,private readonly ChatCrmService $crm) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status'       => 'nullable|string|in:all,open,pending,snoozed,resolved,closed',
            'channel_id'   => 'nullable|integer',
            'channel_type' => 'nullable|string|in:'.implode(',', ChatChannel::TYPES),
            'assigned_to'  => 'nullable|string',
            'unread'       => 'nullable|boolean',
            'q'            => 'nullable|string|max:191',
            'per_page'     => 'nullable|integer|min:1|max:100',
        ]);
        $paginator = $this->chat->conversations($filters, (int) ($filters['per_page'] ?? 25));
        return $this->paginated($paginator, ChatConversationResource::class);
    }

    public function counts(): JsonResponse
    {
        return $this->success($this->chat->counts());
    }

    public function show(int $id): JsonResponse
    {
        $conversation = $this->chat->thread($id);
        $this->chat->markRead($id);
        return $this->success(new ChatConversationResource($conversation));
    }

    /** Per-file ceiling in KB. Well under PHP's 64M: a 64MB upload through this
     *  stack times out before completing, which surfaces as a network error
     *  rather than a validation message. */
    private const MAX_UPLOAD_KB = 15360;   // 15 MB

    public function reply(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            // A media-only message (image with no caption) is legitimate.
            'body'          => 'required_without:attachments|nullable|string|max:8000',
            'direction'     => 'nullable|string|in:outbound,note',
            'attachments'   => 'nullable|array|max:10',
            'attachments.*' => 'file|max:'.self::MAX_UPLOAD_KB.'|mimetypes:'.implode(',', [
                'image/jpeg','image/png','image/gif','image/webp',
                'video/mp4','video/quicktime','video/webm',
                'audio/mpeg','audio/ogg','audio/wav','audio/mp4',
                'application/pdf',
            ]),
        ]);
        $direction = $data['direction'] ?? 'outbound';

        // Meta channels reject free-form replies outside the 24h window; refuse rather than
        // silently queue something the provider would drop. Internal notes are exempt.
        if ($direction === 'outbound') {
            $conversation = ChatConversation::with('channel')->findOrFail($id);
            if (!$conversation->serviceWindowOpen()) {
                return $this->error(
                    'The 24-hour service window for this channel has closed. Only an approved template message can be sent.',
                    422
                );
            }
        }

        $message = $this->chat->reply(
            $id,
            $data['body'] ?? null,
            $direction,
            $request->file('attachments') ?? []
        );
        return $this->success(new ChatMessageResource($message), 'Message queued', 201);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        // Scoped to the caller's company — unscoped, the assignee's name from another tenant
        // is echoed back by ChatConversationResource, enumerating the whole instance.
        $data = $request->validate([
            'user_id' => ['nullable', 'integer',
                Rule::exists('users', 'id')->where('company_id', $request->user()->company_id)],
        ]);
        $conversation = $this->chat->assign($id, $data['user_id'] ?? null);
        return $this->success(new ChatConversationResource($conversation), 'Conversation assigned');
    }

    public function status(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|string|in:'.implode(',', ChatConversation::STATUSES),
        ]);
        $conversation = $this->chat->setStatus($id, $data['status']);
        return $this->success(new ChatConversationResource($conversation), 'Status updated');
    }

    public function crmContext(Request $request,int $id): JsonResponse
    {
        $data=$request->validate(['q'=>'nullable|string|max:191']);
        return $this->success($this->crm->context(ChatConversation::findOrFail($id),$data['q']??null));
    }

    public function linkCrm(Request $request,int $id): JsonResponse
    {
        $companyId=$request->user()->company_id;
        $data=$request->validate(['type'=>'required|in:lead,contact,account','id'=>'required|integer']);
        $table=['lead'=>'leads','contact'=>'contacts','account'=>'customers'][$data['type']];
        $request->validate(['id'=>[Rule::exists($table,'id')->where('company_id',$companyId)->whereNull('deleted_at')]]);
        return $this->success($this->crm->link(ChatConversation::findOrFail($id),$data['type'],(int)$data['id']),'Social identity linked to CRM');
    }

    public function unlinkCrm(int $id): JsonResponse
    {
        return $this->success($this->crm->unlink(ChatConversation::findOrFail($id)),'Social identity unlinked from CRM');
    }

    public function createLead(Request $request,int $id): JsonResponse
    {
        $companyId=$request->user()->company_id;
        $data=$request->validate(['name'=>'required|string|max:191','company_name'=>'nullable|string|max:191','email'=>'nullable|email|max:191','phone'=>'nullable|string|max:32',
            'owner_id'=>['nullable','integer',Rule::exists('users','id')->where('company_id',$companyId)],'estimated_value'=>'nullable|numeric|min:0|max:9999999999999','currency'=>'nullable|string|size:3','notes'=>'nullable|string|max:5000']);
        try{$context=$this->crm->createLead(ChatConversation::findOrFail($id),$data);}catch(\RuntimeException $e){return $this->error($e->getMessage(),422);}
        return $this->success($context,'Lead created and linked',201);
    }

    /**
     * Active channels grouped by provider, since a company runs several accounts
     * per type (5 Facebook pages, 3 Instagram profiles, …). The encrypted config
     * blob is never selected, let alone serialised.
     */
    public function channels(): JsonResponse
    {
        $conversationCounts = ChatConversation::query()
            ->selectRaw('channel_id, COUNT(*) AS total')
            ->whereIn('status', ['open','pending'])
            ->groupBy('channel_id')
            ->pluck('total', 'channel_id');

        $groups = ChatChannel::query()->where('is_active', true)
            ->orderBy('type')->orderBy('name')
            ->get(['id','type','name','external_account_id'])
            ->groupBy('type')
            ->map(fn ($channels, $type) => [
                'type'     => $type,
                'total'    => $channels->sum(fn ($c) => (int) ($conversationCounts[$c->id] ?? 0)),
                'accounts' => $channels->map(fn ($c) => [
                    'id'                  => $c->id,
                    'name'                => $c->name,
                    'external_account_id' => $c->external_account_id,
                    'open_count'          => (int) ($conversationCounts[$c->id] ?? 0),
                ])->values(),
            ])->values();

        return $this->success($groups);
    }

    public function cannedResponses(): JsonResponse
    {
        return $this->success(
            ChatCannedResponse::query()->where('is_active', true)
                ->orderBy('shortcut')->get(['id','shortcut','title','body'])
        );
    }
}
