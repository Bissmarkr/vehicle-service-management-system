<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $table = 'service_bookings';
    protected $primaryKey = 'booking_id';
    protected $fillable = ['customer_id', 'vehicle_id', 'service_type_id', 'mechanic_id', 'preferred_date', 'preferred_time', 'booking_status', 'payment_status', 'customer_notes'];

    public function customer() { return $this->belongsTo(Customer::class, 'customer_id', 'customer_id'); }
    public function vehicle() { return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicle_id'); }
    public function service() { return $this->belongsTo(ServiceType::class, 'service_type_id', 'service_type_id'); }
    public function mechanic() { return $this->belongsTo(Mechanic::class, 'mechanic_id', 'mechanic_id'); }
    public function parts() { return $this->hasMany(BookingPart::class, 'booking_id', 'booking_id'); }
    public function invoice() { return $this->hasOne(Invoice::class, 'booking_id', 'booking_id'); }
}
