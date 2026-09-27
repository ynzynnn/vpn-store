<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ip_address')->unique();
            $table->string('domain')->nullable();
            $table->string('country_code', 2)->default('SG');
            $table->string('location')->nullable();
            $table->enum('status', ['online', 'offline', 'maintenance'])->default('offline');

            // Agent API auth
            $table->string('agent_token', 128)->unique();
            $table->timestamp('last_heartbeat_at')->nullable();

            // Port forwarding pool
            $table->unsignedInteger('port_range_start')->default(20000);
            $table->unsignedInteger('port_range_end')->default(35000);

            // WireGuard configuration
            $table->string('vpn_subnet')->default('10.8.0.0/24');
            $table->string('vpn_server_ip')->default('10.8.0.1');
            $table->unsignedSmallInteger('wireguard_port')->default(51820);
            $table->text('wireguard_public_key')->nullable();
            $table->text('wireguard_private_key')->nullable();

            // Telemetry from Agent
            $table->float('cpu_usage')->nullable();
            $table->float('memory_usage')->nullable();
            $table->float('disk_usage')->nullable();
            $table->unsignedInteger('active_peers')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
