<?php

namespace Modules\Sale\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PendingAccountCommissionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->input('id');

        return [
            'seller_id' => [
                'required',
                Rule::unique('tenant.pending_account_commissions')->ignore($id),
            ],
            'amount' => [
                'required',
                'gt:0'
            ],
        ];
    }

    public function messages()
    {
        return [
            'amount.gt' => 'El monto debe ser mayor que 0.',
        ];
    }
}