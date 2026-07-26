<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Profile;
use Behat\Config\Suite;
use Tests\Behaviour\Contexts\FeatureContext;

$behaviourSuite = (new Suite('third_party'))
    ->withPaths('%paths.base%/tests/Behaviour/Features')
    ->withContexts(FeatureContext::class);

return (new Config)
    ->withProfile(
        (new Profile('default'))
            ->withSuite($behaviourSuite),
    );
