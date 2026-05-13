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
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('stripe_payment_intent_id')->nullable()->after('pdf_path');
            $table->string('stripe_payment_status')->nullable()->after('stripe_payment_intent_id');
            // requires_payment_method | requires_confirmation | processing | succeeded | canceled
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('stripe_payment_intent_id');
            $table->dropColumn('stripe_payment_status');
        });
    }
};
