<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charity_requests', function (Blueprint $table) {
            $table->id();
            $table->string('requester_email');
            $table->foreignId('charity_member_id')->constrained('charity_members')->restrictOnDelete();
            $table->foreignId('charity_event_cost_id')->constrained('charity_event_costs')->restrictOnDelete();
            // Encrypted IBAN values are longer than the original value.
            $table->text('iban')->nullable();
            $table->string('account_name', 100)->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('charity_requests') && DB::table('charity_requests')->exists()) {
            throw new RuntimeException('Cannot roll back charity requests while they contain data.');
        }

        Schema::dropIfExists('charity_requests');
    }
};
