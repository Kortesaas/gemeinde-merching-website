<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['galleries', 'council_terms', 'council_members', 'committees', 'media', 'search_synonyms', 'site_settings', 'articles', 'events', 'public_notices', 'documents', 'external_resources', 'services', 'life_situations', 'pages', 'site_alerts', 'people', 'departments', 'locations', 'organizations', 'contact_routes', 'categories', 'tags', 'navigation_items', 'redirects'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->index('updated_at'));
        }
        Schema::table('documents', fn (Blueprint $table) => $table->index(['accessibility_status', 'updated_at']));
        Schema::table('media', fn (Blueprint $table) => $table->index(['is_decorative', 'updated_at']));
        Schema::table('events', fn (Blueprint $table) => $table->index(['status', 'operational_status', 'starts_at']));
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropIndex(['status', 'operational_status', 'starts_at']));
        Schema::table('media', fn (Blueprint $table) => $table->dropIndex(['is_decorative', 'updated_at']));
        Schema::table('documents', fn (Blueprint $table) => $table->dropIndex(['accessibility_status', 'updated_at']));
        foreach (self::TABLES as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropIndex(['updated_at']));
        }
    }
};
