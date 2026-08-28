<?php

namespace App\Http\Requests\Operator;

use Illuminate\Foundation\Http\FormRequest;

class StoreAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'alert_type_id' => ['required', 'exists:alert_types,id'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'response_instructions' => ['nullable', 'array'],
            'response_instructions.*' => ['string'],
            'severity' => ['required', 'in:low,medium,high,critical'],
            'target_roles' => ['required', 'array', 'min:1'],
            'target_roles.*' => ['in:student,faculty,staff'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['in:email,web_push,sms'],
        ];
    }
}
