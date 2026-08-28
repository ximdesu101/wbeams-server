<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlertTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'severity' => strtolower(trim($this->severity ?? '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $alertTypeId = ($this->route('alertType') instanceof \Illuminate\Database\Eloquent\Model ? $this->route('alertType')->getKey() : null);

        return [
            'emergency_category_id' => 'required|exists:emergency_categories,id',
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('alert_types', 'name')
                    ->where('emergency_category_id', $this->emergency_category_id)
                    ->ignore($alertTypeId),
            ],
            'description' => 'nullable|string',
            'response_instructions' => 'nullable|array',
            'response_instructions.*' => 'string',
            'severity' => 'required|in:low,medium,high,critical',
            'icon' => 'required|string',
            'color' => 'required|regex:/^#[0-9a-fA-F]{6}$/',
        ];
    }
}
