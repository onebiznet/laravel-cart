<?php

namespace OneBiznet\LaravelCart\Models;

use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OneBiznet\LaravelCart\Facades;
use OneBiznet\LaravelCart\Contracts\Buyable;

class Cart extends Model
{
    protected $table = 'carts';
    
    protected $attributes = [
        'name' => null,
    ];

    protected $fillable = ['name'];

    public function getTable()
    {
        return config('cart.cart_table_name', parent::getTable());
    }

    public function contents(): HasMany
    {
        $model = Facades\Cart::getCartContentModel();

        return $this->hasMany($model, 'cart_id', 'id');
    }

    public function getContents(): Collection
    {
        return $this->contents;
    }

    public function add(array | Buyable $item, $quantity = 1, $price)
    {
        if ($item instanceof Buyable) {
            $cartItem = $this->contents()->firstOrNew([
                'item_id' => $item->getBuyableIdentifier(),
                'item_type' => $item->getMorphClass(),
            ], [
                'quantity' => 0,
                'price' => $item->getBuyablePrice(),
            ]);
        } elseif (is_string($item)) {
            $cartItem = $this->contents()->firstOrNew([
                'title' => $item,
            ], [
                'quantity' => 0,
                'price' => $price ?? 0.0,
            ]);
        } else {
            throw new Exception();
        }

        $cartItem->quantity += $quantity;

        if ($this->isDirty()) {
            $this->save();
        }

        return $this->contents()->save($cartItem);
    }
}
