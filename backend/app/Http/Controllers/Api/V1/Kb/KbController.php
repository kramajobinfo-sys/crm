<?php
namespace App\Http\Controllers\Api\V1\Kb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kb\StoreArticleRequest;
use App\Http\Requests\Kb\UpdateArticleRequest;
use App\Http\Resources\KbArticleResource;
use App\Models\KbArticle;
use App\Services\KbService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KbController extends Controller
{
    public function __construct(private readonly KbService $kb) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => 'nullable|string|max:191',
            'category_id' => 'nullable|integer',
            'status' => 'nullable|string|in:all,draft,published',
            'visibility' => 'nullable|string|in:all,internal,public',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated($this->kb->paginate($f, (int) ($f['per_page'] ?? 25)), KbArticleResource::class);
    }

    public function meta(): JsonResponse
    {
        return $this->success([
            'categories' => $this->kb->categories(),
            'statuses' => KbArticle::STATUSES,
            'visibilities' => KbArticle::VISIBILITIES,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new KbArticleResource($this->kb->find($id)));
    }

    public function store(StoreArticleRequest $request): JsonResponse
    {
        return $this->success(new KbArticleResource($this->kb->create($request->validated())), 'Article created', 201);
    }

    public function update(UpdateArticleRequest $request, int $id): JsonResponse
    {
        $article = KbArticle::findOrFail($id);
        return $this->success(new KbArticleResource($this->kb->update($article, $request->validated())), 'Article updated');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->kb->delete(KbArticle::findOrFail($id));
        return $this->success(null, 'Article deleted');
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'name' => 'required|string|max:128',
            'code' => ['required','string','max:32', Rule::unique('kb_categories','code')->where('company_id',$companyId)],
        ]);
        $cat = $this->kb->createCategory($data);
        return $this->success(['id' => $cat->id, 'name' => $cat->name, 'code' => $cat->code], 'Category created', 201);
    }
}
