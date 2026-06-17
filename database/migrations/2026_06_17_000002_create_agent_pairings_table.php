<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * QR pairing handshakes. A GM's Bridge registers a one-time nonce bound to
     * a Foundry user; the player's Agent claims it, which binds their agent_link
     * to that Foundry user and broadcasts the result back to the GM's Bridge.
     */
    public function up(): void
    {
        Schema::create('agent_pairings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nonce')->unique();
            $table->string('channel_id')->index();
            $table->string('foundry_user_id');
            $table->string('foundry_user_name')->nullable();
            $table->string('foundry_url')->nullable();
            $table->foreignId('claimed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('agent_link')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_pairings');
    }
};
