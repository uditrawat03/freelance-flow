<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();                                    // bigint, primary key, auto-increment
            $table->string('name');                          // varchar(255), required
            $table->string('email')->unique();               // unique index on email
            $table->string('phone')->nullable();             // optional
            $table->string('company')->nullable();           // optional
            $table->text('notes')->nullable();               // long text, optional
            $table->string('status')->default('active');     // defaults to 'active'
            $table->timestamps();                            // created_at + updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
