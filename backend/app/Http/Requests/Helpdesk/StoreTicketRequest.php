<?php
namespace App\Http\Requests\Helpdesk;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        return [
            'subject'         => ['required','string','max:191'],
            'description'     => ['nullable','string','max:10000'],
            'priority'        => ['nullable', Rule::in(Ticket::PRIORITIES)],
            'channel'         => ['nullable', Rule::in(Ticket::CHANNELS)],
            'category_id'     => ['nullable','integer', Rule::exists('ticket_categories','id')->where('company_id',$companyId)],
            'customer_id'     => ['nullable','integer', Rule::exists('customers','id')->where('company_id',$companyId)],
            'requester_name'  => ['nullable','string','max:191'],
            'requester_email' => ['nullable','email','max:191'],
            'assigned_to'     => ['nullable','integer', Rule::exists('users','id')->where('company_id',$companyId)],
        ];
    }
}
