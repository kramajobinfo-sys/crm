<?php
namespace App\Services;

use App\Models\KbArticle;
use App\Models\KbCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Knowledge Base (Zoho gap #8) — staff authoring side. The customer-facing read path lives in
 * PortalKbController and never goes through here. See docs/KB_SCOPE.md.
 */
class KbService
{
    public function paginate(array $f = [], int $perPage = 25): LengthAwarePaginator
    {
        return KbArticle::query()
            ->with(['category:id,name', 'author:id,name'])
            ->search($f['q'] ?? null)
            ->when(!empty($f['category_id']), fn ($q) => $q->where('category_id', $f['category_id']))
            ->when(!empty($f['status']) && $f['status'] !== 'all', fn ($q) => $q->where('status', $f['status']))
            ->when(!empty($f['visibility']) && $f['visibility'] !== 'all', fn ($q) => $q->where('visibility', $f['visibility']))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function find(int $id): KbArticle
    {
        return KbArticle::with(['category:id,name', 'author:id,name'])->findOrFail($id);
    }

    public function create(array $data): KbArticle
    {
        $data['author_id'] ??= auth()->id();
        $data['slug'] = $this->uniqueSlug($data['title'], $data['slug'] ?? null);
        $data = $this->stampPublished($data, null);
        return $this->find(KbArticle::create($data)->id);
    }

    public function update(KbArticle $article, array $data): KbArticle
    {
        if (array_key_exists('slug', $data) || array_key_exists('title', $data)) {
            $data['slug'] = $this->uniqueSlug($data['title'] ?? $article->title, $data['slug'] ?? null, $article->id);
        }
        $data = $this->stampPublished($data, $article);
        $article->update($data);
        return $this->find($article->id);
    }

    public function delete(KbArticle $article): void
    {
        $article->delete();
    }

    // ---- categories ------------------------------------------------------

    public function categories(): \Illuminate\Support\Collection
    {
        return KbCategory::orderBy('name')->get(['id', 'name', 'code', 'is_active']);
    }

    public function createCategory(array $data): KbCategory
    {
        return KbCategory::create($data);
    }

    // ---- helpers ---------------------------------------------------------

    /** Stamp published_at the first time an article becomes published; clear it if unpublished. */
    private function stampPublished(array $data, ?KbArticle $existing): array
    {
        if (!array_key_exists('status', $data)) return $data;
        $wasPublished = $existing && $existing->status === 'published';
        if ($data['status'] === 'published' && !$wasPublished) {
            $data['published_at'] = $existing?->published_at ?? now();
        } elseif ($data['status'] !== 'published') {
            $data['published_at'] = null;
        }
        return $data;
    }

    /** Per-company-unique slug derived from the title (or an explicit slug), with a numeric suffix. */
    private function uniqueSlug(string $title, ?string $explicit = null, ?int $ignoreId = null): string
    {
        $base = Str::slug($explicit ?: $title) ?: 'article';
        $companyId = auth()->user()?->company_id;
        $slug = $base;
        $n = 1;
        while (
            KbArticle::withoutGlobalScopes()->withTrashed()
                ->where('company_id', $companyId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.(++$n);
        }
        return $slug;
    }
}
