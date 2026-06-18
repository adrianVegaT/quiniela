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
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiniela_id')->constrained('quinielas')->cascadeOnDelete();
            $table->string('name');
            $table->string('flag_url')->nullable();
            $table->timestamps();

            $table->unique(['quiniela_id', 'name']);
        });

        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiniela_id')->constrained('quinielas')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['quiniela_id', 'name']);
        });

        Schema::create('team_group', function (Blueprint $table) {
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();

            $table->primary(['team_id', 'group_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_group');
        Schema::dropIfExists('groups');
        Schema::dropIfExists('teams');
    }
};
