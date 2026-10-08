<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Employee accounts for the backend. There is no public registration.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Stored lower-case; used as login name.
            $table->string('email')->unique();
            $table->string('password');
            // Deactivated accounts cannot log in or reset their password.
            $table->boolean('is_active')->default(true)->index();

            // TOTP two-factor authentication. The secret is encrypted with the
            // application key (Eloquent "encrypted" cast); never logged.
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            // Last accepted TOTP time step – prevents replaying the same code.
            $table->unsignedBigInteger('two_factor_last_used_timestep')->nullable();

            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            // Hashed by Laravel's password broker; plain tokens are never stored.
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Server-side sessions (database driver). Intentionally without the
        // ip_address / user_agent columns of Laravel's default schema
        // (privacy by default, see App\Session\PrivacyDatabaseSessionHandler).
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
