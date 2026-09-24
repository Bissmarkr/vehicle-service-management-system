<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SparePart extends Model
{
    protected $table = 'spare_parts';
    protected $primaryKey = 'part_id';
    public $timestamps = false;
    protected $fillable = ['part_name', 'stock_quantity', 'unit_price'];
}
