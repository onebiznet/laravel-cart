<?php 

namespace OneBiznet\LaravelCart\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OneBiznet\LaravelCart\Facades;

class CartContent extends Model
{
    protected $table = 'cart_contents';

    protected $attributes = [
        'title' => null,
        'quantity' => 0,
        'price' => 0.0,
    ];

    protected $fillable = ['title', 'quantity', 'price'];

    public function cart(): BelongsTo
    {
        $cartModel = Facades\Cart::getCartModel();
        
        return $this->belongsTo($cartModel, 'cart_id', 'id');
    }

    public function getCart()
    {
        return $this->cart ?? new (Facades\Cart::getCartModel());
    }

    public function item(): MorphTo
    {
        return $this->morphTo();
    }
}