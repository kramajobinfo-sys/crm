<?php
namespace App\Services\Dynamics\Syncers;

use App\Models\Product;

/**
 * BC `items` -> CRM `products` (pull only).
 *
 * `category_id` and `tax_rate_id` are deliberately not mapped: BC exposes ids from its own
 * category/tax tables, which mean nothing in the CRM, and inventing a mapping would attach
 * products to arbitrary CRM categories on every sync.
 */
class ItemSyncer extends PullSyncer
{
    public function crmEntity(): string { return 'product'; }
    public function bcEntity(): string  { return 'items'; }

    protected function businessKey(): string { return 'sku'; }
    protected function modelClass(): string  { return Product::class; }

    protected function defaultFieldMap(): array
    {
        return [
            'sku'        => 'number',
            'name'       => 'displayName',
            'unit'       => 'baseUnitOfMeasureCode',
            'cost_price' => 'unitCost',
            'sale_price' => 'unitPrice',
            'barcode'    => 'gtin',
            'type'       => 'type',
            'is_active'  => 'blocked',
        ];
    }

    protected function toAttributes(array $row, array $map, int $companyId, string $key): array
    {
        $str = fn (string $crmField) => $this->stringOrNull($row[$map[$crmField]] ?? null);
        $num = fn (string $crmField) => $this->decimalOrNull($row[$map[$crmField]] ?? null);

        // BC item `type` is 'Inventory' | 'Service' | 'Non-Inventory'. Only Inventory tracks
        // stock, so anything else becomes a CRM service (which skips the stock ledger).
        $bcType  = strtolower((string) ($str('type') ?? ''));
        $isGoods = $bcType === 'inventory';

        return [
            'company_id'      => $companyId,
            'sku'             => $key,
            'name'            => $str('name') ?? $key,
            'unit'            => $str('unit') ?? 'pcs',
            'cost_price'      => $num('cost_price') ?? 0,
            'sale_price'      => $num('sale_price') ?? 0,
            'barcode'         => $str('barcode'),
            'type'            => $isGoods ? 'goods' : 'service',
            'track_inventory' => $isGoods,
            // `blocked` is a bool on BC items (unlike customers, where it is a string).
            'is_active'       => !filter_var($row[$map['is_active']] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }
}
