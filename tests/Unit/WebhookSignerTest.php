<?php

declare(strict_types=1);

use App\Services\Webhooks\WebhookSigner;

test('it signs payload with HMAC SHA-256', function () {
    $signer = new WebhookSigner;
    $payload = '{"event":"incident.opened","id":123}';
    $secret = 'whsec_secret_key_12345';

    $signature = $signer->sign($payload, $secret);

    expect($signature)->toBe(hash_hmac('sha256', $payload, $secret));
});

test('it verifies signatures in constant time', function () {
    $signer = new WebhookSigner;
    $payload = '{"event":"incident.opened","id":123}';
    $secret = 'whsec_secret_key_12345';

    $signature = $signer->sign($payload, $secret);

    expect($signer->verify($payload, $signature, $secret))->toBeTrue()
        ->and($signer->verify($payload, 'invalid-signature-hash', $secret))->toBeFalse()
        ->and($signer->verify('{"tampered":"payload"}', $signature, $secret))->toBeFalse();
});

test('it generates random webhook secrets with whsec prefix', function () {
    $signer = new WebhookSigner;
    $secret = $signer->generateSecret();

    expect($secret)->toStartWith('whsec_')
        ->and(strlen($secret))->toBe(38);
});
