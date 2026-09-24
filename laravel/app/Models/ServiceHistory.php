<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceHistory extends Model
{
    protected $table = 'service_history';
    protected $primaryKey = 'history_id';
    public $timestamps = false;
    protected $fillable = ['vehicle_id', 'booking_id', 'service_date', 'service_summary', 'final_status'];
    public function vehicle() { return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicle_id'); }
    public function booking() { return $this->belongsTo(Booking::class, 'booking_id', 'booking_id'); }
}