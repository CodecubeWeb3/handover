<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class Point implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        // MySQL returns binary; transform in query (e.g. ST_AsText) if you need structured data.
        return $value;
    }

    public function set($model, string $key, $value, array $attributes)
    {
        // Expect [$lat, $lng]; callers should wrap writes with ST_SRID(POINT(:lng, :lat), 4326).
        return $value;
    }
}