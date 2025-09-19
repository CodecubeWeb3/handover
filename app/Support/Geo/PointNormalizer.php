<?php

namespace App\Support\Geo;

use Illuminate\Support\Arr;

class PointNormalizer
{
    /**
     * Normalise a stored point into latitude/longitude floats.
     *
     * @return array{lat: float, lng: float}|null
     */
    public static function normalize(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $lat = Arr::get($value, 'lat');
            $lng = Arr::get($value, 'lng');

            if (self::isCoordinate($lat) && self::isCoordinate($lng)) {
                return ['lat' => (float) $lat, 'lng' => (float) $lng];
            }
        }

        if (is_string($value) && $value !== '') {
            $trimmed = trim($value);

            if (str_starts_with($trimmed, '{')) {
                $decoded = json_decode($trimmed, true);

                if (is_array($decoded)) {
                    return self::normalize($decoded);
                }
            }

            if (preg_match('/POINT\(([-0-9\.]+) ([-0-9\.]+)\)/i', $trimmed, $matches)) {
                $lng = (float) $matches[1];
                $lat = (float) $matches[2];

                return ['lat' => $lat, 'lng' => $lng];
            }

            if (str_contains($trimmed, ',')) {
                [$lat, $lng] = array_map('trim', explode(',', $trimmed, 2));

                if (self::isCoordinate($lat) && self::isCoordinate($lng)) {
                    return ['lat' => (float) $lat, 'lng' => (float) $lng];
                }
            }
        }

        return null;
    }

    private static function isCoordinate(mixed $value): bool
    {
        return $value !== null && is_numeric($value);
    }
}