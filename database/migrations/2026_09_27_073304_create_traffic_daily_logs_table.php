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
        Schema::create('traffic_daily_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vpn_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->date('log_date')->index();
            $table->unsignedBigInteger('bytes_rx')->default(0);
            $table->unsignedBigInteger('bytes_tx')->default(0);
            $table->timestamps();

            $table->unique(['vpn_account_id', 'log_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('traffic_daily_logs');
    }
};
