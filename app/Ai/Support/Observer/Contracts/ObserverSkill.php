<?php

namespace App\Ai\Support\Observer\Contracts;

use App\Ai\Support\Observer\ObserverResult;

interface ObserverSkill
{
    public function observe(): ?ObserverResult;
}
