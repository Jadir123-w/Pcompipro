<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;

class SendEmailGuestRegisterRequest extends FormRequest
{
    public function rules()
    {
        return [
            'user_id' => [
                'required',
            ],
            'key' => [
                'required',
            ],
            'email' => [
                'required',
                'email',
            ],
        ];
    }

    public function authorize()
    {
        return true;
    }

    public function messages()
    {
        return [
            'user_id.required' => 'El campo usuario es obligatorio.',
        ];
    }
}