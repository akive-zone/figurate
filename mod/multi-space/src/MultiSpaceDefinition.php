<?php

namespace Figurate\MultiSpace;

final readonly class MultiSpaceDefinition
{
    /**
     * @param  list<string>  $scopeColumns
     */
    public function __construct(
        public string $driver,
        public string $databaseStrategy,
        public bool $sharedDatabase,
        public bool $requiresSiteContext,
        public string $parentLayer,
        public array $scopeColumns,
    ) {}
}
