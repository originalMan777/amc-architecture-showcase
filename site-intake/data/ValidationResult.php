<?php

namespace App\SiteIntake\Data;

final readonly class ValidationResult
{
    public function __construct(public string $status, public array $errors) {}

    public function toArray(): array
    {
        return ['status' => $this->status, 'errors' => $this->errors];
    }
}
