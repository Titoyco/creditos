<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:191'],
            'username' => [
                'required',
                'string',
                'max:191',
                'alpha_dash',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'username.unique'     => 'El nombre de usuario ya está en uso por otro operador.',
            'username.alpha_dash' => 'El nombre de usuario solo puede contener letras, números, guiones y guiones bajos.',
        ];
    }
}
