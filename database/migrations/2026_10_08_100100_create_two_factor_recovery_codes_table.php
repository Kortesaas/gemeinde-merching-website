<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-use MFA recovery codes. Only a keyed hash (HMAC-SHA256 with the
        // application key) is stored; the plain codes are shown exactly once.
        Schema::create('two_factor_recovery_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('code_hash', 64)->unique();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_recovery_codes');
    }
};
