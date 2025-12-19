<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_room_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('deal_rooms')->onDelete('cascade');
            $table->string('name');
            $table->string('file_path', 500);
            $table->unsignedInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->text('description')->nullable();
            $table->boolean('is_visible_to_external')->default(true);
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['room_id', 'is_visible_to_external']);
        });

        Schema::create('deal_room_document_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('deal_room_documents')->onDelete('cascade');
            $table->foreignId('member_id')->constrained('deal_room_members')->onDelete('cascade');
            $table->unsignedInteger('time_spent_seconds')->nullable();
            $table->timestamp('viewed_at')->useCurrent();

            $table->index(['document_id', 'viewed_at']);
            $table->index('member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_room_document_views');
        Schema::dropIfExists('deal_room_documents');
    }
};
