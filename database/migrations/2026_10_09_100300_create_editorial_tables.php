<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editorial content and its explicit relationship tables.
 *
 * Foreign-key rule: the owning side cascades (permanently deleting an article
 * removes its placements), the referenced reusable record restricts
 * (a document, link, person or department cannot be destroyed while used).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $this->publication($table);
            $this->editorial($table);
        });
        $this->contacts('page_person', 'page_id', 'pages');
        $this->placements('pages', 'page_id', 'page');

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            // Sort key for the A–Z list (e.g. "Personalausweis" for "Antrag auf …").
            $table->string('sort_title')->nullable()->index();
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('online_service_resource_id')->nullable()->constrained('external_resources')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $this->publication($table);
            $this->editorial($table);
        });
        Schema::create('service_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('alias');
            $table->timestamps();
            $table->unique(['service_id', 'alias']);
            $table->index('alias');
        });
        Schema::create('department_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['service_id', 'department_id']);
        });
        $this->contacts('person_service', 'service_id', 'services');
        Schema::create('related_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_service_id')->constrained('services')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['service_id', 'related_service_id']);
        });
        $this->placements('services', 'service_id', 'service');

        Schema::create('life_situations', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $this->publication($table);
            $this->editorial($table);
        });
        Schema::create('life_situation_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('life_situation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['life_situation_id', 'service_id']);
        });
        $this->placements('life_situations', 'life_situation_id', 'life_situation');

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('author_name')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $this->publication($table);
            $this->editorial($table);
        });
        Schema::create('article_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['article_id', 'tag_id']);
        });
        $this->contacts('article_person', 'article_id', 'articles');
        $this->placements('articles', 'article_id', 'article');

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            // RFC 5545 RRULE (e.g. "FREQ=WEEKLY;BYDAY=TU"); expansion follows later.
            $table->string('recurrence_rule', 500)->nullable();
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('venue')->nullable();
            $table->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('organizer_name')->nullable();
            $table->foreignId('contact_person_id')->nullable()->constrained('people')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('url', 2048)->nullable();
            $table->string('registration_url', 2048)->nullable();
            // When set, expires_at follows the end of the event (no cron needed).
            $table->boolean('auto_archive')->default(false);
            $this->publication($table);
            $this->editorial($table);
            $table->index(['status', 'starts_at']);
        });
        $this->placements('events', 'event_id', 'event');

        Schema::create('public_notices', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            // Official publication date (may differ from the display window).
            $table->date('published_on')->nullable();
            $this->publication($table);
            $this->editorial($table);
        });
        $this->placements('public_notices', 'public_notice_id', 'public_notice', withResources: false);
        Schema::create('page_public_notice', function (Blueprint $table) {
            $table->id();
            $table->foreignId('public_notice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['public_notice_id', 'page_id']);
        });
        Schema::create('public_notice_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('public_notice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['public_notice_id', 'service_id']);
        });

        Schema::create('site_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('severity', 20)->default('info');
            $table->string('link_url', 2048)->nullable();
            $table->string('link_label')->nullable();
            $this->publication($table);
            $this->editorial($table);
        });
    }

    public function down(): void
    {
        foreach ([
            'site_alerts', 'public_notice_service', 'page_public_notice', 'document_public_notice', 'public_notices',
            'event_external_resource', 'document_event', 'events',
            'article_external_resource', 'article_document', 'article_person', 'article_tag', 'articles',
            'external_resource_life_situation', 'document_life_situation', 'life_situation_service', 'life_situations',
            'external_resource_service', 'document_service', 'related_services', 'person_service', 'department_service',
            'service_aliases', 'services',
            'external_resource_page', 'document_page', 'page_person', 'pages',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function publication(Blueprint $table): void
    {
        $table->string('status', 20)->default('draft');
        $table->timestamp('publish_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamp('archived_at')->nullable();
        $table->index(['status', 'publish_at', 'expires_at']);
    }

    private function editorial(Blueprint $table): void
    {
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();
        $table->softDeletes();
    }

    /**
     * Contact people of an owner (owner cascades, person restricts).
     */
    private function contacts(string $table, string $ownerKey, string $ownerTable): void
    {
        Schema::create($table, function (Blueprint $t) use ($ownerKey, $ownerTable) {
            $t->id();
            $t->foreignId($ownerKey)->constrained($ownerTable)->cascadeOnDelete();
            $t->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
            $t->unique([$ownerKey, 'person_id']);
        });
    }

    /**
     * Placement tables for documents and external resources of an owner type
     * (Laravel's default pivot names, e.g. "document_page").
     */
    private function placements(string $ownerTable, string $ownerKey, string $ownerSingular, bool $withResources = true): void
    {
        $items = ['document' => 'documents'];
        if ($withResources) {
            $items['external_resource'] = 'external_resources';
        }

        foreach ($items as $itemSingular => $itemTable) {
            $names = [$ownerSingular, $itemSingular];
            sort($names);
            $table = implode('_', $names);
            $itemKey = $itemSingular.'_id';

            Schema::create($table, function (Blueprint $t) use ($table, $ownerKey, $ownerTable, $itemKey, $itemTable) {
                $t->id();
                $t->foreignId($ownerKey)->constrained($ownerTable)->cascadeOnDelete();
                $t->foreignId($itemKey)->constrained($itemTable)->restrictOnDelete();
                // Validated slot identifier (see the owner model's *Slots()).
                $t->string('slot', 40);
                // Optional heading within the slot, e.g. "2026".
                $t->string('group_label', 120)->nullable();
                $t->unsignedSmallInteger('sort_order')->default(0);
                $t->timestamps();

                $t->unique([$ownerKey, $itemKey, 'slot'], substr($table, 0, 40).'_unique');
                $t->index([$ownerKey, 'slot', 'sort_order'], substr($table, 0, 40).'_slot_idx');
                $t->index($itemKey, substr($table, 0, 40).'_item_idx');
            });
        }
    }
};
