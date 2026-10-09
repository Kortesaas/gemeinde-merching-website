<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->decimal('focal_x', 5, 2)->nullable()->default(50);
            $table->decimal('focal_y', 5, 2)->nullable()->default(50);
        });
        Schema::table('services', function (Blueprint $table) {
            foreach (['prerequisites', 'required_items', 'processing_duration', 'important_notice'] as $column) {
                $table->text($column)->nullable();
            }
            $table->string('online_service_mode', 30)->default('not_specified');
        });
        Schema::table('events', function (Blueprint $table) {
            $table->string('operational_status', 20)->default('scheduled');
            $table->text('schedule_notice')->nullable();
        });
        Schema::table('locations', fn (Blueprint $table) => $table->text('accessibility_note')->nullable());

        foreach (['galleries', 'council_terms', 'council_members', 'committees'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                if ($name === 'council_terms') {
                    $table->date('starts_on')->nullable();
                    $table->date('ends_on')->nullable();
                    $table->boolean('is_historical')->default(false);
                }
                if ($name === 'committees') {
                    $table->foreignId('council_term_id')->constrained()->restrictOnDelete();
                    $table->unsignedSmallInteger('sort_order')->default(0);
                }
                $table->string('status', 20)->default('draft');
                $table->timestamp('publish_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                if (in_array($name, ['galleries', 'council_terms'], true)) {
                    $table->string('seo_title')->nullable();
                    $table->string('meta_description', 500)->nullable();
                    $table->boolean('seo_noindex')->default(false);
                }
                $table->timestamps();
                $table->softDeletes();
                $table->index(['status', 'publish_at', 'expires_at']);
            });
        }
        Schema::create('gallery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('caption')->nullable();
            $table->text('alt_override')->nullable();
            $table->string('alt_context')->nullable();
            $table->timestamps();
            $table->unique(['gallery_id', 'media_id']);
        });
        Schema::create('service_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->string('context')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('note')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        foreach (['council_memberships' => 'council_term_id', 'committee_memberships' => 'committee_id'] as $name => $owner) {
            Schema::create($name, function (Blueprint $table) use ($owner) {
                $table->id();
                $table->foreignId($owner)->constrained()->cascadeOnDelete();
                $table->foreignId('council_member_id')->constrained()->restrictOnDelete();
                $table->string('role');
                if ($owner === 'council_term_id') {
                    $table->string('grouping')->nullable();
                }
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique([$owner, 'council_member_id']);
            });
        }
        Schema::create('content_blocks', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');
            $table->string('type', 30);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('heading')->nullable();
            $table->unsignedTinyInteger('heading_level')->nullable();
            $table->text('text')->nullable();
            foreach (['media' => 'media', 'gallery' => 'galleries', 'document' => 'documents', 'person' => 'people', 'department' => 'departments', 'service' => 'services', 'event' => 'events', 'location' => 'locations', 'external_resource' => 'external_resources'] as $reference => $target) {
                $table->foreignId($reference.'_id')->nullable()->constrained($target)->restrictOnDelete();
            }
            $table->timestamps();
            $table->index(['owner_type', 'owner_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        foreach (['content_blocks', 'committee_memberships', 'council_memberships', 'service_fees', 'gallery_items', 'committees', 'council_members', 'council_terms', 'galleries'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('locations', fn (Blueprint $table) => $table->dropColumn('accessibility_note'));
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn(['operational_status', 'schedule_notice']));
        Schema::table('services', fn (Blueprint $table) => $table->dropColumn(['prerequisites', 'required_items', 'processing_duration', 'important_notice', 'online_service_mode']));
        Schema::table('media', fn (Blueprint $table) => $table->dropColumn(['focal_x', 'focal_y']));
    }
};
