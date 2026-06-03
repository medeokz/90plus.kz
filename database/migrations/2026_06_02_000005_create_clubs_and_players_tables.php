<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clubs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_club_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_url')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('source_url')->nullable();
            $table->timestamps();
        });

        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_player_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('photo_url')->nullable();
            $table->string('nationality')->nullable();
            $table->integer('age')->nullable();
            $table->string('source_url')->nullable();
            $table->timestamps();
        });

        Schema::create('club_player', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('position')->nullable();
            $table->string('number')->nullable();
            $table->integer('age')->nullable();
            $table->string('nationality')->nullable();
            $table->string('season')->nullable();
            $table->timestamp('parsed_at')->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_player');
        Schema::dropIfExists('players');
        Schema::dropIfExists('clubs');
    }
};

