<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('file_path');
            $t->string('original_filename');
            $t->string('mime_type', 150);
            $t->string('extension', 10);
            $t->unsignedBigInteger('size_bytes');
            $t->char('sha256', 64);
            $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable();
            $t->text('alt_text')->nullable();
            $t->boolean('is_decorative')->default(false);
            $t->text('caption')->nullable();
            $t->string('copyright')->nullable();
            $t->string('creator')->nullable();
            $t->string('language', 10)->default('de');
            $t->string('status', 20)->default('draft');
            $t->timestamp('publish_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('archived_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['status', 'publish_at', 'expires_at']);
        });
        foreach (['articles', 'events', 'public_notices', 'services', 'life_situations', 'pages'] as $table) {
            $owner = ['articles' => 'article', 'events' => 'event', 'public_notices' => 'public_notice', 'services' => 'service', 'life_situations' => 'life_situation', 'pages' => 'page'][$table];
            Schema::create($owner.'_media', function (Blueprint $t) use ($table, $owner) {
                $t->id();
                $t->foreignId($owner.'_id')->constrained($table)->cascadeOnDelete();
                $t->foreignId('media_id')->constrained('media')->restrictOnDelete();
                $t->unsignedSmallInteger('sort_order')->default(0);
                $t->timestamps();
                $t->unique([$owner.'_id', 'media_id']);
            });
        }
        Schema::create('search_entries', function (Blueprint $t) {
            $t->id();
            $t->string('content_type', 50);
            $t->unsignedBigInteger('content_id');
            $t->string('title');
            $t->text('summary');
            $t->text('keywords');
            $t->longText('body');
            $t->timestamps();
            $t->unique(['content_type', 'content_id']);
            $t->fullText(['title', 'summary', 'keywords', 'body']);
        });
        Schema::create('search_synonyms', function (Blueprint $t) {
            $t->id();
            $t->string('phrase', 100)->unique();
            $t->string('alternatives', 1000);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('search_statistics', function (Blueprint $t) {
            $t->id();
            $t->string('phrase', 150);
            $t->unsignedInteger('result_count');
            $t->boolean('result_clicked')->nullable();
            $t->timestamp('created_at')->index();
        });
        Schema::create('site_settings', function (Blueprint $t) {
            $t->unsignedTinyInteger('id')->primary();
            $t->string('municipality_name');
            $t->foreignId('town_hall_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $t->foreignId('central_department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $t->foreignId('central_contact_route_id')->nullable()->constrained('contact_routes')->restrictOnDelete();
            $t->foreignId('works_department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $t->foreignId('recycling_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $t->text('postal_address')->nullable();
            $t->text('legal_contact')->nullable();
            $t->string('default_seo_title')->nullable();
            $t->string('default_meta_description', 500)->nullable();
            $t->timestamps();
        });
        Schema::table('navigation_items', fn (Blueprint $t) => $t->foreignId('external_resource_id')->nullable()->constrained('external_resources')->restrictOnDelete());
        foreach (['pages', 'articles', 'services', 'life_situations', 'events', 'public_notices', 'documents', 'departments', 'organizations', 'locations'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('seo_title')->nullable();
                $t->string('meta_description', 500)->nullable();
                $t->boolean('seo_noindex')->default(false);
            });
        }
    }

    public function down(): void
    {
        foreach (['pages', 'articles', 'services', 'life_situations', 'events', 'public_notices', 'documents', 'departments', 'organizations', 'locations'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['seo_title', 'meta_description', 'seo_noindex']));
        }
        Schema::table('navigation_items', fn (Blueprint $t) => $t->dropConstrainedForeignId('external_resource_id'));
        foreach (['site_settings', 'search_statistics', 'search_synonyms', 'search_entries', 'article_media', 'event_media', 'public_notice_media', 'service_media', 'life_situation_media', 'page_media', 'media'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
