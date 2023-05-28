<?php

namespace OneBiznet\LaravelCart\Concerns;

trait HasEvents
{    
    /**
     * events
     *
     * @var mixed
     */
    protected $events;
    
    /**
     * Fire an event and call the listeners.
     *
     * @param object|string $event
     * @param mixed         $payload
     * @param bool          $halt
     *
     * @return null|array
     */
    protected function fireEvent($event, $payload = [], $halt = true)
    {
        return event($event, $payload, $halt);
    }
}
