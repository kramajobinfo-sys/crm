<?php
namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Controllers\Controller;
use App\Models\ChatChannel;
use App\Services\ChannelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Illuminate\Database\QueryException;

class ChannelSettingsController extends Controller
{
    public function __construct(private readonly ChannelService $channels) {}

    public function index(): JsonResponse
    {
        return $this->success($this->channels->list());
    }

    public function meta(): JsonResponse
    {
        return $this->success([
            'types' => $this->channels->types(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $channel = ChatChannel::withCount('conversations')->findOrFail($id);
        return $this->success($this->channels->present($channel));
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'type' => ['required','string', Rule::in(ChatChannel::TYPES)],
            'name' => ['required','string','max:128',
                Rule::unique('chat_channels','name')->where('company_id',$companyId)->where('type',$request->input('type'))],
            'config' => ['nullable','array'],
            'is_active' => ['nullable','boolean'],
        ]);
        try {
            $channel = $this->channels->create($data);
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success($this->channels->present($channel->loadCount('conversations')), 'Channel connected', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $channel = ChatChannel::findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes','string','max:128',
                Rule::unique('chat_channels','name')->where('company_id',$companyId)->where('type',$channel->type)->ignore($channel->id)],
            'config' => ['nullable','array'],
            'is_active' => ['nullable','boolean'],
        ]);
        $channel = $this->channels->update($channel, $data);
        return $this->success($this->channels->present($channel->loadCount('conversations')), 'Channel updated');
    }

    public function test(int $id): JsonResponse
    {
        $channel = ChatChannel::findOrFail($id);
        $result = $this->channels->test($channel);
        return $result['ok']
            ? $this->success($result, 'Connection looks good')
            : $this->error($result['message'], 422, ['missing' => $result['missing']]);
    }

    public function destroy(int $id): JsonResponse
    {
        ChatChannel::findOrFail($id)->delete();
        return $this->success(null, 'Channel removed');
    }
}
