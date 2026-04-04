<?php

namespace Tests\Unit\Security;

use App\Support\Security\UrlTrustPolicy;
use Tests\TestCase;

class UrlTrustPolicyTest extends TestCase
{
    public function test_it_allows_local_http_urls_in_testing_environment(): void
    {
        $policy = app(UrlTrustPolicy::class);

        $result = $policy->authorize('http://localhost:8080/mcp');

        $this->assertTrue($result['allowed']);
    }

    public function test_it_allows_secure_websocket_urls(): void
    {
        $policy = app(UrlTrustPolicy::class);

        $result = $policy->authorize('wss://tools.example.com/socket', [
            'allow_http' => false,
            'allow_private_network' => false,
        ]);

        $this->assertTrue($result['allowed']);
    }

    public function test_it_rejects_plain_http_when_policy_disallows_it(): void
    {
        $policy = app(UrlTrustPolicy::class);

        $result = $policy->authorize('http://example.com/mcp', [
            'allow_http' => false,
            'allow_private_network' => false,
        ]);

        $this->assertFalse($result['allowed']);
        $this->assertSame('Plain HTTP URLs are not allowed by policy.', $result['reason']);
    }

    public function test_it_rejects_private_hosts_when_policy_disallows_them(): void
    {
        $policy = app(UrlTrustPolicy::class);

        $result = $policy->authorize('https://127.0.0.1:9000/mcp', [
            'allow_http' => false,
            'allow_private_network' => false,
        ]);

        $this->assertFalse($result['allowed']);
        $this->assertSame('Private or local network hosts are not allowed by policy.', $result['reason']);
    }

    public function test_it_enforces_host_allowlists(): void
    {
        $policy = app(UrlTrustPolicy::class);

        $allowed = $policy->authorize('https://tools.example.com/mcp', [
            'allowed_hosts' => ['*.example.com'],
            'allow_http' => false,
            'allow_private_network' => false,
        ]);
        $denied = $policy->authorize('https://tools.other.com/mcp', [
            'allowed_hosts' => ['*.example.com'],
            'allow_http' => false,
            'allow_private_network' => false,
        ]);

        $this->assertTrue($allowed['allowed']);
        $this->assertFalse($denied['allowed']);
        $this->assertSame('URL host is not allowlisted.', $denied['reason']);
    }
}
