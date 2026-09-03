<?php
namespace App\Http\Controllers\Api\V1\Visits;

use App\Http\Controllers\Controller;
use App\Http\Resources\WebVisitorResource;
use App\Models\Lead;
use App\Services\VisitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Authenticated read side of website tracking, plus site-key management. */
class VisitController extends Controller
{
    public function __construct(private readonly VisitService $visits) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q'        => 'nullable|string|max:191',
            'status'   => 'nullable|string|in:all,identified,anonymous',
            'lead_id'  => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return $this->paginated(
            $this->visits->paginateVisitors($f, (int) ($f['per_page'] ?? 25)),
            WebVisitorResource::class
        );
    }

    public function stats(): JsonResponse
    {
        return $this->success($this->visits->stats());
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new WebVisitorResource($this->visits->findVisitor($id)));
    }

    /** Page views for one lead, across every visitor that identified as them. */
    public function forLead(int $leadId): JsonResponse
    {
        Lead::findOrFail($leadId);   // company-scoped: another tenant's lead is simply not found

        return $this->success($this->visits->pageViewsForLead($leadId)->map(fn ($v) => [
            'id' => $v->id, 'url' => $v->url, 'path' => $v->path, 'title' => $v->title,
            'referrer' => $v->referrer, 'occurred_at' => $v->occurred_at?->toIso8601String(),
        ]));
    }

    /**
     * The tracking snippet for this tenant. Generates the key on first view so a tenant never
     * has to think about creating one.
     */
    public function tracker(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        $key = $company->visits_site_key ?: $this->visits->rotateSiteKey($company);

        return $this->success([
            'site_key' => $key,
            'script_url' => url('/api/v1/visits/t.js'),
            'snippet' => sprintf('<script src="%s" data-key="%s" async></script>', url('/api/v1/visits/t.js'), $key),
            'identify_hint' => "krama.identify('customer@example.com')",
        ]);
    }

    /** Rotating invalidates every snippet already deployed — that is the point of it. */
    public function rotate(Request $request): JsonResponse
    {
        $key = $this->visits->rotateSiteKey($request->user()->company);

        return $this->success([
            'site_key' => $key,
            'snippet' => sprintf('<script src="%s" data-key="%s" async></script>', url('/api/v1/visits/t.js'), $key),
        ], 'Site key rotated — update the snippet on your website');
    }
}
