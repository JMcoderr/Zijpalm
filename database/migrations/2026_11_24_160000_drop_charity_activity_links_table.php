<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('charity_activity_links');
    }

    public function down(): void
    {
        Schema::create('charity_activity_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_event_id')->unique();
            $table->foreignId('activity_id')->unique()->constrained('activities')->restrictOnDelete();
            $table->timestamps();
        });
    }
};
