<?php

namespace Figurate\AccountManager\Contracts;

interface AccountContext
{
    public function hasAccount(): bool;

    public function primaryAccount(): ?object;

    public function canActAsHuman(): bool;
}
