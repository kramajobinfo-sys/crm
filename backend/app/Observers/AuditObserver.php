<?php
namespace App\Observers;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    public function created(Model $m): void  { $this->record($m, 'created', null, $m->getAttributes()); }
    public function updated(Model $m): void  { $this->record($m, 'updated', $this->dirty($m->getOriginal(), $m->getChanges()), $m->getChanges()); }
    public function deleted(Model $m): void  { $this->record($m, 'deleted', $m->getOriginal(), null); }
    public function restored(Model $m): void { $this->record($m, 'restored', null, $m->getAttributes()); }

    private function record(Model $m, string $event, ?array $old, ?array $new): void
    {
        try {
            AuditLog::create([
                'company_id' => $m->company_id ?? auth()->user()?->company_id, 'user_id' => auth()->id(),
                'auditable_type' => $m::class, 'auditable_id' => $m->getKey(), 'event' => $event,
                'old_values' => $old ? $this->scrub($old) : null, 'new_values' => $new ? $this->scrub($new) : null,
                'url' => request()->fullUrl(), 'ip_address' => request()->ip(),
                'user_agent' => substr(request()->userAgent() ?? '', 0, 500), 'created_at' => now(),
            ]);
        } catch (\Throwable $e) { logger()->warning('AuditObserver failed', ['error' => $e->getMessage()]); }
    }
    private function dirty(array $o, array $c): array { return array_intersect_key($o, $c); }
    private function scrub(array $d): array
    {
        foreach (['password','remember_token','two_factor_secret','smtp_password_encrypted'] as $f)
            if (array_key_exists($f, $d)) $d[$f] = '[redacted]';
        return $d;
    }
}
