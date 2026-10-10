<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('council_members', fn (Blueprint $table) => $table->foreignId('portrait_id')->nullable()->constrained('media')->restrictOnDelete());
        Schema::create('budget_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('show_components')->default(false);
            $table->string('accessibility_status', 30)->default('not_checked');
            $table->text('accessibility_notes')->nullable();
            $table->string('generation_status', 20)->default('stale');
            $table->text('generation_error')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('seo_noindex')->default(false);
            $table->string('status', 20)->default('draft');
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
        // Immutable private files. Removing a component only changes its association.
        Schema::create('budget_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_plan_id')->constrained()->restrictOnDelete();
            $table->string('file_path')->unique();
            $table->string('original_filename');
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->unsignedInteger('page_count')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('budget_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_source_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unique(['budget_plan_id', 'budget_source_id']);
            $table->timestamps();
        });
        Schema::create('budget_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_plan_id')->constrained()->restrictOnDelete();
            $table->char('fingerprint', 64);
            $table->string('file_path')->unique();
            $table->string('original_filename');
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->unsignedInteger('page_count');
            $table->json('sources');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::table('budget_plans', fn (Blueprint $table) => $table->foreignId('current_generation_id')->nullable()->constrained('budget_generations')->restrictOnDelete());
        Schema::create('budget_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('budget_generation_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('title');
            $table->string('status', 20);
            $table->timestamp('publish_at');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('publisher_name');
            $table->text('public_url');
            $table->string('accessibility_status', 30);
            $table->text('accessibility_notes')->nullable();
            $table->boolean('show_components');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_publications');
        Schema::table('budget_plans', fn (Blueprint $table) => $table->dropConstrainedForeignId('current_generation_id'));
        Schema::dropIfExists('budget_generations');
        Schema::dropIfExists('budget_components');
        Schema::dropIfExists('budget_sources');
        Schema::dropIfExists('budget_plans');
        Schema::table('council_members', fn (Blueprint $table) => $table->dropConstrainedForeignId('portrait_id'));
    }
};
