<?php

namespace OneBiznet\LaravelCart;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use OneBiznet\LaravelCart\Models;
use OneBiznet\LaravelCart\Concerns\HasEvents;
use OneBiznet\LaravelCart\Contracts\Buyable;

class Cart
{
    use Macroable,
        HasEvents;

    /**
     * The root session name.
     *
     * @var string
     */
    protected string $sessionKey;

    /**
     * The default cart name.
     *
     * @var string
     */
    protected string $defaultCartName = 'default';

    /**
     * The name of current cart instance.
     *
     * @var string
     */
    protected string $cartName;

    protected Collection $carts;

    /**
     * Create cart instance.
     *
     * @return void
     */
    public function __construct(Dispatcher $events)
    {
        $this->events = $events;
        $this->sessionKey = '_' . md5(config('app.name') . __NAMESPACE__);
        $defaultCartName = config('cart.default_cart_name');

        if (is_string($defaultCartName) && !empty($defaultCartName)) {
            $this->defaultCartName = $defaultCartName;
        }

        $this->name()->initSessions();
    }

    /**
     * Initialize attributes for current cart instance.
     *
     * @return bool return false if attributes already exist without initialization
     */
    protected function initSessions(): void
    {
        $uuid = $this->getCartKey();

        $cartModel = $this->getCartModel();

        $this->carts = auth()->check() ? $cartModel::where('user_id', auth()->id())->get() : new Collection();

        $this->carts->each(function ($cart) use ($uuid) {
            $cart->uuid = $uuid;
            if ($cart->isDirty()) $cart->save();
        });

        if (is_null($this->carts) || $this->carts->isEmpty()) {
            $this->carts = $cartModel::where('uuid', $uuid)->get();
            if ($user_id = auth()->id())
                $this->carts->each(function ($cart) use ($user_id) {
                    $cart->user_id = $user_id;
                    if ($cart->isDirty()) $cart->save();
                });
        }
    }

    protected function getCart()
    {
        try {
            return $this->carts->firstOrFail('name', $this->cartName);
            
        } catch (ItemNotFoundException) {
            $cartModel = $this->getCartModel();

            $instance = $cartModel::firstOrNew(['name' => $this->cartName]);
            $instance->uuid = $this->getCartKey();
            $instance->user_id = auth()->id();

            return $instance;
        }

        return null;
    }

    protected function getCartKey(): string
    {
        if (!session()->has($this->sessionKey)) {
            session()->put($this->sessionKey, Str::uuid());
        }
        return session()->get($this->sessionKey);
    }

    public function getCartModel(): string
    {
        return config('cart.cart_model');
    }

    public function getCartContentModel(): string
    {
        return config('cart.cart_content_model');
    }

    public function name(string $name = 'default'): self
    {
        $this->cartName = $name;

        return $this;
    }

    public function getContents()
    {
        return $this->carts->flatMap->contents;
    }

    public function add(string | Buyable $item, int $quantity = 1, float $price = 0.0)
    {
        $attributes = [];

        if ($item instanceof Buyable) {
            $attributes = [
                'name' => $item->getBuyableDescription(),
                'item_id' => $item->getBuyableIdentifier(),
                'item_type' => $item->getMorphClass(),
                'price' => $item->getBuyablePrice() ?? $price,
                'quantity' => $quantity,
            ];

        } else {
            $attributes = [
                'name' => $item,
                'price' => $price,
                'quantity' => $quantity,
            ];
        }

        $this->fireEvent('cart.adding', $attributes);

        if ($itemAdded = $this->getCart()->add($item, $quantity, $price)) {
            $this->fireEvent('cart.added', $itemAdded);
        }

    }

    public function count(): int
    {
        return $this->getContents()->sum('quantity');
    }
}
