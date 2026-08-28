<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMasterlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id_number' => ['required', 'string', 'unique:masterlists,id_number'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(['student', 'faculty', 'staff'])],
            'student_program' => [
                'nullable',
                'required_if:role,student',
                Rule::in(['BSIT', 'BSCrim', 'BEED', 'BTLED', 'BSABE', 'BSA', 'BSF', 'BAT']),
            ],
            'student_year' => [
                'nullable',
                'required_if:role,student',
                Rule::in(['1st year', '2nd year', '3rd year', '4th year']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'student_program.required_if' => 'Student program is required for students.',
            'student_year.required_if' => 'Student year is required for students.',
        ];
    }
}