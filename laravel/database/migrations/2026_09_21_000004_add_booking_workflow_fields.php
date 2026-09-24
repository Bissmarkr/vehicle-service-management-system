<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_bookings') && ! Schema::hasColumn('service_bookings', 'mechanic_id')) {
            Schema::table('service_bookings', function (Blueprint $table) {
                $table->unsignedBigInteger('mechanic_id')->nullable()->after('service_type_id')->index();
            });
        }

        if (! Schema::hasTable('booking_parts')) {
            Schema::create('booking_parts', function (Blueprint $table) {
                $table->id('booking_part_id');
                $table->unsignedBigInteger('booking_id');
                $table->unsignedBigInteger('part_id');
                $table->unsignedInteger('quantity');
                $table->decimal('unit_price', 12, 2);
                $table->timestamps();
                $table->unique(['booking_id', 'part_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_parts');

        if (Schema::hasTable('service_bookings') && Schema::hasColumn('service_bookings', 'mechanic_id')) {
            Schema::table('service_bookings', function (Blueprint $table) {
                $table->dropColumn('mechanic_id');
            });
        }
    }
};
