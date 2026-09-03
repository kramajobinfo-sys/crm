<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Crosswalk between a CRM record and its Business Central counterpart.
 * This table is what makes sync idempotent: without it, every run re-creates
 * records on the far side. bc_entity is part of the key because BC GUIDs are
 * unique per entity set, so a customer and a vendor id can collide.
 */
class BcRecordLink extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','connection_id','mapping_id','crm_type','crm_id',
        'bc_entity','bc_id','bc_etag','payload_hash','last_direction','last_synced_at',
    ];
    protected function casts(): array { return ['last_synced_at' => 'datetime']; }

    public function connection(): BelongsTo { return $this->belongsTo(BcConnection::class, 'connection_id'); }
    public function mapping(): BelongsTo    { return $this->belongsTo(BcEntityMapping::class, 'mapping_id'); }

    /** True when the local payload differs from what was last pushed/pulled. */
    public function hasDrifted(string $currentHash): bool
    {
        return $this->payload_hash !== null && $this->payload_hash !== $currentHash;
    }
}
