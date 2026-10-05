<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The request log (FR-020). The API key is stored masked, never in full. */
    public function up(): void
    {
        Schema::create('mock_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id');
            $table->string('method', 10);
            $table->string('path', 512);
            $table->json('query');
            $table->json('body')->nullable();
            $table->string('api_key_masked', 16);
            $table->unsignedSmallInteger('status');
            $table->unsignedInteger('duration_ms');
            $table->json('warnings');
            $table->timestamp('created_at', 3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_requests');
    }
};
