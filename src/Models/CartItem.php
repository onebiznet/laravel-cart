<?php 

namespace OneBiznet\LaravelCart\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OneBiznet\LaravelCart\Contracts\Buyable;
use OneBiznet\LaravelCart\Facades;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class CartItem extends Model
{
    protected $table = 'cart_items';

    protected $attributes = [
        'name' => null,
        'quantity' => 0,
        'price' => 0.0,
    ];

    protected $fillable = ['name', 'quantity', 'price', 'data'];

    protected $casts = [
        'data' => 'array',
    ];

    public function cart(): BelongsTo
    {
        $cartModel = Facades\Cart::getCartModel();
        
        return $this->belongsTo($cartModel, 'cart_id', 'id');
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public static function newFromBuyable(Buyable $item, $options = [])
    {
        $newItem =  new self([
            'name' => $item->getBuyableDescription(),
            'price' => (float) $item->getBuyablePrice(),
            'data' => $options,
        ]);

        $newItem->model()->associate($item);

        return $newItem;
    }

    public function hasThumbnail(): bool 
    {
        if ($this->item == null) return false;

        return (bool) $this->item->thumbnail_url;
    }

    public function getThumbnailUrlAttribute()
    {
        return $this->hasThumbnail() ? $this->item->thumbnail_url : null;
    }
}