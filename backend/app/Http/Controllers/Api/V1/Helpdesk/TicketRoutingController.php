<?php
namespace App\Http\Controllers\Api\V1\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\TicketRoutingRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketRoutingController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->success(
            TicketRoutingRule::with('assignee:id,name')->orderBy('priority')->orderBy('id')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        return $this->success(TicketRoutingRule::create($this->validated($request)), 'Routing rule created', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $rule = TicketRoutingRule::findOrFail($id);
        $rule->update($this->validated($request));
        return $this->success($rule->fresh('assignee'));
    }

    public function destroy(int $id): JsonResponse
    {
        TicketRoutingRule::findOrFail($id)->delete();
        return $this->success(null, 'Deleted');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'                => ['required', 'string', 'max:120'],
            'strategy'            => ['required', Rule::in(TicketRoutingRule::STRATEGIES)],
            'priority'            => ['nullable', 'integer'],
            'conditions'          => ['nullable', 'array'],
            'conditions.*.field'  => ['required_with:conditions', 'string'],
            'conditions.*.op'     => ['nullable', 'string'],
            'assign_to_user_id'   => ['nullable', 'integer', 'required_if:strategy,specific'],
            'pool_user_ids'       => ['nullable', 'array', 'required_if:strategy,round_robin', 'required_if:strategy,least_busy'],
            'pool_user_ids.*'     => ['integer'],
            'is_active'           => ['nullable', 'boolean'],
        ]);
        $data['priority'] ??= 0;
        $data['is_active'] ??= true;
        return $data;
    }
}
