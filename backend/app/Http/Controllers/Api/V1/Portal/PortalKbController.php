<?php
namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\Portal\PortalKbArticleResource;
use App\Models\Contact;
use App\Models\KbArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer-facing knowledge base. Unlike the other three portal controllers, this does NOT filter
 * by customer_id — KB articles are company-wide knowledge, not per-customer records. The gate here
 * is the double predicate status=published AND visibility=public (KbArticle::scopePortalVisible),
 * applied to BOTH index and show. The IDOR guarded against is a portal contact fetching an
 * internal or draft article by id: it must 404. Uses a Portal resource that never exposes
 * status/visibility/author. See docs/KB_SCOPE.md.
 */
class PortalKbController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $contact = $this->contact();
        $q = $request->validate([
            'q' => 'nullable|string|max:191',
            'category_id' => 'nullable|integer',
        ]);

        $articles = $this->visible($contact)
            ->with('category:id,name')
            ->select(['id','company_id','title','slug','excerpt','view_count','category_id','published_at'])
            ->search($q['q'] ?? null)
            ->when(!empty($q['category_id']), fn ($b) => $b->where('category_id', $q['category_id']))
            ->orderByDesc('published_at')
            ->paginate(25);

        return $this->paginated($articles, PortalKbArticleResource::class);
    }

    public function show(int $id): JsonResponse
    {
        $article = $this->visible($this->contact())->with('category:id,name')->find($id);
        if (!$article) return $this->error('Article not found', 404);

        // Atomic view counter — a write on a GET; never read-modify-write (avoids the race).
        KbArticle::withoutGlobalScope('company')->whereKey($article->id)->increment('view_count');
        $article->view_count += 1;

        return $this->success(new PortalKbArticleResource($article));
    }

    private function contact(): Contact
    {
        /** @var Contact $contact */
        $contact = auth('portal')->user();
        return $contact;
    }

    /** Company-scoped + published + public. No customer_id filter — articles are company-wide. */
    private function visible(Contact $contact)
    {
        return KbArticle::withoutGlobalScope('company')
            ->where('company_id', $contact->company_id)
            ->portalVisible();
    }
}
