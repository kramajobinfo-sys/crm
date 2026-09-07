<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; margin: 0; padding: 32px; }
  .row { width: 100%; }
  .head { display: table; width: 100%; margin-bottom: 24px; }
  .head .cell { display: table-cell; vertical-align: top; }
  .brand { font-size: 20px; font-weight: bold; color: {{ $company->primary_color ?: '#1d6fe0' }}; }
  .muted { color: #6b7280; }
  .doc-title { text-align: right; }
  .doc-title h1 { font-size: 22px; margin: 0; color: #111827; letter-spacing: 1px; }
  .meta { margin-top: 6px; text-align: right; }
  .meta div { margin-bottom: 2px; }
  .parties { display: table; width: 100%; margin: 18px 0; }
  .parties .cell { display: table-cell; width: 50%; vertical-align: top; }
  .label { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #9ca3af; margin-bottom: 4px; }
  table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
  table.items th { background: #f3f4f6; text-align: left; padding: 8px; font-size: 10px; text-transform: uppercase; color: #6b7280; border-bottom: 2px solid #e5e7eb; }
  table.items td { padding: 8px; border-bottom: 1px solid #eef0f3; }
  .num { text-align: right; }
  .totals { width: 40%; margin-left: 60%; margin-top: 14px; }
  .totals td { padding: 5px 8px; }
  .totals .grand { font-weight: bold; font-size: 14px; border-top: 2px solid #e5e7eb; color: #111827; }
  .foot { margin-top: 26px; }
  .foot .label { margin-top: 12px; }
  .status { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; text-transform: uppercase; background: #eef2ff; color: #4338ca; }
</style>
</head>
@php
  $cur = $q->currency ?: ($company->base_currency ?? 'USD');
  $m = fn ($v) => $cur.' '.number_format((float) $v, 2);
@endphp
<body>
  <div class="head">
    <div class="cell">
      <div class="brand">{{ $company->name ?? 'Company' }}</div>
      <div class="muted">
        @if($company->address_line1){{ $company->address_line1 }}<br>@endif
        @if($company->city){{ $company->city }}@endif @if($company->country), {{ $company->country }}@endif<br>
        @if($company->phone){{ $company->phone }} @endif @if($company->email)· {{ $company->email }}@endif
      </div>
    </div>
    <div class="cell doc-title">
      <h1>QUOTATION</h1>
      <div class="meta">
        <div><strong>{{ $q->quote_no }}</strong></div>
        <div class="muted">Issued: {{ optional($q->issue_date)->format('d M Y') }}</div>
        @if($q->valid_until)<div class="muted">Valid until: {{ $q->valid_until->format('d M Y') }}</div>@endif
        <div><span class="status">{{ $q->status }}</span></div>
      </div>
    </div>
  </div>

  <div class="parties">
    <div class="cell">
      <div class="label">Bill to</div>
      <div><strong>{{ $q->customer->name ?? '—' }}</strong></div>
      <div class="muted">
        @if($q->customer?->email){{ $q->customer->email }}<br>@endif
        @if($q->customer?->phone){{ $q->customer->phone }}@endif
        @if($q->customer?->tax_id)<br>Tax ID: {{ $q->customer->tax_id }}@endif
      </div>
    </div>
    <div class="cell">
      <div class="label">Prepared by</div>
      <div>{{ $q->owner->name ?? '—' }}</div>
    </div>
  </div>

  <table class="items">
    <thead>
      <tr>
        <th style="width:44%">Item</th>
        <th class="num">Qty</th>
        <th class="num">Unit price</th>
        <th class="num">Disc %</th>
        <th class="num">Tax</th>
        <th class="num">Amount</th>
      </tr>
    </thead>
    <tbody>
      @forelse($q->items as $it)
        <tr>
          <td>
            <strong>{{ $it->name }}</strong>
            @if($it->description)<br><span class="muted">{{ $it->description }}</span>@endif
          </td>
          <td class="num">{{ rtrim(rtrim(number_format($it->quantity, 2), '0'), '.') }}</td>
          <td class="num">{{ $m($it->unit_price) }}</td>
          <td class="num">{{ (float) $it->discount_pct ? number_format($it->discount_pct, 2).'%' : '—' }}</td>
          <td class="num">{{ $m($it->tax_amount) }}</td>
          <td class="num">{{ $m($it->line_total) }}</td>
        </tr>
      @empty
        <tr><td colspan="6" class="muted" style="text-align:center;padding:16px">No line items.</td></tr>
      @endforelse
    </tbody>
  </table>

  <table class="totals">
    <tr><td>Subtotal</td><td class="num">{{ $m($q->subtotal) }}</td></tr>
    @if((float) $q->discount_total)<tr><td>Discount</td><td class="num">−{{ $m($q->discount_total) }}</td></tr>@endif
    <tr><td>Tax</td><td class="num">{{ $m($q->tax_total) }}</td></tr>
    <tr class="grand"><td>Total</td><td class="num">{{ $m($q->grand_total) }}</td></tr>
  </table>

  <div class="foot">
    @if($q->terms)<div class="label">Terms</div><div class="muted">{{ $q->terms }}</div>@endif
    @if($q->notes)<div class="label">Notes</div><div class="muted">{{ $q->notes }}</div>@endif
  </div>
</body>
</html>
