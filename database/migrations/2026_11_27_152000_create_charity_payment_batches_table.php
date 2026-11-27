<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charity_payment_batches', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 35)->unique();
            $table->text('debtor_name');
            $table->text('debtor_iban');
            $table->date('execution_date');
            $table->unsignedInteger('transaction_count');
            $table->bigInteger('total_amount_cents');
            $table->longText('xml_content');
            $table->string('status', 20)->default('exported');
            $table->timestamp('exported_at');
            $table->timestamps();
        });

        Schema::table('charity_requests', function (Blueprint $table) {
            $table->foreignId('charity_payment_batch_id')
                ->nullable()
                ->constrained('charity_payment_batches')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('charity_payment_batches') && DB::table('charity_payment_batches')->exists()) {
            throw new RuntimeException('Cannot roll back payment batches while they contain data.');
        }

        if (Schema::hasColumn('charity_requests', 'charity_payment_batch_id')) {
            Schema::table('charity_requests', function (Blueprint $table) {
                $table->dropConstrainedForeignId('charity_payment_batch_id');
            });
        }

        Schema::dropIfExists('charity_payment_batches');
    }
};
