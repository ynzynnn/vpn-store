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
        Schema::create('vpn_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->enum('protocol', ['wireguard', 'openvpn', 'ssh'])->default('wireguard');

            // WireGuard & credentials
            $table->string('username', 64)->index();
            $table->text('password')->nullable();
            $table->text('public_key')->nullable()->index();
            $table->text('private_key')->nullable();
            $table->text('preshared_key')->nullable();
            $table->string('allocated_ip')->index(); // e.g. 10.8.0.2

            // Status & Quota
            $table->enum('status', ['active', 'expired', 'suspended'])->default('active');
            $table->unsignedBigInteger('data_limit_bytes')->nullable();
            $table->unsignedBigInteger('data_used_bytes')->default(0);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('last_connected_at')->nullable();

            $table->timestamps();
            $table->unique(['server_id', 'username']);
            $table->unique(['server_id', 'allocated_ip']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vpn_accounts');
    }
};
