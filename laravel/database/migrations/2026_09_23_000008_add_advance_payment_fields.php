<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (! Schema::hasColumn('invoices', 'customer_id')) $table->unsignedBigInteger('customer_id')->nullable()->after('booking_id');
                if (! Schema::hasColumn('invoices', 'advance_percentage')) $table->decimal('advance_percentage', 5, 2)->default(30)->after('total_amount');
                if (! Schema::hasColumn('invoices', 'advance_amount')) $table->decimal('advance_amount', 12, 2)->default(0)->after('advance_percentage');
                if (! Schema::hasColumn('invoices', 'remaining_amount')) $table->decimal('remaining_amount', 12, 2)->default(0)->after('advance_amount');
                if (! Schema::hasColumn('invoices', 'advance_payment_status')) $table->string('advance_payment_status')->default('PENDING')->after('remaining_amount');
                if (! Schema::hasColumn('invoices', 'remaining_payment_status')) $table->string('remaining_payment_status')->default('PENDING')->after('advance_payment_status');
                if (! Schema::hasColumn('invoices', 'created_at')) $table->timestamp('created_at')->nullable();
                if (! Schema::hasColumn('invoices', 'updated_at')) $table->timestamp('updated_at')->nullable();
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (! Schema::hasColumn('payments', 'booking_id')) $table->unsignedBigInteger('booking_id')->nullable()->after('payment_id');
                if (! Schema::hasColumn('payments', 'payment_type')) $table->string('payment_type')->default('advance')->after('payment_amount');
            });
        }

        if (Schema::hasTable('invoices')) {
            foreach (DB::table('invoices')->get() as $invoice) {
                $booking = Schema::hasTable('service_bookings')
                    ? DB::table('service_bookings')->where('booking_id', $invoice->booking_id)->first()
                    : null;
                $customerId = $booking?->customer_id;
                $total = (float) $invoice->total_amount;
                $advance = round($total * 30 / 100, 2);
                DB::table('invoices')->where('invoice_id', $invoice->invoice_id)->update([
                    'customer_id' => $invoice->customer_id ?? $customerId,
                    'advance_percentage' => $invoice->advance_percentage ?: 30,
                    'advance_amount' => $invoice->advance_amount ?: $advance,
                    'remaining_amount' => $invoice->remaining_amount ?: round($total - $advance, 2),
                    'advance_payment_status' => $invoice->advance_payment_status ?: 'PENDING',
                    'remaining_payment_status' => $invoice->remaining_payment_status ?: 'PENDING',
                ]);
            }
        }

        if (Schema::hasTable('payments')) {
            foreach (DB::table('payments')->get() as $payment) {
                $invoice = DB::table('invoices')->where('invoice_id', $payment->invoice_id)->first();
                DB::table('payments')->where('payment_id', $payment->payment_id)->update([
                    'booking_id' => $payment->booking_id ?? $invoice?->booking_id,
                    'payment_type' => $payment->payment_type ?: 'advance',
                    'payment_amount' => $invoice?->advance_amount ?: $payment->payment_amount,
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (['customer_id', 'advance_percentage', 'advance_amount', 'remaining_amount', 'advance_payment_status', 'remaining_payment_status', 'created_at', 'updated_at'] as $column) {
            if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', $column)) Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn($column));
        }
        foreach (['booking_id', 'payment_type'] as $column) {
            if (Schema::hasTable('payments') && Schema::hasColumn('payments', $column)) Schema::table('payments', fn (Blueprint $table) => $table->dropColumn($column));
        }
    }
};