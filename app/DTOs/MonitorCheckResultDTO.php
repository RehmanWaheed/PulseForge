<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class MonitorCheckResultDTO
{
    public function __construct(
        public int $monitorId,
        public bool $isSuccessful,
        public ?int $statusCode = null,
        public ?int $responseTimeMs = null,
        public ?string $errorMessage = null,
        public ?bool $sslIsValid = null,
        public ?int $sslDaysRemaining = null,
    ) {}

    /**
     * Create a successful check DTO.
     */
    public static function success(
        int $monitorId,
        int $statusCode,
        int $responseTimeMs,
        ?bool $sslIsValid = true,
        ?int $sslDaysRemaining = null
    ): self {
        return new self(
            monitorId: $monitorId,
            isSuccessful: true,
            statusCode: $statusCode,
            responseTimeMs: $responseTimeMs,
            errorMessage: null,
            sslIsValid: $sslIsValid,
            sslDaysRemaining: $sslDaysRemaining,
        );
    }

    /**
     * Create a failed check DTO.
     */
    public static function failure(
        int $monitorId,
        string $errorMessage,
        ?int $statusCode = null,
        ?int $responseTimeMs = null,
        ?bool $sslIsValid = null
    ): self {
        return new self(
            monitorId: $monitorId,
            isSuccessful: false,
            statusCode: $statusCode,
            responseTimeMs: $responseTimeMs,
            errorMessage: $errorMessage,
            sslIsValid: $sslIsValid,
            sslDaysRemaining: null,
        );
    }
}
