<?php

namespace App\Http\Controllers\Api\V1\System;

use App\Http\Controllers\Controller;
use App\Services\DuplicateDetectionService;
use App\Services\RecordMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DuplicateController extends Controller
{
    public function __construct(
        private readonly DuplicateDetectionService $duplicates,
        private readonly RecordMergeService $merges,
    ) {}

    /** Proactive duplicate review: groups of existing records likely to be the same entity. */
    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'  => ['required', Rule::in(['lead', 'account', 'contact'])],
            'limit' => 'nullable|integer|min:1|max:200',
        ]);
        $permission = match ($data['type']) {
            'lead' => 'leads.view', 'account' => 'customers.view', 'contact' => 'contacts.view',
        };
        $user = $request->user();
        abort_unless($user->isPlatformAdmin() || $user->can($permission), 403);

        return $this->success($this->duplicates->scan($data['type'], $user->company_id, (int) ($data['limit'] ?? 50)));
    }

    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['lead', 'account', 'contact'])],
            'exclude_id' => 'nullable|integer|min:1',
            'name' => 'nullable|string|max:191',
            'company_name' => 'nullable|string|max:191',
            'email' => 'nullable|string|max:191',
            'phone' => 'nullable|string|max:32',
            'mobile' => 'nullable|string|max:32',
            'tax_id' => 'nullable|string|max:64',
            'customer_id' => 'nullable|integer|min:1',
        ]);

        $permission = match ($data['type']) {
            'lead' => 'leads.view',
            'account' => 'customers.view',
            'contact' => 'contacts.view',
        };
        $user = $request->user();
        abort_unless($user->isPlatformAdmin() || $user->can($permission), 403);

        return $this->success($this->duplicates->check(
            $data['type'],
            $data,
            isset($data['exclude_id']) ? (int) $data['exclude_id'] : null,
        ));
    }

    public function previewMerge(Request $request): JsonResponse
    {
        $data = $this->validateMergeRequest($request);
        $this->authorizeMerge($request, $data['type']);
        return $this->success($this->merges->preview($data['type'], $data['primary_id'], $data['duplicate_id']));
    }

    public function merge(Request $request): JsonResponse
    {
        $data = $this->validateMergeRequest($request, true);
        $this->authorizeMerge($request, $data['type']);
        return $this->success($this->merges->merge(
            $data['type'], $data['primary_id'], $data['duplicate_id'], $data['field_sources'] ?? []
        ), 'Records merged successfully.');
    }

    private function validateMergeRequest(Request $request, bool $withSources = false): array
    {
        $rules = [
            'type' => ['required', Rule::in(['lead', 'account', 'contact'])],
            'primary_id' => 'required|integer|min:1|different:duplicate_id',
            'duplicate_id' => 'required|integer|min:1|different:primary_id',
        ];
        if ($withSources) {
            $rules['field_sources'] = 'sometimes|array';
            $rules['field_sources.*'] = ['required', Rule::in(['primary', 'duplicate'])];
        }
        return $request->validate($rules);
    }

    private function authorizeMerge(Request $request, string $type): void
    {
        $module = match ($type) { 'lead' => 'leads', 'account' => 'customers', 'contact' => 'contacts' };
        $user = $request->user();
        abort_unless($user->isPlatformAdmin() || ($user->can("{$module}.update") && $user->can("{$module}.delete")), 403);
    }
}
