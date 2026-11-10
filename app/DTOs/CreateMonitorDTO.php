<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\MonitorMethod;

readonly class CreateMonitorDTO
{
    public function __construct(
        public string $name,
        public string $url,
        public MonitorMethod $method = MonitorMethod::Get,
        public int $checkIntervalSeconds = 60,
        public int $timeoutSeconds = 10,
        public int $expectedStatusCode = 200,
        public int $consecutiveFailureThreshold = 3,
        public bool $sslCheckEnabled = true,
    ) {}

    /**
     * Build DTO from request payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $method = isset($data['method']) && is_string($data['method'])
            ? MonitorMethod::tryFrom(strtoupper($data['method'])) ?? MonitorMethod::Get
            : MonitorMethod::Get;

        return new self(
            name: (string) ($data['name'] ?? ''),
            url: (string) ($data['url'] ?? ''),
            method: $method,
            checkIntervalSeconds: isset($data['check_interval_seconds']) ? (int) $data['check_interval_seconds'] : 60,
            timeoutSeconds: isset($data['timeout_seconds']) ? (int) $data['timeout_seconds'] : 10,
            expectedStatusCode: isset($data['expected_status_code']) ? (int) $data['expected_status_code'] : 200,
            consecutiveFailureThreshold: isset($data['consecutive_failure_threshold']) ? (int) $data['consecutive_failure_threshold'] : 3,
            sslCheckEnabled: isset($data['ssl_check_enabled']) ? (bool) $data['ssl_check_enabled'] : true,
        );
    }

    /**
     * Convert DTO to Eloquent fillable array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'url' => $this->url,
            'method' => $this->method->value,
            'check_interval_seconds' => $this->checkIntervalSeconds,
            'timeout_seconds' => $this->timeoutSeconds,
            'expected_status_code' => $this->expectedStatusCode,
            'consecutive_failure_threshold' => $this->consecutiveFailureThreshold,
            'ssl_check_enabled' => $this->sslCheckEnabled,
        ];
    }
}
