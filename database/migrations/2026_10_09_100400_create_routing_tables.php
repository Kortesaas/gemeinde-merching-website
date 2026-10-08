<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Public URL paths of content records, independent of IDs and navigation.
        // Paths use a binary collation: exact, accent- and case-sensitive storage;
        // path_key is the lower-case lookup key without trailing slash.
        Schema::create('public_routes', function (Blueprint $table) {
            $table->id();
            $table->string('path')->collation('utf8mb4_bin');
            $table->string('path_key')->collation('utf8mb4_bin')->unique();
            // Polymorphic by design: one URL space for many content types.
            $table->string('routable_type', 50);
            $table->unsignedBigInteger('routable_id');
            // Exactly one canonical route per record: "type:id" when canonical, else NULL.
            $table->boolean('is_canonical')->default(false);
            $table->string('canonical_for', 80)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['routable_type', 'routable_id']);
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('source_path')->collation('utf8mb4_bin');
            $table->string('source_key')->collation('utf8mb4_bin')->unique();
            // Internal path or absolute https:// URL; NULL for 410 Gone.
            $table->string('destination', 2048)->nullable();
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Menus are independent of URLs: an item points to a canonical route
        // (stable even when the path changes) or to an external URL.
        Schema::create('navigation_items', function (Blueprint $table) {
            $table->id();
            $table->string('menu', 20);
            $table->foreignId('parent_id')->nullable()->constrained('navigation_items')->cascadeOnDelete();
            $table->string('label');
            $table->foreignId('public_route_id')->nullable()->constrained()->nullOnDelete();
            $table->string('url', 2048)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['menu', 'parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_items');
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('public_routes');
    }
};
