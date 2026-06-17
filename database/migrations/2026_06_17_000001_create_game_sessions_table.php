<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique()->index(); // e.g. "NEON-7X2"
            $table->foreignId('gm_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('foundry_config')->nullable(); // cached Foundry Bridge settings from GM
            $table->unsignedInteger('player_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['gm_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_sessions');
    }
};
