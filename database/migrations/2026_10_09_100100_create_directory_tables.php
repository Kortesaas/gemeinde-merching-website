<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Managed external links / online services (reused via placements).
        Schema::create('external_resources', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('url', 2048);
            $table->text('description')->nullable();
            $table->string('type', 30);
            $table->string('provider_name')->nullable();
            $table->text('privacy_note')->nullable();
            $this->publication($table);
            $this->editors($table);
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 30);
            $table->text('description')->nullable();
            $table->string('street')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->text('opening_hours')->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            // Link to an external map – never embedded, no third-party requests.
            $table->foreignId('map_resource_id')->nullable()->constrained('external_resources')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name', 50)->nullable();
            $table->text('description')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('opening_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });

        // Employees/contacts. Deliberately no portrait/photo field.
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('salutation', 30)->nullable();
            $table->string('academic_title', 50)->nullable();
            $table->string('first_name', 120)->nullable();
            $table->string('last_name', 120);
            $table->string('display_name')->nullable();
            $table->string('job_title')->nullable();
            $table->text('responsibilities')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('fax', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('room', 120)->nullable();
            $table->text('availability')->nullable();
            $table->text('public_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('last_name');
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('department_person', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('function_label')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['department_id', 'person_id']);
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 30);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('contact_name')->nullable();
            $table->string('street')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('website', 2048)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_active', 'sort_order']);
        });

        Schema::create('organization_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('url', 2048);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Topics of the future contact form. Recipient addresses are internal
        // and stored encrypted (Eloquent "encrypted" cast).
        Schema::create('contact_routes', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->text('explanation')->nullable();
            $table->text('recipients');
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_routes');
        Schema::dropIfExists('organization_links');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('department_person');
        Schema::dropIfExists('people');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('external_resources');
    }

    private function publication(Blueprint $table): void
    {
        $table->string('status', 20)->default('draft');
        $table->timestamp('publish_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamp('archived_at')->nullable();
        $table->index(['status', 'publish_at', 'expires_at']);
    }

    private function editors(Blueprint $table): void
    {
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    }
};
