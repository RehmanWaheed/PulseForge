<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use Illuminate\Support\Str;

class WebhookSigner
{
    /**
     * Compute HMAC SHA-256 signature for payload.
     */
    public function sign(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Verify HMAC SHA-256 signature in constant time against timing attacks.
     */
    public function verify(string $payload, string $signature, string $secret): bool
    {
        $expected = $this->sign($payload, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Generate a cryptographically secure webhook secret.
     */
    public function generateSecret(): string
    {
        return 'whsec_'.Str::random(32);
    }
}
