<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('original_name');   // "Project Brief.pdf"
            $table->string('stored_name');     // "attachments/abc123.pdf"
            $table->string('mime_type');       // "application/pdf"
            $table->unsignedBigInteger('size'); // bytes
            $table->string('disk')->default('local'); // local, s3, etc.

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};