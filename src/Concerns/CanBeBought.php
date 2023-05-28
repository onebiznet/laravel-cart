<?php 

namespace OneBiznet\LaravelCart\Concerns;

trait CanBeBought
{
    public function getBuyableIdentifier($options = null)
    {
        return $this->id;
    }

    public function getBuyableDescription($options = null)
    {
        return $this->title ?? $this->name ?? $this->description;
    }

    public function getBuyablePrice($options = null) 
    {
        return $this->price ?? $this->product_price ?? $this->regular_price;
    }
}