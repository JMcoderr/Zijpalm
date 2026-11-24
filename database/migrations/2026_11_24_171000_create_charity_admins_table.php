<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charity_admins', function (Blueprint $table) {
            $table->id();
            // The source stores the administrator email in this field.
            $table->string('employee')->unique();
            // Preserve the grant time; null means this administrator is not a superadmin.
            $table->timestamp('super')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('charity_admins')->exists()) {
            throw new RuntimeException('Cannot remove Lief & Leed admin records during rollback.');
        }

        Schema::dropIfExists('charity_admins');
    }
};
