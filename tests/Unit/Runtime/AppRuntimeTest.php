<?php

namespace Tests\Unit\Runtime;

use App\Support\Runtime\AppRuntime;
use PHPUnit\Framework\TestCase;

class AppRuntimeTest extends TestCase
{
    public function test_it_defaults_to_server_when_no_native_signal_is_present(): void
    {
        $runtime = new class extends AppRuntime
        {
            public function nativeSignal(): bool
            {
                return false;
            }
        };

        $this->assertSame('server', $runtime->host());
        $this->assertFalse($runtime->isNative());
    }

    public function test_it_uses_registered_host_detectors(): void
    {
        $runtime = new class extends AppRuntime
        {
            public function nativeSignal(): bool
            {
                return true;
            }
        };

        $runtime->claimNativeHost('desktop');
        $runtime->registerRootView('desktop', 'desktop-native::app');

        $this->assertSame('desktop', $runtime->host());
        $this->assertTrue($runtime->isNative());
        $this->assertSame('desktop-native::app', $runtime->rootView('web-view::app'));
    }

    public function test_it_reports_generic_native_when_no_extension_has_claimed_the_host(): void
    {
        $runtime = new class extends AppRuntime
        {
            public function nativeSignal(): bool
            {
                return true;
            }
        };

        $this->assertSame('native', $runtime->host());
        $this->assertTrue($runtime->isNative());
    }
}
