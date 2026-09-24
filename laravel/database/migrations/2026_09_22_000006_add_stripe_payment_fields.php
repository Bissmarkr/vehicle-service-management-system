<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_bookings')) {
            if (! Schema::hasColumn('service_bookings', 'preferred_time')) {
                Schema::table('service_bookings', function (Blueprint $table) {
                    $table->string('preferred_time')->nullable()->after('preferred_date');
                });
            }

            if (! Schema::hasColumn('service_bookings', 'payment_status')) {
                Schema::table('service_bookings', function (Blueprint $table) {
                    $table->string('payment_status')->nullable()->default('PENDING')->after('booking_status');
                });
            }
        }

        if (Schema::hasTable('payments')) {
            $paymentColumns = ['customer_id', 'status', 'currency', 'stripe_checkout_session_id', 'stripe_payment_intent_id', 'stripe_event_id', 'paid_at'];
            foreach ($paymentColumns as $column) {
                if (! Schema::hasColumn('payments', $column)) {
                    Schema::table('payments', function (Blueprint $table) use ($column) {
                        if ($column === 'customer_id') {
                            $table->unsignedBigInteger('customer_id')->nullable()->after('invoice_id');
                        }
                        if ($column === 'status') {
                            $table->string('status')->nullable()->default('PENDING')->after('payment_status');
                        }
                        if ($column === 'currency') {
                            $table->string('currency', 10)->nullable()->default('LKR')->after('status');
                        }
                        if ($column === 'stripe_checkout_session_id') {
                            $table->string('stripe_checkout_session_id')->nullable()->after('currency');
                        }
                        if ($column === 'stripe_payment_intent_id') {
                            $table->string('stripe_payment_intent_id')->nullable()->after('stripe_checkout_session_id');
                        }
                        if ($column === 'stripe_event_id') {
                            $table->string('stripe_event_id')->nullable()->after('stripe_payment_intent_id');
                        }
                        if ($column === 'paid_at') {
                            $table->timestamp('paid_at')->nullable()->after('stripe_event_id');
                        }
                    });
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('service_bookings') && Schema::hasColumn('service_bookings', 'payment_status')) {
            Schema::table('service_bookings', function (Blueprint $table) {
                $table->dropColumn('payment_status');
            });
        }

        if (Schema::hasTable('payments')) {
            $columns = ['customer_id', 'status', 'currency', 'stripe_checkout_session_id', 'stripe_payment_intent_id', 'stripe_event_id', 'paid_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    Schema::table('payments', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }
};
