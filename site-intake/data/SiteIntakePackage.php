<?php

namespace App\SiteIntake\Data;

final readonly class SiteIntakePackage
{
    public function __construct(
        public array $manifest,
        public array $profile,
        public array $observedFiles = [],
        public bool $malformed = false,
    ) {}

    public static function fromArray(array $value): self
    {
        return new self(
            is_array($value['manifest'] ?? null) ? $value['manifest'] : [],
            is_array($value['profile'] ?? null) ? $value['profile'] : [],
            is_array($value['observed_files'] ?? null) ? $value['observed_files'] : [],
            (bool) ($value['malformed'] ?? false),
        );
    }
}
