<?php

namespace OneBiznet\LaravelCart;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Session\SessionManager;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use OneBiznet\LaravelCart\Models;
use OneBiznet\LaravelCart\Concerns\HasEvents;
use OneBiznet\LaravelCart\Contracts\Buyable;
use OneBiznet\LaravelCart\Contracts\InstanceIdentifier;
use OneBiznet\LaravelCart\Models\CartItem;

class Cart
{
    use Macroable;

    const DEFAULT_INSTANCE = 'default';

    /**
     * Instance of the session manager.
     *
     * @var \Illuminate\Session\SessionManager
     */
    private $session;

    /**
     * Instance of the event dispatcher.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    private $events;

    /**
     * Holds the current cart instance.
     *
     * @var string
     */
    private $instance;

    /**
     * Cart instance
     *
     * @var \OneBiznet\LaravelCart\Models\Cart
     */
    private $cart;

    /**
     * Create cart instance.
     *
     * @return void
     */
    public function __construct(SessionManager $session, Dispatcher $events)
    {
        $this->session = $session;
        $this->events = $events;

        $this->instance(self::DEFAULT_INSTANCE);
    }

    /**
     * Set the current cart instance.
     *
     * @param string|null $instance
     *
     * @return \OneBiznet\LaravelCart\Cart
     */
    public function instance($instance = null): self
    {
        $instance = $instance ?: self::DEFAULT_INSTANCE;

        if ($instance instanceof InstanceIdentifier) {
            $instance = $instance->getInstanceIdentifier();
        }

        $this->instance = 'cart.' . $instance;

        return $this;
    }

    /**
     * Get the current cart instance.
     *
     * @return string
     */
    public function getInstance(): string
    {
        return str_replace('cart.', '', $this->instance);
    }

    /**
     * Add an item to the cart.
     *
     * @param mixed     $item
     * @param int|float $qty
     * @param float     $price
     * @param array     $options
     *
     * @return \OneBiznet\LaravelCart\Models\CartItem
     */
    public function add(mixed $item, $qty = null, $price = null, array $options = [])
    {
        if ($this->isMulti($item)) {
            return array_map(function ($eachItem) {
                return $this->add($eachItem);
            }, $item);
        }

        $cartItem = $this->getCartItem($item, $price, $options);

        $this->events->dispatch('cart.adding', [
            'item' => $cartItem,
            'qty' => $qty,
        ], false);

        $this->cart = $this->cart ?? $this->getCart();

        $cartItem = $this->cart->addItem($cartItem, $qty);

        $this->events->dispatch('cart.added', [
            'item' => $cartItem,
            'qty' => $qty,
        ]);

        return $cartItem;
    }

    public function remove(mixed $item, $qty = null)
    {
        $item = $this->getCartItem($item);

        $this->events->dispatch('cart.removing', [
            'item' => $item,
        ]);

        $qty = $qty ?: $item->quantity;

        $cart = $this->getCart();

        if ($cart->removeItem($item, $qty)) {
            $this->events->dispatch('cart.removed', [
                'item' => $item
            ]);
        }
    }

    public function update(mixed $item, $qty)
    {
        $item = $this->getCartItem($item);

        $this->events->dispatch('cart.updating', [
            'item' => $item,
            'qty' => $qty,
        ]);

        $item->quantity = $qty;

        $this->cart = $this->cart ?: $this->getCart();

        if ($this->cart->items()->save($item)) {
            $this->events->dispatch('cart.updated', [
                'item' => $item,
                'qty' => $qty,
            ]);
        }
    }

    public function clear()
    {
        $this->cart = $this->cart ?: $this->getCart();

        $this->events->dispatch('cart.clearing', [
            'cart' => $this->cart,
        ]);

        foreach ($this->cart->items as $item) {
            $item->delete();
        }

        $this->events->dispatch('cart.cleared', [
            'cart' => $this->cart,
        ]);
    }


    public function contents()
    {
        $model = $this->getCartItemModel();

        return $model::where('cart_id', $this->getCartKey())->get();
    }

    public function getCartItem($item, $price = 0, $options = [])
    {
        $itemClass = config('cart.cart_item_model');

        if ($item instanceof Buyable) {
            $newItem = $itemClass::newFromBuyable($item, $price ?: []);
        } elseif ($item instanceof CartItem) {
            $newItem = $item;
        } elseif (is_array($item)) {
            $newItem = new $itemClass($item);
        } else {
            return $itemClass::findOr($item, function () use ($itemClass, $item, $price, $options) {
                return new $itemClass([
                    'name' => $item,
                    'price' => $price,
                    'data' => $options,
                ]);
            });
        }

        $this->cart = $this->cart ?? $this->getCart();

        $existing = $this->cart->items->first(function ($item) use ($newItem) {
            return ($item->model_id != null && $item->model_id == $newItem->model_id && $item->model_type == $newItem->model_type) || ($item->model_id == null && $item->name == $newItem->name && $newItem->model_id == null);
        });

        return $existing ?: $newItem;
    }

    public function count()
    {
        $this->cart = $this->cart ?? $this->getCart();

        return $this->cart->items->sum('quantity');
    }

    /**
     * Destroy the current cart instance.
     *
     * @return void
     */
    public function destroy()
    {
        $this->session->remove($this->instance);
    }

    public function getCart()
    {
        $model = $this->getCartModel();

        $this->cart = auth()->check()
            ? $model::where('user_id', auth()->id())->firstOr(function () use ($model) {
                return $this->getSessionCart();
            })
            : $this->getSessionCart();

        return $this->cart;
    }

    protected function getSessionCart()
    {
        $model = $this->getCartModel();

        return $model::where('id', $this->getCartKey())->firstOr(function () use ($model) {
            $cart = new $model([
                'name' => $this->getInstance() ?: self::DEFAULT_INSTANCE,
            ]);

            return $cart;
        });
    }

    public function getCartKey(): string
    {
        if (!$this->session->has($this->instance)) {
            $this->session->put($this->instance, Str::uuid());
        }
        return $this->session->get($this->instance);
    }

    public function getCartModel(): string
    {
        return config('cart.cart_model');
    }

    public function getCartItemModel(): string
    {
        return config('cart.cart_item_model');
    }

    /**
     * Check if the item is a multidimensional array or an array of Buyables.
     *
     * @param mixed $item
     *
     * @return bool
     */
    private function isMulti($item)
    {
        if (!is_array($item)) {
            return false;
        }

        return is_array(head($item)) || head($item) instanceof Buyable;
    }
}
