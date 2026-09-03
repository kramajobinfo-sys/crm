<?php
namespace Database\Seeders;

use App\Models\Company;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo data for the Knowledge Base (Zoho gap #8). Idempotent; company_id passed explicitly
 * (BelongsToCompany's scope does not fire in an unauthenticated seeder).
 */
class KbSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'KRAMA')->first();
        if (!$company) return;
        $cid = $company->id;
        $author = User::where('company_id', $cid)->where('email', 'support@krama.local')->value('id')
            ?? User::where('company_id', $cid)->value('id');

        $cats = [];
        foreach ([['Getting started', 'START'], ['Billing', 'BILLING'], ['Troubleshooting', 'TROUBLE']] as [$name, $code]) {
            $cats[$code] = KbCategory::updateOrCreate(
                ['company_id' => $cid, 'code' => $code],
                ['name' => $name, 'is_active' => true]
            )->id;
        }

        // [title, category, status, visibility, excerpt, body]
        $articles = [
            ['How to sign in to the customer portal', 'START', 'published', 'public',
                'Step-by-step guide to accessing your portal account.',
                "Open the portal link we emailed you and enter your email and password.\n\nIf you don't have a password yet, ask your account manager to enable portal access for you."],
            ['Understanding your invoice', 'BILLING', 'published', 'public',
                'What each section of your invoice means.',
                "Your invoice lists each line item with quantity, unit price and tax.\n\nThe balance due is shown at the bottom. Payments are reflected within one business day."],
            ['Resetting a stuck device', 'TROUBLE', 'published', 'public',
                'A quick power-cycle usually resolves it.',
                "Unplug the device, wait 30 seconds, then plug it back in.\n\nIf the problem persists, open a support ticket from the portal and we'll help."],
            ['Internal: escalation matrix', 'TROUBLE', 'published', 'internal',
                'Who to page for each severity (staff only).',
                "Sev-1: on-call lead immediately.\nSev-2: team channel within 1 hour.\nThis article is internal and must never appear on the portal."],
            ['Draft: upcoming pricing changes', 'BILLING', 'draft', 'public',
                'Not published yet.',
                "Placeholder for the pricing-change announcement. Still in draft — must not be visible anywhere customer-facing."],
        ];

        foreach ($articles as [$title, $catCode, $status, $visibility, $excerpt, $body]) {
            KbArticle::updateOrCreate(
                ['company_id' => $cid, 'slug' => Str::slug($title)],
                [
                    'category_id' => $cats[$catCode] ?? null,
                    'title' => $title,
                    'body' => $body,
                    'excerpt' => $excerpt,
                    'status' => $status,
                    'visibility' => $visibility,
                    'author_id' => $author,
                    'published_at' => $status === 'published' ? now() : null,
                ]
            );
        }
    }
}
