<?php

namespace OneBiznet\LaravelCart\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OneBiznet\LaravelCart\Facades;
use OneBiznet\LaravelCart\Contracts\Buyable;

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

    public function add($item, $qty, $price, $options)
    {
        if ($item instanceof Buyable) {
            $cartItem = CartItem::newFromBuyable($item, $price ?: []);
            $cartItem->quantity = $qty ?: 1;
        } elseif (is_array($item)) {
            $cartItem = new CartItem($item);
            $cartItem->quantity = $qty ?: 1;
        } else {
            $cartItem = new CartItem([
                'name' => $item,
                'quantity' => $qty,
                'price' => $price,
                'data' => $options,
            ]);
        }

        if (!$this->getKey() || $this->isDirty()) {
            $this->save();
        }
        $existing = $this->items->first(function ($item) use ($cartItem) {
            return ($item->model_id != null && $item->model_id == $cartItem->model_id && $item->model_type == $cartItem->model_type) || ($item->model_id == null && $item->name == $cartItem->name && $cartItem->model_id == null);
        });
        if ($existing) {
            $existing->quantity += $cartItem->quantity;

            return $this->items()->save($existing);
        }

        return  $this->items()->save($cartItem);
    }
}
