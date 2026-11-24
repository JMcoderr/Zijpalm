<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charity_events', function (Blueprint $table) {
            $table->id();
            // Keep the source ID separate from Zijpalm activities; never match by title.
            $table->unsignedBigInteger('source_event_id')->nullable()->unique();
            $table->string('title')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charity_events');
    }
};
