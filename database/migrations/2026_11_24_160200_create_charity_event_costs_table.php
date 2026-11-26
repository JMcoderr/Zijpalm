<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charity_event_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charity_event_id')->constrained('charity_events')->restrictOnDelete();
            $table->decimal('amount', 13, 2);
            // Match source cost periods: start is inclusive and end is exclusive.
            $table->dateTime('start_date');
            $table->dateTime('end_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('charity_event_costs') && DB::table('charity_event_costs')->exists()) {
            throw new RuntimeException('Cannot roll back charity event costs while they contain data.');
        }

        Schema::dropIfExists('charity_event_costs');
    }
};
