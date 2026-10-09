<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->input('id');
        $type = $this->input('type');
        $email = $this->input('email');
        return [
            'number' => [
                'required',
                Rule::unique('tenant.persons', 'number')->where(function ($query) use($id, $type) {
                    $query->where('type', $type);
                })->ignore($id, 'id')
            ],
            'name' => [
                'required',
                Rule::unique('tenant.persons', 'name')->where(function ($query) use($id, $type) {
                    $query->where('type', $type);
                })->ignore($id, 'id')
            ],
            'identity_document_type_id' => [
                'required',
            ],
            'country_id' => [
                'required',
            ],
            // 'person_type_id' => [
            //     'required_if:type,"customers"',
            // ],
            'department_id' => [
                'required_if:identity_document_type_id,"066"',
            ],
            'province_id' => [
                'required_if:identity_document_type_id,"066"',
            ],
            'district_id' => [
                'required_if:identity_document_type_id,"066"',
            ],
            'address' => [
                'required_if:identity_document_type_id,"066"',
            ],
            'email' => [
                isset($email) ? 'required' : 'nullable',
                'email',
                Rule::unique('tenant.persons', 'email')->ignore($id, 'id')
            ]
            ,
            'internal_code' => 'max:100'
        ];
    }
}
