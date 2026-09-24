<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $primaryKey = 'customer_id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'full_name',
        'email',
        'phone',
        'address',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'customer_id', 'customer_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(ServiceBooking::class, 'customer_id', 'customer_id');
    }
}
