<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('project_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete();

            // Human-readable invoice number: INV-2026-001
            $table->string('number')->unique();

            $table->string('status')->default('draft');
            // draft | sent | paid | overdue | cancelled

            $table->text('notes')->nullable();

            // Line items stored as JSON array
            // [{"description": "Web Design", "quantity": 1, "rate": 50000, "amount": 50000}]
            $table->json('line_items');

            // Calculated totals stored separately for performance
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);   // percentage e.g. 18.00
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            $table->date('issued_at')->nullable();
            $table->date('due_at')->nullable();
            $table->date('paid_at')->nullable();

            // Path to the generated PDF on disk
            $table->string('pdf_path')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};