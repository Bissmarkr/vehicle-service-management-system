<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceType extends Model
{
    protected $table = 'service_types';
    protected $primaryKey = 'service_type_id';
    public $timestamps = false;
    protected $fillable = ['service_name', 'description', 'price', 'estimated_duration_minutes'];
}
