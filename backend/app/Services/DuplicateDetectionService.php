<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class DuplicateDetectionService
{
    /**
     * Proactive duplicate REVIEW: scan existing records of a type for groups that are
     * likely the same entity (normalized email / digits-only phone). Company-scoped.
     */
    public function scan(string $type, int $companyId, int $limit = 50): array
    {
        $table = match ($type) {
            'lead' => 'leads', 'account' => 'customers', 'contact' => 'contacts', default => null,
        };
        if (!$table) return ['type' => $type, 'groups' => []];

        $groups = array_merge(
            $this->scanBy($table, $companyId, 'email', "LOWER(TRIM(`email`))", "`email` IS NOT NULL AND `email` <> ''", $limit),
            $this->scanBy($table, $companyId, 'phone', "REGEXP_REPLACE(COALESCE(NULLIF(`phone`,''), `mobile`, ''), '[^0-9]+', '')", "COALESCE(NULLIF(`phone`,''), `mobile`) IS NOT NULL", $limit),
        );
        usort($groups, fn ($a, $b) => $b['count'] <=> $a['count']);
        return ['type' => $type, 'groups' => array_slice($groups, 0, $limit)];
    }

    private function scanBy(string $table, int $companyId, string $reason, string $normExpr, string $whereExtra, int $limit): array
    {
        $rows = DB::table($table)
            ->selectRaw("$normExpr AS k, GROUP_CONCAT(id ORDER BY id) AS ids, COUNT(*) AS c")
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereRaw($whereExtra)
            ->whereRaw("$normExpr <> ''")
            ->groupByRaw($normExpr)
            ->havingRaw('c > 1')
            ->orderByDesc('c')->limit($limit)->get();

        $out = [];
        foreach ($rows as $r) {
            $ids = array_map('intval', explode(',', (string) $r->ids));
            $records = DB::table($table)->whereIn('id', $ids)
                ->get(['id', 'name', 'email', 'phone', 'mobile'])
                ->map(fn ($x) => (array) $x)->all();
            $out[] = ['reason' => $reason, 'value' => (string) $r->k, 'count' => (int) $r->c, 'records' => $records];
        }
        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    public function check(string $type, array $data, ?int $excludeId = null): array
    {
        return match ($type) {
            'lead' => $this->leads($data, $excludeId),
            'account' => $this->accounts($data, $excludeId),
            'contact' => $this->contacts($data, $excludeId),
            default => [],
        };
    }

    private function leads(array $data, ?int $excludeId): array
    {
        $email = $this->text($data['email'] ?? null);
        $phone = $this->text($data['phone'] ?? $data['mobile'] ?? null);
        $company = $this->text($data['company_name'] ?? null);
        if (!$email && !$phone && !$company) return [];

        $rows = Lead::query()->when($excludeId, fn (Builder $q) => $q->whereKeyNot($excludeId))
            ->where(function (Builder $q) use ($email, $phone, $company) {
                if ($email) $q->orWhere('email', $email);
                if ($phone) $q->orWhere('phone', $phone)->orWhere('mobile', $phone);
                if ($company) $q->orWhere('company_name', $company);
            })->latest('id')->limit(10)->get();

        return $this->format($rows, 'lead', fn (Lead $row) => [
            'email' => $email && $this->same($row->email, $email),
            'phone' => $phone && ($this->same($row->phone, $phone) || $this->same($row->mobile, $phone)),
            'company_name' => $company && $this->same($row->company_name, $company),
        ], fn (Lead $row) => $row->company_name ?: ($row->email ?: $row->phone));
    }

    private function accounts(array $data, ?int $excludeId): array
    {
        $name = $this->text($data['name'] ?? $data['company_name'] ?? null);
        $email = $this->text($data['email'] ?? null);
        $phone = $this->text($data['phone'] ?? $data['mobile'] ?? null);
        $taxId = $this->text($data['tax_id'] ?? null);
        if (!$name && !$email && !$phone && !$taxId) return [];

        $rows = Customer::query()->when($excludeId, fn (Builder $q) => $q->whereKeyNot($excludeId))
            ->where(function (Builder $q) use ($name, $email, $phone, $taxId) {
                if ($name) $q->orWhere('name', $name)->orWhere('legal_name', $name);
                if ($email) $q->orWhere('email', $email);
                if ($phone) $q->orWhere('phone', $phone)->orWhere('mobile', $phone);
                if ($taxId) $q->orWhere('tax_id', $taxId);
            })->latest('id')->limit(10)->get();

        return $this->format($rows, 'account', fn (Customer $row) => [
            'tax_id' => $taxId && $this->same($row->tax_id, $taxId),
            'email' => $email && $this->same($row->email, $email),
            'phone' => $phone && ($this->same($row->phone, $phone) || $this->same($row->mobile, $phone)),
            'name' => $name && ($this->same($row->name, $name) || $this->same($row->legal_name, $name)),
        ], fn (Customer $row) => $row->customer_no.($row->email ? ' · '.$row->email : ''));
    }

    private function contacts(array $data, ?int $excludeId): array
    {
        $name = $this->text($data['name'] ?? null);
        $email = $this->text($data['email'] ?? null);
        $phone = $this->text($data['phone'] ?? $data['mobile'] ?? null);
        $accountId = isset($data['customer_id']) ? (int) $data['customer_id'] : null;
        if (!$email && !$phone && !($name && $accountId)) return [];

        $rows = Contact::query()->with('customer:id,name,customer_no')
            ->when($excludeId, fn (Builder $q) => $q->whereKeyNot($excludeId))
            ->where(function (Builder $q) use ($name, $email, $phone, $accountId) {
                if ($email) $q->orWhere('email', $email);
                if ($phone) $q->orWhere('phone', $phone)->orWhere('mobile', $phone);
                if ($name && $accountId) {
                    $q->orWhere(fn (Builder $inner) => $inner->where('name', $name)->where('customer_id', $accountId));
                }
            })->latest('id')->limit(10)->get();

        return $this->format($rows, 'contact', fn (Contact $row) => [
            'email' => $email && $this->same($row->email, $email),
            'phone' => $phone && ($this->same($row->phone, $phone) || $this->same($row->mobile, $phone)),
            'name_account' => $name && $accountId && $row->customer_id === $accountId && $this->same($row->name, $name),
        ], fn (Contact $row) => ($row->customer?->name ?: '—').($row->email ? ' · '.$row->email : ''));
    }

    /** @param Collection<int, mixed> $rows */
    private function format(Collection $rows, string $type, callable $matches, callable $secondary): array
    {
        return $rows->map(function ($row) use ($type, $matches, $secondary) {
            $reasons = collect($matches($row))->filter()->keys()->values()->all();
            $high = count(array_intersect($reasons, ['email', 'tax_id'])) > 0 || count($reasons) > 1;
            return [
                'type' => $type,
                'id' => $row->id,
                'label' => $row->name,
                'secondary' => $secondary($row),
                'confidence' => $high ? 'high' : 'medium',
                'reasons' => $reasons,
            ];
        })->values()->all();
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function same(?string $left, ?string $right): bool
    {
        return $left !== null && $right !== null && mb_strtolower(trim($left)) === mb_strtolower(trim($right));
    }
}
