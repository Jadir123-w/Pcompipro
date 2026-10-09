<?php
namespace App\Http\Requests\System;

use App\Rules\SubdomainNotLatin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuestRegisterClientRequest extends FormRequest
{
    public function rules()
    {
        return [
            'number' => [
                'required',
                'numeric',
                Rule::unique('clients'), // Ajustar según tu tabla
            ],
            'name' => [
                'required',
                Rule::unique('system.clients')
            ],
            'subdomain' => [
                'required',
                new SubdomainNotLatin
            ],
            'password' => [
                'required',
                'min:6',
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
}