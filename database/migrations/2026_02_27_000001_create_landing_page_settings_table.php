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
        Schema::create('landing_page_settings', function (Blueprint $table) {
            $table->id();
            $table->string('hero_title');
            $table->text('hero_subtitle')->nullable();
            $table->string('hero_cta_text')->nullable();
            $table->string('hero_cta_url')->nullable();
            $table->string('about_title');
            $table->text('about_description');
            $table->string('features_title');
            $table->text('features_subtitle')->nullable();
            $table->string('instagram_title');
            $table->string('instagram_username')->nullable();
            $table->string('contact_title');
            $table->text('contact_description')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_whatsapp')->nullable();
            $table->text('contact_address')->nullable();
            $table->string('contact_map_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landing_page_settings');
    }
};
