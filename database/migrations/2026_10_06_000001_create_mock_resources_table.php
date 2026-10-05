<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** One table for every resource type (R8). Derived fields are never stored (R9). */
    public function up(): void
    {
        Schema::create('mock_resources', function (Blueprint $table) {
            $table->id();
            $table->string('type', 48);
            $table->uuid('site_uuid')->nullable();
            $table->uuid('uuid')->unique();
            $table->json('payload');
            $table->integer('position')->default(0);
            $table->timestamps();
            $table->index(['type', 'site_uuid', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_resources');
    }
};
