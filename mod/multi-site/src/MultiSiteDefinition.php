<?php

namespace Figurate\MultiSite;

final readonly class MultiSiteDefinition
{
    /**
     * @param  list<string>  $identifiers
     */
    public function __construct(
        public string $driver,
        public string $databaseStrategy,
        public bool $separateDatabase,
        public array $identifiers,
    ) {}
}
