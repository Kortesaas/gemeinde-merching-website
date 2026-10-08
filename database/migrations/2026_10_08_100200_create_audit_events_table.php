<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only log of security-relevant and administrative actions.
        // Deliberately stores NO IP addresses, user agents or request payloads –
        // only who did what to which record, when, plus safe metadata.
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            // Acting user; kept as NULL if the account is later deleted.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100);
            // Affected record (polymorphic, uses the morph map aliases).
            $table->nullableMorphs('subject');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
