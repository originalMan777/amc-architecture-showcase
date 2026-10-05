<?php

namespace App\SiteIntake\Data;

final readonly class NormalizationResult
{
    public function __construct(public array $profile, public array $errors, public array $warnings, public string $checksum, public array $evidence) {}

    public function valid(): bool
    {
        return $this->errors === [];
    }
}
