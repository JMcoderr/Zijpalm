<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charity_members', function (Blueprint $table) {
            $table->id();
            // Source data may contain duplicate email addresses.
            $table->string('email');
            $table->string('full_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charity_members');
    }
};
