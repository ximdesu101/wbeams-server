<?php

use App\Models\Recipient\Recipient;
use App\Models\Recipient\Report;

test('recipient can submit an emergency sos report with coordinates and profile details', function () {
    $recipient = Recipient::factory()->create();

    $response = $this->actingAs($recipient, 'recipient')->postJson('/api/recipient/emergency-sos', [
        'location' => 'Library area',
        'latitude' => 14.123456,
        'longitude' => 121.654321,
        'profile' => 'Student | BSIT | 2nd year',
        'details' => 'Need immediate assistance near the library.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Emergency SOS')
        ->assertJsonPath('data.sender.first_name', $recipient->first_name)
        ->assertJsonPath('data.latitude', 14.123456)
        ->assertJsonPath('data.longitude', 121.654321);

    $this->assertDatabaseHas('reports', [
        'recipient_id' => $recipient->id,
        'title' => 'Emergency SOS',
        'location' => 'Library area',
    ]);

    expect(Report::where('recipient_id', $recipient->id)->latest()->first()->details)
        ->toContain('Need immediate assistance near the library.');
});
