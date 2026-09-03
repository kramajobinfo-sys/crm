<?php
namespace Database\Seeders;

use App\Models\AiConversation;
use App\Models\AiInsight;
use App\Models\AiMessage;
use App\Models\AiPrediction;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Seeder;

class AiSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'KRAMA')->first();
        if (!$company) return;
        $cid = $company->id;
        $ceo = User::where('company_id', $cid)->where('email', 'ceo@krama.local')->value('id');
        $ccy = $company->base_currency ?? 'AED';

        // A sample conversation.
        $convo = AiConversation::updateOrCreate(
            ['company_id' => $cid, 'user_id' => $ceo, 'title' => 'Business summary'],
            []
        );
        $convo->messages()->delete();
        AiMessage::create(['company_id' => $cid, 'ai_conversation_id' => $convo->id, 'role' => 'user', 'content' => 'Give me a summary of the business today.']);
        AiMessage::create(['company_id' => $cid, 'ai_conversation_id' => $convo->id, 'role' => 'assistant',
            'content' => "Here's your snapshot:\n• Revenue this month is tracking well\n• Several deals are in negotiation\n• A few invoices need chasing\nAsk me about deals, hot leads, overdue invoices or support tickets."]);

        // Insights derived from real data (explicit company scope; seeder is unauthenticated).
        AiInsight::withoutGlobalScopes()->where('company_id', $cid)->where('is_dismissed', false)->delete();

        // withoutGlobalScope('company'), not withoutGlobalScopes(): the plural form also strips
        // SoftDeletingScope, so these figures silently counted soft-deleted records. That is
        // how the seeded pipeline forecast came out as 479930 when the live value is 479660 —
        // it was including a deleted 270.00 deal.
        $overdue = Invoice::withoutGlobalScope('company')->where('company_id', $cid)->whereIn('status', ['issued','partially_paid'])->where('due_date', '<', now())->get();
        if ($overdue->count()) {
            AiInsight::create(['company_id' => $cid, 'type' => 'overdue_invoices', 'level' => 'warning',
                'title' => $overdue->count().' overdue invoice(s)',
                'body' => 'Totalling '.number_format($overdue->sum('balance'), 0).' '.$ccy.' past their due date.',
                'meta' => ['ids' => $overdue->pluck('id')], 'generated_at' => now()]);
        }

        $hotUnassigned = Lead::withoutGlobalScope('company')->where('company_id', $cid)->whereNull('converted_to_customer_id')
            ->where('rating', 'hot')->whereNull('owner_id')->count();
        if ($hotUnassigned) {
            AiInsight::create(['company_id' => $cid, 'type' => 'unassigned_hot_leads', 'level' => 'critical',
                'title' => $hotUnassigned.' unassigned hot lead(s)',
                'body' => 'Hot leads with no owner risk going cold. Assign them now.', 'generated_at' => now()]);
        }

        AiInsight::create(['company_id' => $cid, 'type' => 'stale_deals', 'level' => 'info',
            'title' => 'Pipeline is active',
            'body' => 'Deals are progressing across stages. Focus on the negotiation-stage deals to close this month.', 'generated_at' => now()]);

        // Predictions.
        AiPrediction::withoutGlobalScopes()->where('company_id', $cid)->delete();
        $weighted = Deal::withoutGlobalScope('company')->where('company_id', $cid)->where('status', 'open')->get()->sum(fn ($d) => $d->weighted_amount);
        $open = Deal::withoutGlobalScope('company')->where('company_id', $cid)->where('status', 'open')->sum('amount');
        AiPrediction::create(['company_id' => $cid, 'type' => 'pipeline_forecast', 'title' => 'Pipeline forecast (weighted)',
            'value' => ['amount' => round($weighted, 2), 'open_amount' => round((float) $open, 2),
                        'confidence' => $open > 0 ? round($weighted / $open * 100, 1) : null], 'generated_at' => now()]);

        $topLead = Lead::withoutGlobalScope('company')->where('company_id', $cid)->whereNull('converted_to_customer_id')->orderByDesc('score')->first();
        if ($topLead) {
            AiPrediction::create(['company_id' => $cid, 'type' => 'top_lead',
                'subject_type' => Lead::class, 'subject_id' => $topLead->id,
                'title' => 'Most likely to convert: '.$topLead->name,
                'value' => ['score' => $topLead->score, 'probability' => min($topLead->score, 95), 'rating' => $topLead->rating],
                'generated_at' => now()]);
        }

        $this->command?->info('Seeded 1 AI conversation, insights and predictions.');
    }
}
