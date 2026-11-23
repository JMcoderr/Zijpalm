<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charity_activity_links', function (Blueprint $table) {
            $table->id();
            // Legacy event IDs are independent of Zijpalm activity IDs and titles.
            $table->unsignedBigInteger('source_event_id')->unique();
            // Keep the mapped activity while a legacy event link exists.
            $table->foreignId('activity_id')->unique()->constrained('activities')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charity_activity_links');
    }
};
