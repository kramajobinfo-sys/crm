<?php
namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Models\CustomFieldDefinition;
use App\Services\CustomFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin management of custom-field definitions. Field VALUES are validated + stored by the owning
 * entity (via CustomFieldService::sanitize); this only manages the definitions. A field's `key` is
 * derived from its label on create and never changes on update, so stored values never orphan.
 */
class CustomFieldController extends Controller
{
    public function __construct(private CustomFieldService $fields) {}

    public function index(Request $request): JsonResponse
    {
        $entity = $request->query('entity');
        $defs = CustomFieldDefinition::when($entity, fn ($q) => $q->where('entity', $entity))
            ->orderBy('entity')->orderBy('sort_order')->orderBy('id')->get();
        return $this->success(['entities' => CustomFieldDefinition::ENTITIES, 'types' => CustomFieldDefinition::TYPES, 'fields' => $defs]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->rules($request);
        $data['company_id'] = $request->user()->company_id;
        $data['key'] = $this->fields->keyFor($data['entity'], $data['company_id'], $data['label']);
        $def = CustomFieldDefinition::create($data);
        return $this->success($def, 'Field created', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $def = CustomFieldDefinition::findOrFail($id);
        $data = $this->rules($request, true);
        unset($data['entity'], $data['key']); // entity + key are immutable (values are keyed by them)
        $def->update($data);
        return $this->success($def, 'Field updated');
    }

    public function destroy(int $id): JsonResponse
    {
        CustomFieldDefinition::findOrFail($id)->delete();
        return $this->success(null, 'Field deleted');
    }

    private function rules(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'entity' => [$isUpdate ? 'sometimes' : 'required', Rule::in(CustomFieldDefinition::ENTITIES)],
            'label' => 'required|string|max:128',
            'type' => ['required', Rule::in(CustomFieldDefinition::TYPES)],
            'options' => 'nullable|array',
            'options.*' => 'string|max:128',
            'required' => 'boolean',
            'help' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'boolean',
        ]);
    }
}
