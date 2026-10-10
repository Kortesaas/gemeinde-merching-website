<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_plans', function (Blueprint $table) {
            $table->dropUnique(['year']);
            $table->index('year');
            $table->string('topic', 180)->default('Haushaltsplan')->after('year');
        });
        Schema::table('budget_publications', function (Blueprint $table) {
            // Historical receipts retain their actual recorded facts; do not invent a topic.
            $table->string('topic', 180)->nullable()->after('year');
        });
    }

    public function down(): void
    {
        Schema::table('budget_publications', fn (Blueprint $table) => $table->dropColumn('topic'));
        Schema::table('budget_plans', function (Blueprint $table) {
            $table->dropColumn('topic');
            $table->dropIndex(['year']);
            $table->unique('year');
        });
    }
};
