<?php

use App\Models\Recipient\Recipient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

test('recipient password reset code is sent without revealing whether an email exists', function () {
    Mail::fake();
    $recipient = Recipient::factory()->create();

    $this->postJson('/api/recipient/password/send-reset-code', [
        'email' => $recipient->email,
    ])->assertOk()
        ->assertJsonPath('message', 'If an account with that email exists, a password reset code has been sent.');

    expect(Cache::has('recipient-password-reset-otp:'.strtolower($recipient->email)))->toBeTrue();

    $this->postJson('/api/recipient/password/send-reset-code', [
        'email' => 'unknown@example.com',
    ])->assertOk()
        ->assertJsonPath('message', 'If an account with that email exists, a password reset code has been sent.');
});

test('recipient can reset password with a valid code and existing tokens are revoked', function () {
    $recipient = Recipient::factory()->create();
    $recipient->createToken('existing-session');
    Cache::put(
        'recipient-password-reset-otp:'.strtolower($recipient->email),
        Hash::make('123456'),
        now()->addMinutes(10),
    );

    $this->postJson('/api/recipient/password/reset', [
        'email' => $recipient->email,
        'code' => '123456',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertOk()
        ->assertJsonPath('message', 'Password reset successfully. You can now log in with your new password.');

    expect(Hash::check('new-password-123', $recipient->fresh()->password))->toBeTrue()
        ->and($recipient->tokens()->count())->toBe(0)
        ->and(Cache::has('recipient-password-reset-otp:'.strtolower($recipient->email)))->toBeFalse();
});

test('recipient password reset rejects an invalid code', function () {
    $recipient = Recipient::factory()->create();
    Cache::put(
        'recipient-password-reset-otp:'.strtolower($recipient->email),
        Hash::make('123456'),
        now()->addMinutes(10),
    );

    $this->postJson('/api/recipient/password/reset', [
        'email' => $recipient->email,
        'code' => '654321',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'The reset code is invalid or expired. Please request a new code.');

    expect(Hash::check('password', $recipient->fresh()->password))->toBeTrue();
});
