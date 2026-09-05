<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactConsent;
use App\Models\EmailSuppression;
use App\Support\UnsubscribeToken;
use Illuminate\Http\Response;

/** Public, no-auth self-service unsubscribe reached from a tokenized link in marketing emails. */
class UnsubscribeController extends Controller
{
    public function unsubscribe(string $token): Response
    {
        $data = UnsubscribeToken::decode($token);
        if (!$data) return $this->page('Link not valid', 'This unsubscribe link is invalid or has expired.', null, false);

        $email = $this->applyUnsubscribe($data);

        return $this->page("You're unsubscribed",
            'You will no longer receive marketing emails at <strong>' . e($email) . '</strong>.', $token, true);
    }

    /** RFC 8058 one-click: mail providers POST here with no auth; a 2xx is all they need. */
    public function oneClick(string $token): Response
    {
        $data = UnsubscribeToken::decode($token);
        if (!$data) return response('Invalid link', 400);
        $this->applyUnsubscribe($data);
        return response('Unsubscribed', 200);
    }

    /** Record the marketing opt-out (email suppression list + mirror to any Contact). */
    private function applyUnsubscribe(array $data): string
    {
        $email = strtolower(trim($data['e']));
        EmailSuppression::updateOrCreate(
            ['company_id' => $data['c'], 'email' => $email],
            ['source' => 'unsubscribe_link', 'campaign_id' => $data['k'] ?? null],
        );
        $this->mirrorToContacts($data['c'], $email, 'withdrawn', 'unsubscribe_link');
        return $email;
    }

    public function resubscribe(string $token): Response
    {
        $data = UnsubscribeToken::decode($token);
        if (!$data) return $this->page('Link not valid', 'This link is invalid or has expired.', null, false);

        $email = strtolower(trim($data['e']));
        EmailSuppression::where('company_id', $data['c'])->where('email', $email)->delete();
        $this->mirrorToContacts($data['c'], $email, 'granted', 'resubscribe_link');

        return $this->page("You're resubscribed",
            'You will again receive marketing emails at <strong>' . e($email) . '</strong>.', null, true);
    }

    /** Keep the CRM contact consent panel in sync when the address maps to a Contact. */
    private function mirrorToContacts(int $companyId, string $email, string $status, string $source): void
    {
        $contacts = Contact::withoutGlobalScopes()
            ->where('company_id', $companyId)->whereRaw('LOWER(email) = ?', [$email])->get(['id']);
        foreach ($contacts as $c) {
            ContactConsent::create([
                'company_id' => $companyId, 'contact_id' => $c->id, 'channel' => 'marketing',
                'status' => $status, 'source' => $source, 'occurred_at' => now(),
            ]);
        }
    }

    private function page(string $title, string $bodyHtml, ?string $token, bool $ok): Response
    {
        $accent = $ok ? ['#ECFDF3', '#12B76A', '&#10003;'] : ['#FEF3F2', '#F04438', '!'];
        $resub = $token
            ? '<p style="margin:20px 0 0;font-size:13px;color:#8A94A6">Changed your mind? '
              . '<a href="' . e(route('unsubscribe.resubscribe', ['token' => $token])) . '" style="color:#1D6FE0;text-decoration:none">Resubscribe</a>.</p>'
            : '';
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . '</title></head>'
            . '<body style="margin:0;font-family:Inter,system-ui,-apple-system,sans-serif;background:#F5F7FA;color:#101828">'
            . '<div style="max-width:440px;margin:12vh auto;padding:36px 32px;background:#fff;border:1px solid #E4E8EF;border-radius:16px;box-shadow:0 1px 3px rgba(16,24,40,.06);text-align:center">'
            . '<div style="width:52px;height:52px;border-radius:50%;background:' . $accent[0] . ';color:' . $accent[1] . ';font-size:26px;line-height:52px;margin:0 auto 18px;font-weight:700">' . $accent[2] . '</div>'
            . '<h1 style="font-size:19px;margin:0 0 10px;letter-spacing:-.01em">' . e($title) . '</h1>'
            . '<p style="font-size:14px;line-height:1.5;color:#475467;margin:0">' . $bodyHtml . '</p>' . $resub
            . '</div></body></html>';
        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
