<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charity_payment_batches', function (Blueprint $table) {
            $table->text('debtor_name')->nullable()->change();
            $table->text('debtor_iban')->nullable()->change();
            $table->longText('xml_content')->nullable()->change();
        });
    }

    public function down(): void
    {
        $containsClearedBatches = DB::table('charity_payment_batches')
            ->whereNull('debtor_name')
            ->orWhereNull('debtor_iban')
            ->orWhereNull('xml_content')
            ->exists();

        if ($containsClearedBatches) {
            throw new RuntimeException('Cannot require payment batch details while cleared batches exist.');
        }

        Schema::table('charity_payment_batches', function (Blueprint $table) {
            $table->text('debtor_name')->nullable(false)->change();
            $table->text('debtor_iban')->nullable(false)->change();
            $table->longText('xml_content')->nullable(false)->change();
        });
    }
};
