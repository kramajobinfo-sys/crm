<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadAssignmentRule extends Model
{
    use HasFactory, BelongsToCompany;

    public const STRATEGIES = ['specific', 'round_robin'];

    protected $fillable = [
        'company_id','name','priority','conditions','strategy',
        'assign_to_user_id','round_robin_user_ids','round_robin_cursor','is_active',
    ];
    protected function casts(): array
    {
        return [
            'conditions' => 'array', 'round_robin_user_ids' => 'array',
            'priority' => 'integer', 'round_robin_cursor' => 'integer', 'is_active' => 'boolean',
        ];
    }

    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assign_to_user_id'); }

    /**
     * All conditions must hold (AND). An empty condition set is a catch-all,
     * which is why priority ordering matters — put catch-alls last.
     */
    public function matches(Lead $lead): bool
    {
        foreach ($this->conditions ?? [] as $c) {
            $field = $c['field'] ?? null;
            if (!$field) continue;
            $actual = $lead->{$field};
            $expected = $c['value'] ?? null;

            $ok = match ($c['op'] ?? 'equals') {
                'equals'      => (string) $actual === (string) $expected,
                'not_equals'  => (string) $actual !== (string) $expected,
                'contains'    => $actual !== null && str_contains(mb_strtolower((string) $actual), mb_strtolower((string) $expected)),
                'gte'         => is_numeric($actual) && $actual >= $expected,
                'lte'         => is_numeric($actual) && $actual <= $expected,
                'is_set'      => filled($actual),
                'is_empty'    => blank($actual),
                default       => false,
            };
            if (!$ok) return false;
        }
        return true;
    }
}
