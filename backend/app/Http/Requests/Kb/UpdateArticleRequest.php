<?php
namespace App\Http\Requests\Kb;

use App\Models\KbArticle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateArticleRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $id = (int) $this->route('id');
        return [
            'title'       => ['sometimes','required','string','max:191'],
            'slug'        => ['nullable','string','max:191',
                Rule::unique('kb_articles','slug')->where('company_id',$companyId)->ignore($id)],
            'body'        => ['sometimes','required','string','max:50000'],
            'excerpt'     => ['nullable','string','max:500'],
            'category_id' => ['nullable','integer', Rule::exists('kb_categories','id')->where('company_id',$companyId)],
            'status'      => ['nullable', Rule::in(KbArticle::STATUSES)],
            'visibility'  => ['nullable', Rule::in(KbArticle::VISIBILITIES)],
        ];
    }
}
