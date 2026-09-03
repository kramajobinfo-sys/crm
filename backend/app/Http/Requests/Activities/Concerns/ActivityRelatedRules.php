<?php

namespace App\Http\Requests\Activities\Concerns;

use Illuminate\Validation\Rule;

trait ActivityRelatedRules
{
    protected function relatedRules(): array
    {
        $types = ['deal' => 'deals', 'lead' => 'leads', 'customer' => 'customers',
            'contact' => 'contacts', 'quotation' => 'quotations'];
        $type = $this->input('related_type');
        $idRules = ['nullable', 'integer', 'required_with:related_type'];
        if (isset($types[$type])) {
            $idRules[] = Rule::exists($types[$type], 'id')->where('company_id', $this->user()->company_id);
        }
        return [
            'related_type' => ['nullable', 'string', Rule::in(array_keys($types))],
            'related_id' => $idRules,
        ];
    }
}
