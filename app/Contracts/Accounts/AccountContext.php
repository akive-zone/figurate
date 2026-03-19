<?php

namespace App\Contracts\Accounts;

interface AccountContext
{
    public function hasAccount(): bool;

    public function primaryAccount(): ?object;

    public function canActAsHuman(): bool;
}
