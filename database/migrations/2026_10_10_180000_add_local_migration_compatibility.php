<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_urls', function (Blueprint $table) {
            $table->id();
            $table->text('url');
            $table->char('url_hash', 64)->unique();
            $table->string('target_type', 50);
            $table->unsignedBigInteger('target_id');
            $table->string('destination')->nullable();
            $table->timestamps();
        });
        Schema::table('budget_publications', function (Blueprint $table) {
            $table->unsignedBigInteger('budget_generation_id')->nullable()->change();
            $table->boolean('source_only')->default(false);
            $table->json('source_manifest')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('budget_publications')->whereNull('budget_generation_id')->exists()) {
            throw new LogicException('Source-only budget receipts must be retained.');
        }
        Schema::dropIfExists('legacy_urls');
        Schema::table('budget_publications', function (Blueprint $table) {
            $table->dropColumn(['source_only', 'source_manifest']);
            $table->unsignedBigInteger('budget_generation_id')->nullable(false)->change();
        });
    }
};
