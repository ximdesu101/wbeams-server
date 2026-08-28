<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmergencyCategoryRequest extends FormRequest
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
        $categoryId = ($this->route('emergencyCategory') instanceof \Illuminate\Database\Eloquent\Model ? $this->route('emergencyCategory')->getKey() : null);

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('emergency_categories', 'name')->ignore($categoryId),
            ],
            'description' => 'nullable|string'
        ];
    }
}
