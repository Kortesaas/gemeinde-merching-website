<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Change proposals for content that is (or was) public. The live
        // record stays untouched until a publisher applies the proposal.
        // Snapshots use the revision format (allowlisted editorial data only).
        Schema::create('content_proposals', function (Blueprint $table) {
            $table->id();
            // Polymorphic like revisions: one review workflow for all publishable types.
            $table->string('proposable_type', 50);
            $table->unsignedBigInteger('proposable_id');
            $table->string('status', 20)->default('draft');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('summary')->nullable();
            $table->text('review_comment')->nullable();
            // Live state the proposal is based on (for change detection and conflicts).
            $table->unsignedInteger('base_revision_number')->nullable();
            $table->json('base_snapshot');
            $table->json('payload');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('applied_revision_number')->nullable();
            $table->timestamps();

            $table->index(['proposable_type', 'proposable_id', 'status'], 'content_proposals_record_status_idx');
            $table->index(['status', 'submitted_at']);
            $table->index(['author_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_proposals');
    }
};
