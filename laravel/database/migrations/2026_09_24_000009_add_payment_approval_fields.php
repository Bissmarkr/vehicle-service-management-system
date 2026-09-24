<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'admin_status')) $table->string('admin_status')->nullable()->after('payment_status');
            if (! Schema::hasColumn('payments', 'approved_by')) $table->unsignedBigInteger('approved_by')->nullable()->after('admin_status');
            if (! Schema::hasColumn('payments', 'approved_at')) $table->timestamp('approved_at')->nullable()->after('approved_by');
            if (! Schema::hasColumn('payments', 'rejection_reason')) $table->text('rejection_reason')->nullable()->after('approved_at');
        });

        DB::table('payments')
            ->whereIn('payment_status', ['PAID', 'COMPLETED'])
            ->whereNull('admin_status')
            ->update([
                'payment_status' => 'COMPLETED',
                'admin_status' => 'APPROVED',
            ]);
    }

    public function down(): void
    {
        foreach (['admin_status', 'approved_by', 'approved_at', 'rejection_reason'] as $column) {
            if (Schema::hasTable('payments') && Schema::hasColumn('payments', $column)) {
                Schema::table('payments', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
