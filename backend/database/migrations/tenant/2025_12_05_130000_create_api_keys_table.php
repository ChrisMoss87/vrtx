<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key', 64)->unique(); // Hashed API key
            $table->string('prefix', 8); // First 8 chars for identification (e.g., "vrtx_abc")
            $table->text('description')->nullable();
            $table->json('scopes')->nullable(); // Specific permissions: ["records:read", "records:write"]
            $table->json('allowed_ips')->nullable(); // IP whitelist
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->integer('rate_limit')->nullable(); // Requests per minute
            $table->bigInteger('request_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_active']);
            $table->index('prefix');
        });

        // API request logs for analytics
        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 10);
            $table->string('path', 500);
            $table->integer('status_code');
            $table->string('ip_address', 45);
            $table->string('user_agent', 500)->nullable();
            $table->integer('response_time_ms');
            $table->json('request_headers')->nullable();
            $table->json('response_summary')->nullable();
            $table->timestamp('created_at');

            $table->index(['api_key_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
        Schema::dropIfExists('api_keys');
    }
};
