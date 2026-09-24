<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
            $testUsers = [
                ['username' => 'admin@example.com', 'email' => 'admin@example.com', 'password' => 'Admin@123', 'role' => 'ADMIN'],
                ['username' => 'customer@example.com', 'email' => 'customer@example.com', 'password' => 'Customer@123', 'role' => 'CUSTOMER'],
                ['username' => 'mechanic@example.com', 'email' => 'mechanic@example.com', 'password' => 'Mechanic@123', 'role' => 'MECHANIC'],
            ];

            foreach ($testUsers as $testUser) {
                DB::table('users')->updateOrInsert(
                    ['username' => $testUser['username']],
                    [
                        'email' => $testUser['email'],
                        'password_hash' => Hash::make($testUser['password']),
                        'role' => $testUser['role'],
                    ]
                );
            }

            $adminId = DB::table('users')->where('username', 'admin@vsms.local')->value('user_id');
            if (! $adminId) {
                $adminId = DB::table('users')->insertGetId([
                    'username' => 'admin@vsms.local',
                    'password_hash' => Hash::make('password123'),
                    'role' => 'ADMIN',
                ], 'user_id');
            }

            $customerId = DB::table('customers')->where('email', 'demo.customer@vsms.local')->value('customer_id');
            if (! $customerId) {
                $customerId = DB::table('customers')->insertGetId([
                    'user_id' => $adminId,
                    'full_name' => 'Demo Customer',
                    'email' => 'demo.customer@vsms.local',
                    'phone' => '+92 300 0000000',
                    'address' => 'VSMS Demo Center',
                ], 'customer_id');
            }

            $vehicleId = DB::table('vehicles')->where('registration_number', 'VSMS-001')->value('vehicle_id');
            if (! $vehicleId) {
                $vehicleId = DB::table('vehicles')->insertGetId([
                    'customer_id' => $customerId,
                    'registration_number' => 'VSMS-001',
                    'make' => 'Toyota',
                    'model' => 'Corolla',
                    'manufacture_year' => 2022,
                ], 'vehicle_id');
            }

            $mechanicId = DB::table('mechanics')->where('email', 'mechanic@vsms.local')->value('mechanic_id');
            if (! $mechanicId) {
                $mechanicId = DB::table('mechanics')->insertGetId([
                    'full_name' => 'Demo Mechanic',
                    'phone' => '+92 300 1111111',
                    'email' => 'mechanic@vsms.local',
                    'specialization' => 'General Service',
                ], 'mechanic_id');
            }

            $serviceId = DB::table('service_types')->where('service_name', 'Oil Change')->value('service_type_id');
            $bookingId = DB::table('service_bookings')->where('customer_id', $customerId)->value('booking_id');
            if (! $bookingId) {
                $bookingId = DB::table('service_bookings')->insertGetId([
                    'customer_id' => $customerId,
                    'vehicle_id' => $vehicleId,
                    'service_type_id' => $serviceId,
                    'preferred_date' => now()->addDays(2)->toDateString(),
                    'booking_status' => 'CONFIRMED',
                    'customer_notes' => 'Demo booking for academic presentation',
                ], 'booking_id');
            }

            DB::table('service_assignments')->updateOrInsert(
                ['booking_id' => $bookingId],
                ['mechanic_id' => $mechanicId]
            );

            $invoiceId = DB::table('invoices')->where('booking_id', $bookingId)->value('invoice_id');
            if (! $invoiceId) {
                $invoiceId = DB::table('invoices')->insertGetId([
                    'booking_id' => $bookingId,
                    'service_charge' => 5000,
                    'parts_charge' => 0,
                    'total_amount' => 5000,
                    'invoice_status' => 'PAID',
                    'invoice_date' => now()->toDateString(),
                ], 'invoice_id');
            }

            DB::table('payments')->updateOrInsert(
                ['invoice_id' => $invoiceId],
                ['payment_amount' => 5000, 'payment_method' => 'CASH', 'payment_status' => 'COMPLETED']
            );
    }
}
