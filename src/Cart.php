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
     * @param mixed     $id
     * @param mixed     $name
     * @param int|float $qty
     * @param float     $price
     * @param array     $options
     *
     * @return \OneBiznet\LaravelCart\Models\CartItem
     */
    public function add(mixed $id, mixed $name = null, $qty = null, $price = null, array $options = [])
    {
        if ($this->isMulti($id)) {
            return array_map(function ($item) {
                return $this->add($item);
            }, $id);
        }

        $this->cart = $this->cart ?? $this->getCart();

        return $this->cart->add($id, $name, $qty, $price, $options);
    }

    public function contents()
    {
        $model = $this->getCartItemModel();

        return $model::where('cart_id', $this->getCartKey())->get();
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

    protected function getCart()
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
