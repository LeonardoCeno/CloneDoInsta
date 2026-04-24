<?php

namespace App\Http\Resources\Concerns;

trait HasPreloadedAttribute
{
    protected function preloadedBool(string $attribute, callable $fallback): bool
    {
        if (array_key_exists($attribute, $this->resource->getAttributes())) {
            return (bool) $this->resource->getAttribute($attribute);
        }

        return (bool) $fallback();
    }
}
