<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingPart extends Model
{
    protected $table = 'booking_parts';
    protected $primaryKey = 'booking_part_id';
    protected $fillable = ['booking_id', 'part_id', 'quantity', 'unit_price'];

    public function part() { return $this->belongsTo(SparePart::class, 'part_id', 'part_id'); }
}
