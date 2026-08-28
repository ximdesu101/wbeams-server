<?php

use App\Models\Admin\Admin;
use App\Models\Operator\Alert;
use App\Models\Operator\AlertRecipientRead;
use App\Models\Operator\Operator;
use App\Enums\OperatorStatus;
use App\Models\Recipient\Recipient;
use Illuminate\Support\Str;

beforeEach(function () {
    Admin::create([
        'first_name' => 'Admin',
        'last_name' => 'User',
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);
});

test('admin can deactivate and activate an operator account', function () {
    $admin = Admin::first();
    $operator = Operator::factory()->create([
        'status' => OperatorStatus::Active,
        'activated_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'admin')
        ->patchJson("/api/admin/operators/{$operator->operator_id}/status", [
            'status' => 'deactivated',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'deactivated');

    $this->assertDatabaseHas('operators', [
        'operator_id' => $operator->operator_id,
        'status' => OperatorStatus::Deactivated,
    ]);

    $response = $this->actingAs($admin, 'admin')
        ->patchJson("/api/admin/operators/{$operator->operator_id}/status", [
            'status' => 'active',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'active');

    $this->assertDatabaseHas('operators', [
        'operator_id' => $operator->operator_id,
        'status' => OperatorStatus::Active,
    ]);
});

test('admin cannot activate or deactivate a pending operator account', function () {
    $admin = Admin::first();
    $operator = Operator::factory()->create([
        'status' => OperatorStatus::Inactive,
        'activated_at' => null,
    ]);

    // Attempt to force activate a pending account
    $response = $this->actingAs($admin, 'admin')
        ->patchJson("/api/admin/operators/{$operator->operator_id}/status", [
            'status' => 'active',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'This account has not been activated by the operator yet. The operator must complete activation first.');

    $this->assertDatabaseHas('operators', [
        'operator_id' => $operator->operator_id,
        'status' => OperatorStatus::Inactive,
        'activated_at' => null,
    ]);

    // Attempt to deactivate a pending account
    $response = $this->actingAs($admin, 'admin')
        ->patchJson("/api/admin/operators/{$operator->operator_id}/status", [
            'status' => 'deactivated',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'This account has not been activated by the operator yet. The operator must complete activation first.');
});

test('admin can fetch operator activity data for the current year', function () {
    $admin = Admin::first();
    $operator = Operator::factory()->create([
        'status' => OperatorStatus::Active,
    ]);
    $recipient = Recipient::factory()->create();

    Alert::factory()->createMany([
        [
            'operator_id' => $operator->id,
            'sent_at' => now()->subMonths(1),
        ],
        [
            'operator_id' => $operator->id,
            'sent_at' => now()->subMonths(1),
        ],
    ]);

    $alert = Alert::first();

    AlertRecipientRead::create([
        'alert_id' => $alert->id,
        'recipient_id' => $recipient->id,
        'read_at' => now()->subMonths(1),
    ]);

    $response = $this->actingAs($admin, 'admin')
        ->getJson("/api/admin/operators/{$operator->operator_id}/activity");

    $response->assertOk();
    $response->assertJsonCount(12, 'data');
    $response->assertJsonPath('data.0.month', now()->startOfYear()->format('F'));
});
