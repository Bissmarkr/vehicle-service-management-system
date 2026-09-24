<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $table = 'invoices';
    protected $primaryKey = 'invoice_id';
    protected $fillable = ['booking_id', 'customer_id', 'service_charge', 'parts_charge', 'total_amount', 'advance_percentage', 'advance_amount', 'remaining_amount', 'advance_payment_status', 'remaining_payment_status', 'invoice_status', 'invoice_date'];

    protected $casts = [
        'advance_percentage' => 'decimal:2',
        'advance_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
    ];

    public function booking() { return $this->belongsTo(Booking::class, 'booking_id', 'booking_id'); }
    public function payments() { return $this->hasMany(Payment::class, 'invoice_id', 'invoice_id'); }
    public function customer() { return $this->belongsTo(Customer::class, 'customer_id', 'customer_id'); }
}
