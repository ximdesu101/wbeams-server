<?php

namespace App\Http\Requests\Recipient;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    /**
     * Canonical campus locations with static coordinates.
     * Must stay in sync with recipient + operator frontend campusLocations.js
     *
     * @var array<string, array{lat: float, lng: float}>
     */
    public const CAMPUS_COORDINATES = [
        'University gate area' => ['lat' => 11.982806, 'lng' => 124.817722],
        'Covered court' => ['lat' => 11.982417, 'lng' => 124.817778],
        "Registrar's office area" => ['lat' => 11.9823, 'lng' => 124.8169],
        'Library area' => ['lat' => 11.982056, 'lng' => 124.816639],
        'Canteen area' => ['lat' => 11.981917, 'lng' => 124.817083],
        'Computer laboratory' => ['lat' => 11.98225, 'lng' => 124.8174],
        'Nachura hall building' => ['lat' => 11.9825, 'lng' => 124.817],
        'Agriculture department area' => ['lat' => 11.9817, 'lng' => 124.8175],
    ];

    public const CAMPUS_LOCATIONS = [
        'University gate area',
        'Covered court',
        "Registrar's office area",
        'Library area',
        'Canteen area',
        'Computer laboratory',
        'Nachura hall building',
        'Agriculture department area',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255', Rule::in(self::CAMPUS_LOCATIONS)],
            'urgency' => ['required', 'in:low,medium,high,critical'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            // MediaRecorder often produces webm (sometimes detected as video/webm even for audio-only).
            // Accept common browser recording types + extensions.
            'video' => [
                'nullable',
                'file',
                'max:51200',
                'mimetypes:video/mp4,video/webm,video/quicktime,video/x-matroska,application/octet-stream',
            ],
            'voice' => [
                'nullable',
                'file',
                'max:10240',
                'mimetypes:audio/webm,audio/ogg,audio/mpeg,audio/wav,audio/mp4,audio/x-wav,audio/wave,video/webm,application/octet-stream',
            ],
        ];
    }

    /**
     * Resolve static lat/lng for the selected campus location.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function coordinatesForLocation(): ?array
    {
        return self::CAMPUS_COORDINATES[$this->input('location')] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'location.in' => 'Please select a valid campus location.',
            'video.max' => 'Video must not exceed 50 MB.',
            'voice.max' => 'Voice message must not exceed 10 MB.',
        ];
    }
}