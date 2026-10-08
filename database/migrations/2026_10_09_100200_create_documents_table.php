<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One central document/download record per uploaded file.
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();

            // File (private disk, random name; see App\Services\Uploads\UploadInspector)
            $table->string('file_path')->unique();
            $table->string('original_filename');
            $table->string('mime_type', 100);
            $table->string('extension', 10);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64)->index();

            $table->unsignedSmallInteger('year')->nullable()->index();
            $table->date('document_date')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('language', 10)->default('de');

            $table->string('accessibility_status', 30)->default('not_checked');
            $table->text('accessibility_notes')->nullable();
            $table->foreignId('accessible_alternative_id')->nullable()->constrained('documents')->restrictOnDelete();

            // Supersession: the newer document points to the one it replaces.
            $table->foreignId('replaces_document_id')->nullable()->unique()->constrained('documents')->restrictOnDelete();

            $table->string('status', 20)->default('draft');
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'publish_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
