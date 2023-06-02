<?php

namespace OneBiznet\LaravelCart\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OneBiznet\LaravelCart\Facades;

class Cart extends Model
{
    use HasUuids;

    protected $table = 'carts';

    protected $attributes = [
        'name' => null,
    ];

    protected $fillable = ['name'];

    protected static function boot()
    {
        static::saving(function ($cart) {
            $cart->id = Facades\Cart::getCartKey();
            $cart->user_id = auth()->id();
        });

        parent::boot();
    }

    public function getTable()
    {
        return config('cart.cart_table_name', parent::getTable());
    }

    public function items(): HasMany
    {
        $model = Facades\Cart::getCartItemModel();

        return $this->hasMany($model, 'cart_id', 'id');
    }

    public function getContents(): Collection
    {
        return $this->items;
    }

    public function addItem($item, $qty)
    {
        $item->quantity = $item->exists ? $item->quantity + $qty : $qty;

        if (!$this->exists || $this->isDirty()) {
            $this->save();
        }

        return  $this->items()->save($item);
    }

    public function removeItem($item, $qty) 
    {
        if (!$this->exists || $this->isDirty()) {
            $this->save();
        }

        if ($item->quantity == $qty) {
            return $item->delete();
        }

        $item->quantity -= $qty;

        return $this->items()->save($item);
    }
}
