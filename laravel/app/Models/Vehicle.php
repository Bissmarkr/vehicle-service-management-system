<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $table = 'vehicles';
    protected $primaryKey = 'vehicle_id';
    public $timestamps = false;
    protected $fillable = ['customer_id', 'registration_number', 'make', 'model', 'manufacture_year'];
    protected $casts = ['manufacture_year' => 'integer'];

    public function customer() { return $this->belongsTo(Customer::class, 'customer_id', 'customer_id'); }
}
