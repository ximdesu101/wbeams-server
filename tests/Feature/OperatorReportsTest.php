<?php

use App\Models\Operator\Operator;
use App\Enums\OperatorStatus;
use App\Models\Recipient\Report;
use App\Models\Recipient\Recipient;

test('operator can fetch sos reports with coordinates', function () {
    $recipient = Recipient::factory()->create();
    $operator = Operator::factory()->create([
        'status' => OperatorStatus::Active,
    ]);

    Report::create([
        'recipient_id' => $recipient->id,
        'title' => 'Emergency SOS',
        'location' => 'Library area',
        'urgency' => 'critical',
        'status' => 'pending',
        'latitude' => 14.123456,
        'longitude' => 121.654321,
        'profile' => 'Student | BSIT',
        'details' => 'Need help',
    ]);

    $response = $this->actingAs($operator, 'operator')->getJson('/api/operator/reports');

    $response->assertOk()
        ->assertJsonPath('data.0.latitude', 14.123456)
        ->assertJsonPath('data.0.longitude', 121.654321)
        ->assertJsonPath('data.0.profile', 'Student | BSIT');
});

test('operator can update recipient report status', function () {
    $recipient = Recipient::factory()->create();
    $operator = Operator::factory()->create([
        'status' => OperatorStatus::Active,
    ]);

    $report = Report::create([
        'recipient_id' => $recipient->id,
        'title' => 'Medical SOS',
        'location' => 'Gymnasium',
        'urgency' => 'critical',
        'status' => 'pending',
        'latitude' => 14.123456,
        'longitude' => 121.654321,
        'profile' => 'Student | BSIT',
        'details' => 'Need medical help',
    ]);

    $response = $this->actingAs($operator, 'operator')
        ->patchJson("/api/operator/reports/{$report->id}/status", [
            'status' => 'acknowledged',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'acknowledged');

    $this->assertDatabaseHas('reports', [
        'id' => $report->id,
        'status' => 'acknowledged',
    ]);
});
