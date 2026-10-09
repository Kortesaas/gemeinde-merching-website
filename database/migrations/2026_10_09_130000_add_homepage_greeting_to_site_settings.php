<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Optional greeting (e.g. by the mayor) on the homepage, managed in site settings.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->text('greeting_text')->nullable()->after('homepage_media_id');
            $table->string('greeting_name')->nullable()->after('greeting_text');
            $table->string('greeting_role')->nullable()->after('greeting_name');
            // The link disappears when its page is permanently deleted.
            $table->foreignId('greeting_page_id')->nullable()->after('greeting_role')->constrained('pages')->nullOnDelete();
            $table->foreignId('greeting_media_id')->nullable()->after('greeting_page_id')->constrained('media')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('greeting_media_id');
            $table->dropConstrainedForeignId('greeting_page_id');
            $table->dropColumn(['greeting_text', 'greeting_name', 'greeting_role']);
        });
    }
};
