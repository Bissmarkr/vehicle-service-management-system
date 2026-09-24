<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'payment_status')) {
            DB::statement("ALTER TABLE payments MODIFY payment_status ENUM('PENDING','PROCESSING','PAID','FAILED','CANCELLED','REFUNDED','COMPLETED') NOT NULL DEFAULT 'PENDING'");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'payment_status')) {
            DB::statement("ALTER TABLE payments MODIFY payment_status ENUM('PENDING','COMPLETED','FAILED','REFUNDED') NOT NULL DEFAULT 'COMPLETED'");
        }
    }
};