<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charity_member_links', function (Blueprint $table) {
            $table->id();
            // The legacy member ID is not guaranteed to match users.employee_number.
            $table->unsignedBigInteger('source_member_id')->unique();
            // Keep historical links intact and prevent hard-deleting a linked user.
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charity_member_links');
    }
};
