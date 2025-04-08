<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('amo_crm_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('access_token', 1000);
            $table->string('refresh_token', 1000);
            $table->string('base_domain')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amo_crm_tokens');
    }
};
