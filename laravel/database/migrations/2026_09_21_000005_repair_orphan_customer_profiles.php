<?php

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        User::query()
            ->whereRaw('LOWER(role) = ?', ['customer'])
            ->whereDoesntHave('customer')
            ->each(function (User $user): void {
                $name = trim((string) ($user->username ?: $user->email ?: 'Customer'));
                $email = $user->email ?: $user->username;

                DB::transaction(function () use ($user, $name, $email): void {
                    if (! Customer::where('user_id', $user->user_id)->exists()) {
                        Customer::create([
                            'user_id' => $user->user_id,
                            'full_name' => $name,
                            'email' => $email,
                            'phone' => 'Not provided',
                            'address' => null,
                        ]);
                    }
                });
            });
    }

    public function down(): void
    {
        // Repaired profiles are retained so existing customer data is not deleted.
    }
};
