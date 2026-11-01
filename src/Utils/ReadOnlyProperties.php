<?php

declare(strict_types=1);

namespace Ondrejnov\EET\Utils;

/**
 * Exposes the private properties of a value object for reading only.
 */
trait ReadOnlyProperties
{
    public function __get(string $name)
    {
        if (!property_exists($this, $name)) {
            throw new \Error(sprintf('Undefined property %s::$%s', static::class, $name));
        }

        return $this->$name;
    }

    public function __isset(string $name): bool
    {
        return isset($this->$name);
    }

    public function __set(string $name, $value)
    {
        throw new \Error(sprintf('Cannot modify read-only property %s::$%s', static::class, $name));
    }

    public function __unset(string $name)
    {
        throw new \Error(sprintf('Cannot modify read-only property %s::$%s', static::class, $name));
    }
}
