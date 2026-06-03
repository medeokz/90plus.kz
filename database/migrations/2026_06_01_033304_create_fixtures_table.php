<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixtures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique();
            $table->unsignedBigInteger('api_fixture_id')->nullable()->index();
            $table->string('competition')->nullable();
            $table->string('home_team');
            $table->string('away_team');
            $table->string('home_team_flag')->nullable();
            $table->string('away_team_flag')->nullable();
            $table->unsignedTinyInteger('home_score')->default(0);
            $table->unsignedTinyInteger('away_score')->default(0);
            $table->string('status', 16)->default('NS');
            $table->unsignedTinyInteger('minute')->nullable();
            $table->timestamp('kickoff_at')->nullable();
            $table->string('venue')->nullable();
            $table->string('city')->nullable();
            $table->string('weather')->nullable();
            $table->string('temperature')->nullable();
            $table->string('broadcast')->nullable();
            $table->json('referees')->nullable();
            $table->json('events')->nullable();
            $table->json('lineups')->nullable();
            $table->json('statistics')->nullable();
            $table->json('team_form')->nullable();
            $table->timestamps();

            $table->index(['status', 'kickoff_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixtures');
    }
};
