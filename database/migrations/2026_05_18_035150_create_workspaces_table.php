<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('owner_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('plan')->default('free'); // free | pro | agency
            $table->json('settings')->nullable();    // workspace-level config
            $table->timestamps();
        });

        // Pivot: many users belong to many workspaces
        Schema::create('user_workspace', function (Blueprint $table) {
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member'); // owner | admin | member
            $table->timestamps();
            $table->primary(['workspace_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_workspace');
        Schema::dropIfExists('workspaces');
    }
};