<?php
namespace App\Http\Requests\Hr;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $id = (int) $this->route('id');
        return [
            'employee_no' => ['nullable','string','max:32',
                Rule::unique('employees','employee_no')->where('company_id',$companyId)->ignore($id)],
            'first_name' => ['required','string','max:96'],
            'last_name'  => ['nullable','string','max:96'],
            'email'      => ['nullable','email','max:191'],
            'phone'      => ['nullable','string','max:32'],
            'department_id' => ['nullable','integer', Rule::exists('departments','id')->where('company_id',$companyId)],
            'branch_id'  => ['nullable','integer', Rule::exists('branches','id')->where('company_id',$companyId)],
            'manager_id' => ['nullable','integer', Rule::exists('employees','id')->where('company_id',$companyId)],
            'user_id'    => ['nullable','integer', Rule::exists('users','id')->where('company_id',$companyId)],
            'job_title'  => ['nullable','string','max:128'],
            'employment_type' => ['nullable', Rule::in(Employee::EMPLOYMENT_TYPES)],
            'status'     => ['nullable', Rule::in(Employee::STATUSES)],
            'hire_date'  => ['nullable','date'],
            'date_of_birth' => ['nullable','date'],
            'national_id' => ['nullable','string','max:64'],
            'salary'     => ['nullable','numeric','min:0'],
            'currency'   => ['nullable','string','size:3', Rule::exists('currencies','code')],
            'address'    => ['nullable','string','max:500'],
            'emergency_contact' => ['nullable','string','max:191'],
        ];
    }
}
