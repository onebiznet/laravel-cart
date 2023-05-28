<?php 

namespace OneBiznet\LaravelCart\Contracts;

interface InstanceIdentifier 
{
    public function getInstanceIdentifier($options = null);

    public function getInstanceGlobalDiscount($options = null);
}