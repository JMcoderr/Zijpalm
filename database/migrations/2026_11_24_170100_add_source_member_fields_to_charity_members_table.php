<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charity_members', function (Blueprint $table) {
            // Legacy imports can contain members without an email address or full name.
            $table->string('email')->nullable()->change();
            $table->string('full_name')->nullable()->change();
            $table->string('name')->nullable();
            // The source uses these dates to determine which milestones a member can request.
            $table->date('dob')->nullable();
            $table->date('years_of_service')->nullable();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        $hasSourceData = DB::table('charity_members')
            ->where(function ($query) {
                $query->whereNull('email')
                    ->orWhereNull('full_name')
                    ->orWhereNotNull('name')
                    ->orWhereNotNull('dob')
                    ->orWhereNotNull('years_of_service')
                    ->orWhereNotNull('deleted_at');
            })
            ->exists();

        if ($hasSourceData) {
            // Refuse to drop imported values or restore required fields when rows are incomplete.
            throw new RuntimeException('Cannot remove source member fields while imported member data exists.');
        }

        Schema::table('charity_members', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['name', 'dob', 'years_of_service']);
        });

        Schema::table('charity_members', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->string('full_name')->nullable(false)->change();
        });
    }
};
