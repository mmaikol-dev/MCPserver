<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SheetOrder extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'sheet_orders';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'order_date',
        'order_no',
        'amount',
        'client_name',
        'address',
        'phone',
        'alt_no',
        'country',
        'city',
        'product_name',
        'quantity',
        'status',
        'agent',
        'delivery_date',
        'instructions',
        'cc_email',
        'merchant',
        'order_type',
        'sheet_id',
        'sheet_name',
        'store_name',
        'code',
        'processed',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'order_date' => 'date',
        'delivery_date' => 'date',
        'quantity' => 'integer',
        'amount' => 'float',
        'processed' => 'boolean',
    ];

    /**
     * Default attribute values.
     */
    protected $attributes = [
        'status' => 'Pending',
        'country' => 'Kenya',
        'processed' => false,
    ];

    /**
     * Scope: only unprocessed sheet orders.
     */
    public function scopeUnprocessed($query)
    {
        return $query->where('processed', false);
    }

    /**
     * Scope: filter by merchant.
     */
    public function scopeMerchant($query, string $merchant)
    {
        return $query->where('merchant', $merchant);
    }

    /**
     * Scope: filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
