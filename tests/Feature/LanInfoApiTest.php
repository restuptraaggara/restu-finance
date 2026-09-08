<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanInfoApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Restu Dev',
            'email' => 'restu@dev.local',
            'password' => bcrypt('password123'),
            'theme' => 'dark',
            'currency' => 'IDR',
            'language' => 'id',
        ]);
    }

    /**
     * 1. Unauthenticated request to /api/lan-info is rejected (401).
     */
    public function test_unauthenticated_request_to_lan_info_is_rejected(): void
    {
        $response = $this->getJson('/api/lan-info');
        $response->assertStatus(401)
                 ->assertJson([
                     'success' => false,
                 ]);
    }

    /**
     * 2. Authenticated user can get LAN info.
     */
    public function test_authenticated_user_can_get_lan_info(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/lan-info');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'LAN info retrieved successfully',
                 ])
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'available',
                         'ip',
                         'port',
                         'scheme',
                         'share_url',
                         'all_ips',
                         'message',
                     ],
                 ]);
    }

    /**
     * 3. /api/init includes LAN share information.
     */
    public function test_init_api_includes_lan_info(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/init');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'user',
                         'wallets',
                         'categories',
                         'transactions',
                         'budgets',
                         'goals',
                         'recurring_transactions',
                         'lan' => [
                             'available',
                             'ip',
                             'port',
                             'scheme',
                             'share_url',
                             'all_ips',
                         ],
                         'summary',
                     ],
                 ]);
    }

    /**
     * 4. LanService correctly validates private vs non-private IPv4 addresses.
     */
    public function test_lan_service_private_ipv4_validation(): void
    {
        $service = new LanService();

        // Valid Private IPv4
        $this->assertTrue($service->isPrivateIPv4('192.168.1.1'));
        $this->assertTrue($service->isPrivateIPv4('192.168.110.73'));
        $this->assertTrue($service->isPrivateIPv4('10.0.0.1'));
        $this->assertTrue($service->isPrivateIPv4('10.255.255.254'));
        $this->assertTrue($service->isPrivateIPv4('172.16.0.1'));
        $this->assertTrue($service->isPrivateIPv4('172.31.255.255'));

        // Invalid or Non-private (Loopback, APIPA, Public, IPv6)
        $this->assertFalse($service->isPrivateIPv4('127.0.0.1'));
        $this->assertFalse($service->isPrivateIPv4('169.254.1.1'));
        $this->assertFalse($service->isPrivateIPv4('0.0.0.0'));
        $this->assertFalse($service->isPrivateIPv4('8.8.8.8'));
        $this->assertFalse($service->isPrivateIPv4('1.1.1.1'));
        $this->assertFalse($service->isPrivateIPv4('::1'));
        $this->assertFalse($service->isPrivateIPv4('not-an-ip'));
    }

    /**
     * 5. LanService computes share URL correctly.
     */
    public function test_lan_service_computes_share_url(): void
    {
        $service = new LanService();
        $lanInfo = $service->getLanInfo();

        $this->assertIsArray($lanInfo);
        $this->assertArrayHasKey('available', $lanInfo);
        $this->assertArrayHasKey('port', $lanInfo);
        $this->assertArrayHasKey('scheme', $lanInfo);

        if ($lanInfo['available']) {
            $this->assertStringStartsWith($lanInfo['scheme'] . '://', $lanInfo['share_url']);
            $this->assertStringContainsString($lanInfo['ip'], $lanInfo['share_url']);
        }
    }
}
