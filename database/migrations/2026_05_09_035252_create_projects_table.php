<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            // Foreign key — every project belongs to one client
            $table->foreignId('client_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            $table->string('status')->default('draft');
            // Possible values: draft, active, on_hold, completed, cancelled

            $table->decimal('budget', 10, 2)->nullable();
            // decimal(10,2) stores up to 99,999,999.99

            $table->date('deadline')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropForeignKeys('projects');
        Schema::dropIfExists('projects');
    }
};