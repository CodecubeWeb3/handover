<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use InvalidArgumentException;

class FeatureFlags
{
    public const ENABLE_WAITLIST = 'enable_waitlist';
    public const ENABLE_SHARED_PAY = 'enable_shared_pay';
    public const ENABLE_PHOTO_PROOF = 'enable_photo_proof';
    public const REQUIRE_WEBAUTHN_AGENT = 'require_webauthn_agent';
    public const ENABLE_TRAVEL_STIPEND = 'enable_travel_stipend';
    public const BUFFER_MINUTES = 'buffer_minutes';
    public const GEOFENCE_RADIUS_M = 'geofence_radius_m';
    public const SLOT_MINUTES = 'slot_minutes';
    public const ENABLE_WALLET_PASSES = 'enable_wallet_passes';
    public const LATE_FEE_BASE = 'late_fee_base';
    public const LATE_FEE_PER_MIN = 'late_fee_per_min';
    public const PLATFORM_PCT = 'platform_pct';
    public const MIN_CAPTURE_PCT_IF_NO_SHOW = 'min_capture_pct_if_no_show';

    private const CACHE_KEY = 'feature-flags::values';

    private ConfigRepository $config;

    private CacheRepository $cache;

    public function __construct(ConfigRepository $config, CacheFactory $cache)
    {
        $this->config = $config;
        $this->cache = $cache->store($config->get('feature-flags.cache_store'));
    }

    public function refresh(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    public function enabled(string $flag): bool
    {
        return (bool) $this->value($flag);
    }

    public function value(string $flag): mixed
    {
        $flags = $this->all();

        if (! array_key_exists($flag, $flags)) {
            throw new InvalidArgumentException("Unknown feature flag [{$flag}].");
        }

        return $flags[$flag];
    }

    public function waitlistEnabled(): bool
    {
        return $this->enabled(self::ENABLE_WAITLIST);
    }

    public function sharedPayEnabled(): bool
    {
        return $this->enabled(self::ENABLE_SHARED_PAY);
    }

    public function photoProofEnabled(): bool
    {
        return $this->enabled(self::ENABLE_PHOTO_PROOF);
    }

    public function operativeWebauthnRequired(): bool
    {
        return $this->enabled(self::REQUIRE_WEBAUTHN_AGENT);
    }

    public function travelStipendEnabled(): bool
    {
        return $this->enabled(self::ENABLE_TRAVEL_STIPEND);
    }

    public function bufferMinutes(): int
    {
        return (int) $this->value(self::BUFFER_MINUTES);
    }

    public function geofenceRadiusMeters(): int
    {
        return (int) $this->value(self::GEOFENCE_RADIUS_M);
    }

    public function slotMinutes(): int
    {
        return (int) $this->value(self::SLOT_MINUTES);
    }

    public function walletPassesEnabled(): bool
    {
        return $this->enabled(self::ENABLE_WALLET_PASSES);
    }

    public function lateFeeBaseMinor(): int
    {
        return (int) $this->value(self::LATE_FEE_BASE);
    }

    public function lateFeePerMinuteMinor(): int
    {
        return (int) $this->value(self::LATE_FEE_PER_MIN);
    }

    public function platformSharePercent(): float
    {
        return (float) $this->value(self::PLATFORM_PCT);
    }

    public function minimumCapturePercentOnNoShow(): float
    {
        return (float) $this->value(self::MIN_CAPTURE_PCT_IF_NO_SHOW);
    }

    private function all(): array
    {
        return $this->cache->rememberForever(self::CACHE_KEY, function (): array {
            $flags = $this->config->get('feature-flags.flags', []);

            return array_map(fn ($value) => $this->cast($value), $flags);
        });
    }

    private function cast(mixed $value): mixed
    {
        if (is_bool($value) || $value === null) {
            return $value;
        }

        if (is_numeric($value)) {
            return str_contains((string) $value, '.')
                ? (float) $value
                : (int) $value;
        }

        $normalized = strtolower((string) $value);

        $boolean = filter_var($normalized, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($boolean !== null) {
            return $boolean;
        }

        return $value;
    }
}
