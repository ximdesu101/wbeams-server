<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOperatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'contact_number' => preg_replace('/\D+/', '', (string) $this->contact_number),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'contact_number' => [
                'required',
                'string',
                'regex:/^09\d{9}$/',
                Rule::unique('operators', 'contact_number'),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('operators', 'email'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_number.unique' => 'This contact number is already registered.',
            'contact_number.regex' => 'Please enter a valid PH mobile number (e.g. 09171234567).',
            'email.unique' => 'This email is already registered.',
        ];
    }
}