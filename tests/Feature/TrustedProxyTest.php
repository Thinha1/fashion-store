<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    private const HTTPS_PROXY_HEADERS = [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'shop.example.com',
        'X-Forwarded-Port' => '443',
    ];

    public function test_redirects_keep_the_https_host_of_a_trusted_proxy(): void
    {
        config(['trustedproxy.proxies' => '172.16.0.0/12']);

        $this->withServerVariables(['REMOTE_ADDR' => '172.27.0.1'])
            ->get(route('orders.index'), self::HTTPS_PROXY_HEADERS)
            ->assertRedirect('https://shop.example.com/dang-nhap');
    }

    public function test_forwarded_headers_are_ignored_when_no_proxy_is_trusted(): void
    {
        config(['trustedproxy.proxies' => null]);

        $this->withServerVariables(['REMOTE_ADDR' => '172.27.0.1'])
            ->get(route('orders.index'), self::HTTPS_PROXY_HEADERS)
            ->assertRedirect(route('login'));
    }
}
