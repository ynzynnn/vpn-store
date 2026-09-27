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
        Schema::create('allocated_ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vpn_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('public_port'); // e.g. 25565
            $table->unsignedInteger('target_port'); // e.g. 25565
            $table->enum('protocol', ['both', 'tcp', 'udp'])->default('both');
            $table->string('label')->nullable();    // e.g. "Pterodactyl Minecraft"
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['server_id', 'public_port', 'protocol']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('allocated_ports');
    }
};
