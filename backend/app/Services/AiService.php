<?php
namespace App\Services;

use App\Models\AiConversation;
use App\Models\AiInsight;
use App\Models\AiMessage;
use App\Models\AiPrediction;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * AI Assistant — structural, no live LLM. Chat replies and insights/predictions are heuristic
 * answers computed from the company's real CRM data. Swap `respond()` for a real model later.
 */
class AiService
{
    // ---- conversations & chat -------------------------------------------

    public function conversations(): Collection
    {
        return AiConversation::where('user_id', auth()->id())->withCount('messages')->latest('updated_at')->limit(50)->get();
    }

    public function findConversation(int $id): AiConversation
    {
        return AiConversation::where('user_id', auth()->id())->with('messages')->findOrFail($id);
    }

    public function createConversation(): AiConversation
    {
        return AiConversation::create(['user_id' => auth()->id(), 'title' => 'New conversation']);
    }

    public function sendMessage(AiConversation $conversation, string $content): AiMessage
    {
        return DB::transaction(function () use ($conversation, $content) {
            AiMessage::create([
                'company_id' => $conversation->company_id, 'ai_conversation_id' => $conversation->id,
                'role' => 'user', 'content' => $content,
            ]);

            [$reply, $meta] = $this->respond($content);
            $assistant = AiMessage::create([
                'company_id' => $conversation->company_id, 'ai_conversation_id' => $conversation->id,
                'role' => 'assistant', 'content' => $reply, 'meta' => $meta ?: null,
            ]);

            // First user message names the conversation.
            if ($conversation->title === 'New conversation') {
                $conversation->update(['title' => \Illuminate\Support\Str::limit($content, 48)]);
            } else {
                $conversation->touch();
            }
            return $assistant;
        });
    }

    /**
     * Heuristic reply keyed off the message text, grounded in real data. Returns [text, meta].
     *
     * Scoped to $companyId explicitly — see the note on generateInsights(). Without it a
     * platform admin's chat replies quote other tenants' invoice numbers, deal titles and
     * lead names.
     */
    private function respond(string $text): array
    {
        $t = mb_strtolower($text);
        $companyId = auth()->user()->company_id;
        $ccy = auth()->user()->company->base_currency ?? 'USD';
        $money = fn ($v) => number_format((float) $v, 0).' '.$ccy;

        if ($this->has($t, ['overdue', 'unpaid', 'outstanding', 'owe'])) {
            $overdue = fn () => Invoice::outstanding()->where('company_id', $companyId)->where('due_date', '<', now());
            $inv = $overdue()->orderByDesc('balance')->limit(5)->get();
            $total = $overdue()->sum('balance');
            if ($inv->isEmpty()) return ['Good news — there are no overdue invoices right now.', []];
            $lines = $inv->map(fn ($i) => "• {$i->invoice_no}: ".$money($i->balance).' outstanding')->implode("\n");
            return ["There are {$inv->count()} overdue invoice(s) totalling ".$money($total).":\n{$lines}", ['invoices' => $inv->pluck('id')]];
        }

        if ($this->has($t, ['deal', 'pipeline', 'forecast'])) {
            $deals = Deal::open()->where('company_id', $companyId)
                ->orderByDesc('amount')->with('customer:id,name')->limit(5)->get();
            $weighted = (float) Deal::open()->where('company_id', $companyId)
                ->sum(DB::raw('ROUND(amount * probability / 100, 2)'));
            $lines = $deals->map(fn ($d) => "• {$d->title} — ".$money($d->amount)." ({$d->probability}%)")->implode("\n");
            return ["Your weighted pipeline is worth about ".$money($weighted).". Top open deals:\n{$lines}", ['deals' => $deals->pluck('id')]];
        }

        if ($this->has($t, ['lead', 'hot', 'prospect'])) {
            $leads = Lead::open()->where('company_id', $companyId)
                ->where('rating', 'hot')->orderByDesc('score')->limit(5)->get();
            if ($leads->isEmpty()) return ['No hot leads at the moment. Warm and cold leads may still be worth a look.', []];
            $lines = $leads->map(fn ($l) => "• {$l->name} (score {$l->score})".($l->owner_id ? '' : ' — unassigned'))->implode("\n");
            return ["You have {$leads->count()} hot lead(s):\n{$lines}", ['leads' => $leads->pluck('id')]];
        }

        if ($this->has($t, ['ticket', 'support', 'sla', 'helpdesk'])) {
            $open = Ticket::open()->where('company_id', $companyId)->count();
            $breaching = Ticket::open()->where('company_id', $companyId)->where('due_at', '<', now())->count();
            return ["There are {$open} open ticket(s), {$breaching} of them breaching SLA. Prioritise the breaching ones first.", ['open' => $open, 'breaching' => $breaching]];
        }

        if ($this->has($t, ['summary', 'overview', 'how are', "how's", 'today', 'brief'])) {
            $revenue = Invoice::where('company_id', $companyId)->whereIn('status', Invoice::REVENUE_STATUSES)
                ->whereMonth('issue_date', now()->month)->sum('grand_total');
            $openDeals = Deal::open()->where('company_id', $companyId)->count();
            $openTickets = Ticket::open()->where('company_id', $companyId)->count();
            $hotLeads = Lead::open()->where('company_id', $companyId)->where('rating', 'hot')->count();
            return ["Here's your snapshot:\n• Revenue this month: ".$money($revenue)."\n• Open deals: {$openDeals}\n• Hot leads: {$hotLeads}\n• Open tickets: {$openTickets}",
                ['revenue' => $revenue, 'open_deals' => $openDeals]];
        }

        return ["I'm your Krama assistant. I can summarise your business or answer about deals & pipeline, hot leads, overdue invoices, and support tickets. Try “give me a summary” or “what deals are open?”.", []];
    }

    private function has(string $haystack, array $needles): bool
    {
        foreach ($needles as $n) if (str_contains($haystack, $n)) return true;
        return false;
    }

    // ---- insights --------------------------------------------------------

    public function insights(bool $includeDismissed = false): Collection
    {
        return AiInsight::where('company_id', auth()->user()->company_id)
            ->when(!$includeDismissed, fn ($q) => $q->where('is_dismissed', false))
            ->orderByRaw("FIELD(level,'critical','warning','info')")->latest('generated_at')->get();
    }

    /**
     * Regenerate the auto insights from current data (keeps dismissed ones out of the way).
     *
     * Every query here is scoped to $companyId EXPLICITLY rather than leaning on
     * BelongsToCompany's global scope: that scope is deliberately disabled for platform
     * admins, and admin@krama.local is both a platform admin and an ordinary tenant user.
     * Without the explicit filter the delete below is estate-wide and the reads aggregate
     * every tenant's data into this company's insights.
     */
    public function generateInsights(): Collection
    {
        $companyId = auth()->user()->company_id;

        AiInsight::where('company_id', $companyId)->where('is_dismissed', false)->delete();
        $ccy = auth()->user()->company->base_currency ?? 'USD';
        $money = fn ($v) => number_format((float) $v, 0).' '.$ccy;
        $now = now();

        $overdue = Invoice::outstanding()->where('company_id', $companyId)->where('due_date', '<', $now)->get();
        if ($overdue->count()) {
            $this->insight('overdue_invoices', 'warning', $overdue->count().' overdue invoice(s)',
                'Totalling '.$money($overdue->sum('balance')).' past their due date.', ['ids' => $overdue->pluck('id')]);
        }

        $stale = Deal::open()->where('company_id', $companyId)
            ->whereNotNull('expected_close_date')->where('expected_close_date', '<', $now)->get();
        if ($stale->count()) {
            $this->insight('stale_deals', 'warning', $stale->count().' deal(s) past their close date',
                'These open deals were expected to close already — worth a nudge.', ['ids' => $stale->pluck('id')]);
        }

        $hotUnassigned = Lead::open()->where('company_id', $companyId)
            ->where('rating', 'hot')->whereNull('owner_id')->get();
        if ($hotUnassigned->count()) {
            $this->insight('unassigned_hot_leads', 'critical', $hotUnassigned->count().' unassigned hot lead(s)',
                'Hot leads with no owner risk going cold. Assign them now.', ['ids' => $hotUnassigned->pluck('id')]);
        }

        $breaching = Ticket::open()->where('company_id', $companyId)->where('due_at', '<', $now)->count();
        if ($breaching) {
            $this->insight('sla_breaches', 'critical', $breaching.' ticket(s) breaching SLA',
                'Support tickets are past their resolution deadline.', ['count' => $breaching]);
        }

        if ($this->insights()->isEmpty()) {
            $this->insight('all_clear', 'info', 'Everything looks healthy',
                'No overdue invoices, stale deals, unassigned hot leads or SLA breaches right now.', []);
        }

        return $this->insights();
    }

    private function insight(string $type, string $level, string $title, string $body, array $meta): void
    {
        AiInsight::create([
            'company_id' => auth()->user()->company_id, 'type' => $type, 'level' => $level,
            'title' => $title, 'body' => $body, 'meta' => $meta ?: null, 'generated_at' => now(),
        ]);
    }

    public function dismissInsight(AiInsight $insight): void
    {
        $insight->update(['is_dismissed' => true]);
    }

    // ---- predictions -----------------------------------------------------

    public function predictions(): Collection
    {
        return AiPrediction::where('company_id', auth()->user()->company_id)->latest('generated_at')->get();
    }

    /** Explicitly company-scoped throughout — see the note on generateInsights(). */
    public function generatePredictions(): Collection
    {
        $companyId = auth()->user()->company_id;
        AiPrediction::where('company_id', $companyId)->delete();

        // Summed in SQL rather than by hydrating every open deal. ROUND is applied per row,
        // not once at the end, to match Deal::getWeightedAmountAttribute() exactly.
        $weighted = (float) Deal::open()->where('company_id', $companyId)
            ->sum(DB::raw('ROUND(amount * probability / 100, 2)'));
        $open = Deal::open()->where('company_id', $companyId)->sum('amount');
        AiPrediction::create([
            'company_id' => $companyId, 'type' => 'pipeline_forecast', 'title' => 'Pipeline forecast (weighted)',
            'value' => ['amount' => round($weighted, 2), 'open_amount' => round((float) $open, 2),
                        'confidence' => $open > 0 ? round($weighted / $open * 100, 1) : null],
            'generated_at' => now(),
        ]);

        $topLead = Lead::open()->where('company_id', $companyId)->orderByDesc('score')->first();
        if ($topLead) {
            AiPrediction::create([
                'company_id' => $companyId, 'type' => 'top_lead',
                'subject_type' => Lead::class, 'subject_id' => $topLead->id,
                'title' => 'Most likely to convert: '.$topLead->name,
                'value' => ['score' => $topLead->score, 'probability' => min($topLead->score, 95), 'rating' => $topLead->rating],
                'generated_at' => now(),
            ]);
        }

        // Simple churn signal: the customer with the largest overdue balance.
        $churnCustomerId = Invoice::outstanding()->where('company_id', $companyId)
            ->where('due_date', '<', now())->orderByDesc('balance')->value('customer_id');
        if ($churnCustomerId && ($cust = Customer::where('company_id', $companyId)->find($churnCustomerId))) {
            $overdue = Invoice::outstanding()->where('company_id', $companyId)
                ->where('customer_id', $cust->id)->where('due_date', '<', now())->sum('balance');
            AiPrediction::create([
                'company_id' => $companyId, 'type' => 'churn_risk',
                'subject_type' => Customer::class, 'subject_id' => $cust->id,
                'title' => 'At-risk account: '.$cust->name,
                'value' => ['overdue_balance' => round((float) $overdue, 2), 'signal' => 'overdue invoices'],
                'generated_at' => now(),
            ]);
        }

        return $this->predictions();
    }
}
