<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payments';
    protected $primaryKey = 'payment_id';
    public $timestamps = false;
    protected $fillable = [
        'booking_id',
        'invoice_id',
        'customer_id',
        'payment_amount',
        'payment_type',
        'payment_method',
        'payment_date',
        'payment_status',
        'admin_status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'status',
        'currency',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'stripe_event_id',
        'paid_at',
    ];

    protected $casts = [
        'payment_amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function invoice() { return $this->belongsTo(Invoice::class, 'invoice_id', 'invoice_id'); }
    public function booking() { return $this->belongsTo(Booking::class, 'booking_id', 'booking_id'); }
    public function customer() { return $this->belongsTo(Customer::class, 'customer_id', 'customer_id'); }
}
