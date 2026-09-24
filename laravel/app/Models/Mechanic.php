<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mechanic extends Model
{
    protected $table = 'mechanics';
    protected $primaryKey = 'mechanic_id';
    public $timestamps = false;
    protected $fillable = ['user_id', 'full_name', 'phone', 'email', 'specialization'];
}
