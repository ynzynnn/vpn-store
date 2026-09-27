<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use App\Models\VpnAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PterodactylVpnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_landing_page_loads_successfully(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('VPN Port Store');
        $response->assertSee('Pterodactyl');
    }

    public function test_guest_is_redirected_from_dashboard_to_login(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_user_can_view_login_page(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Masuk ke Akun');
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@vpnstore.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@vpnstore.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Budi Gamer',
            'email' => 'budi@example.com',
            'password' => 'rahasia1234',
            'password_confirmation' => 'rahasia1234',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'budi@example.com']);
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $user = User::where('role', 'admin')->first();

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Port Pterodactyl Tersewa');
    }

    public function test_authenticated_admin_can_create_server_node(): void
    {
        $admin = User::where('role', 'admin')->first();

        $response = $this->actingAs($admin)->post('/dashboard/servers', [
            'name' => 'SG-Test-Node',
            'ip_address' => '103.111.222.33',
            'country_code' => 'SG',
            'location' => 'Singapore',
            'port_range_start' => 20000,
            'port_range_end' => 30000,
            'wireguard_port' => 51820,
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertDatabaseHas('servers', [
            'ip_address' => '103.111.222.33',
            'name' => 'SG-Test-Node',
        ]);
    }

    public function test_authenticated_user_can_create_vpn_account_and_allocate_port(): void
    {
        $admin = User::where('role', 'admin')->first();
        $server = Server::first();

        $response = $this->actingAs($admin)->post('/dashboard/accounts', [
            'server_id' => $server->id,
            'username' => 'test-mc-server-'.time(),
            'public_port' => 25570,
            'target_port' => 25570,
            'protocol' => 'both',
            'days_active' => 30,
            'label' => 'Minecraft Test Server',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertDatabaseHas('allocated_ports', [
            'server_id' => $server->id,
            'public_port' => 25570,
            'protocol' => 'both',
        ]);
    }

    public function test_user_can_download_wireguard_config(): void
    {
        $admin = User::where('role', 'admin')->first();
        $account = VpnAccount::first();

        $response = $this->actingAs($admin)->get("/dashboard/accounts/{$account->id}/download");
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/plain; charset=UTF-8');
        $this->assertStringContainsString('[Interface]', $response->streamedContent());
        $this->assertStringContainsString('[Peer]', $response->streamedContent());
    }

    public function test_user_can_logout(): void
    {
        $user = User::first();

        $response = $this->actingAs($user)->post('/logout');
        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_agent_heartbeat_syncs_peers_and_ports(): void
    {
        $server = Server::first();

        $response = $this->withHeader('X-Agent-Token', $server->agent_token)
            ->postJson('/api/v1/agent/heartbeat', [
                'cpu_usage' => 15.2,
                'memory_usage' => 45.0,
                'disk_usage' => 22.0,
                'active_peers' => 1,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'message',
            'sync' => [
                'server_id',
                'peers_to_add',
                'ports_to_forward',
            ],
        ]);
    }
}
