<?php

namespace App\Foundation;

use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application as BaseApplication;
use Illuminate\Foundation\Mix;
use Illuminate\Foundation\PackageManifest;

class Application extends BaseApplication
{
    protected function registerBaseBindings(): void
    {
        static::setInstance($this);

        $this->instance('app', $this);

        $this->instance(Container::class, $this);
        $this->singleton(Mix::class);

        $this->singleton(PackageManifest::class, fn (): ModulePackageManifest => new ModulePackageManifest(
            new Filesystem,
            $this->basePath(),
            $this->getCachedPackagesPath(),
        ));
    }
}
