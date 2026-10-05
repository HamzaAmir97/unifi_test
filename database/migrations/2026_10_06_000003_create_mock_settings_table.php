<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Clock, faults, strict mode, optional-field mode (data-model §1). */
    public function up(): void
    {
        Schema::create('mock_settings', function (Blueprint $table) {
            $table->string('key', 48)->primary();
            $table->json('value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_settings');
    }
};
