<?php

namespace App\Support\ThreadObservers;

class ObserverResult
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        public string $eventType,
        public string $severity = 'low',
        public ?array $payload = null,
        public bool $redactMessage = false,
    ) {}
}
