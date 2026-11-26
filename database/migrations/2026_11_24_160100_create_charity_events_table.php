<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        if (Schema::hasTable('charity_events') && DB::table('charity_events')->exists()) {
            throw new RuntimeException('Cannot roll back charity events while they contain data.');
        }

        Schema::dropIfExists('charity_events');
    }
};
