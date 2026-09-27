<?php

namespace Database\Seeders;

use App\Models\AllocatedPort;
use App\Models\Server;
use App\Models\User;
use App\Models\VpnAccount;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@vpnstore.com'],
            [
                'name' => 'Administrator',
                'password' => bcrypt('password123'),
                'role' => 'admin',
                'balance' => 500000.00,
            ]
        );

        $node1 = Server::firstOrCreate(
            ['ip_address' => '103.145.226.50'],
            [
                'name' => 'SG-Gaming-Node-01',
                'domain' => 'sg1.vpnstore.my.id',
                'country_code' => 'SG',
                'location' => 'Singapore (Equinix)',
                'status' => 'online',
                'agent_token' => 'demo_agent_token_sg01_secret123',
                'port_range_start' => 20000,
                'port_range_end' => 35000,
                'vpn_subnet' => '10.8.0.0/24',
                'vpn_server_ip' => '10.8.0.1',
                'wireguard_port' => 51820,
                'wireguard_public_key' => 'uK3qJmF/G1k9Yy4x8D0vL5rT7wN2pQ6sE9aZ1cB3dF8=',
                'wireguard_private_key' => 'wK3qJmF/G1k9Yy4x8D0vL5rT7wN2pQ6sE9aZ1cB3dF8=',
                'cpu_usage' => 8.5,
                'memory_usage' => 32.4,
                'disk_usage' => 21.0,
                'active_peers' => 1,
                'is_active' => true,
                'last_heartbeat_at' => now(),
            ]
        );

        $node2 = Server::firstOrCreate(
            ['ip_address' => '103.84.207.12'],
            [
                'name' => 'ID-Jakarta-Node-01',
                'domain' => 'id1.vpnstore.my.id',
                'country_code' => 'ID',
                'location' => 'Jakarta (Cyber Data Center)',
                'status' => 'online',
                'agent_token' => 'demo_agent_token_id01_secret456',
                'port_range_start' => 20000,
                'port_range_end' => 35000,
                'vpn_subnet' => '10.9.0.0/24',
                'vpn_server_ip' => '10.9.0.1',
                'wireguard_port' => 51820,
                'wireguard_public_key' => 'zP8vN3mQ4xL7rT2wY9aZ1cB3dF8uK3qJmF/G1k9Yy4x=',
                'wireguard_private_key' => 'xP8vN3mQ4xL7rT2wY9aZ1cB3dF8uK3qJmF/G1k9Yy4x=',
                'cpu_usage' => 12.0,
                'memory_usage' => 45.1,
                'disk_usage' => 34.0,
                'active_peers' => 0,
                'is_active' => true,
                'last_heartbeat_at' => now(),
            ]
        );

        // Contoh akun VPN Pterodactyl Minecraft
        $account = VpnAccount::firstOrCreate(
            ['server_id' => $node1->id, 'username' => 'pterodactyl-mc-smp'],
            [
                'user_id' => $admin->id,
                'protocol' => 'wireguard',
                'public_key' => 'aB3cD4eF5gH6iJ7kL8mN9oP0qR1sT2uV3wX4yZ5aB6c=',
                'private_key' => 'wK3qJmF/G1k9Yy4x8D0vL5rT7wN2pQ6sE9aZ1cB3dF8=',
                'preshared_key' => 'pSh9Yy4x8D0vL5rT7wN2pQ6sE9aZ1cB3dF8uK3qJmF0=',
                'allocated_ip' => '10.8.0.2',
                'status' => 'active',
                'expires_at' => now()->addDays(30),
            ]
        );

        // Contoh alokasi port publik untuk Minecraft Server
        AllocatedPort::firstOrCreate(
            ['server_id' => $node1->id, 'public_port' => 25565],
            [
                'vpn_account_id' => $account->id,
                'target_port' => 25565,
                'protocol' => 'both',
                'label' => 'Minecraft SMP Server',
                'status' => 'active',
            ]
        );
    }
}
