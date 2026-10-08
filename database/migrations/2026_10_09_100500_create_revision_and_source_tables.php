<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Editorial history (separate from audit_events). Snapshot = JSON of the
        // model's allowlisted attributes and relations.
        Schema::create('content_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('revisionable_type', 50);
            $table->unsignedBigInteger('revisionable_id');
            $table->unsignedInteger('revision_number');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('summary')->nullable();
            $table->json('snapshot');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['revisionable_type', 'revisionable_id', 'revision_number'], 'content_revisions_number_unique');
            $table->index('created_at');
        });

        // Provenance of migrated content (e.g. WordPress), optional per record.
        Schema::create('source_references', function (Blueprint $table) {
            $table->id();
            $table->string('referenceable_type', 50);
            $table->unsignedBigInteger('referenceable_id');
            $table->string('source_system', 50);
            $table->string('source_id', 191);
            $table->string('original_url', 2048)->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['source_system', 'source_id', 'referenceable_type'], 'source_references_unique');
            $table->index(['referenceable_type', 'referenceable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_references');
        Schema::dropIfExists('content_revisions');
    }
};
