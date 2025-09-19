<?php

namespace App\Domain\Bookings\Services;

use App\Models\Booking;
use App\Models\HandoverToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class PassTokenManager
{
    private const SECRET_BYTES = 20;
    private const TOTP_STEP_SECONDS = 30;
    private const OTP_DIGITS = 6;

    /**
     * Ensure a handover token exists and is fresh, returning payload details.
     *
     * @return array{token: HandoverToken, payload: array{token_id: int|null, leg: string, rotated_at: string, expires_at: string, otpauth_uri: string, qr_payload: string, secret: string, offline_pin: string, deeplink: string}}
     */
    public function ensurePayloadFor(Booking $booking, string $leg): array
    {
        $leg = $this->normaliseLeg($leg);

        $token = $booking->handoverTokens()->firstOrNew(['leg' => $leg]);

        if (! $token->exists) {
            $token->booking()->associate($booking);
            $token->created_at = now();
        }

        $secret = $this->decryptSecret($token);
        $offlinePin = $this->decryptOfflinePin($token);

        if ($this->tokenNeedsRotation($token, $secret, $offlinePin)) {
            [$token, $secret, $offlinePin] = $this->rotateToken($token);
        }

        return [
            'token' => $token,
            'payload' => $this->composePayload($booking, $token, $secret, $offlinePin),
        ];
    }

    /**
     * Force rotate an existing token.
     *
     * @return array{token: HandoverToken, payload: array{token_id: int|null, leg: string, rotated_at: string, expires_at: string, otpauth_uri: string, qr_payload: string, secret: string, offline_pin: string, deeplink: string}}
     */
    public function rotate(HandoverToken $token): array
    {
        [$token, $secret, $offlinePin] = $this->rotateToken($token);

        $booking = $token->relationLoaded('booking') ? $token->booking : $token->load('booking')->booking;

        return [
            'token' => $token,
            'payload' => $this->composePayload($booking, $token, $secret, $offlinePin),
        ];
    }

    public function verify(HandoverToken $token, string $code): bool
    {
        $normalized = preg_replace('/[^0-9]/', '', $code ?? '');

        if ($normalized === '') {
            return false;
        }

        $offlinePin = $this->decryptOfflinePin($token);
        if ($offlinePin && hash_equals($offlinePin, $normalized)) {
            return true;
        }

        $secret = $this->decryptSecret($token);

        if ($secret === null) {
            return false;
        }

        return $this->verifyTotp($secret, $normalized);
    }

    public function payloadForToken(Booking $booking, HandoverToken $token): array
    {
        $secret = $this->decryptSecret($token);
        $offlinePin = $this->decryptOfflinePin($token);

        if ($secret === null || $offlinePin === null) {
            [$token, $secret, $offlinePin] = $this->rotateToken($token);
        }

        return $this->composePayload($booking, $token, $secret, $offlinePin);
    }

    private function normaliseLeg(string $leg): string
    {
        $upper = strtoupper($leg);

        if (! in_array($upper, [HandoverToken::LEG_A, HandoverToken::LEG_B], true)) {
            throw new InvalidArgumentException("Unsupported handover leg '{$leg}'.");
        }

        return $upper;
    }

    private function tokenNeedsRotation(HandoverToken $token, ?string $secret, ?string $offlinePin): bool
    {
        if ($secret === null || $offlinePin === null) {
            return true;
        }

        if (! $token->rotated_at instanceof Carbon) {
            return true;
        }

        $rotationSeconds = (int) config('passes.rotation_seconds', 900);

        return $token->rotated_at->diffInSeconds(now()) >= $rotationSeconds;
    }

    /**
     * @return array{0: HandoverToken, 1: string, 2: string}
     */
    private function rotateToken(HandoverToken $token): array
    {
        $secret = $this->generateSecret();
        $offlinePin = $this->generateOfflinePin();

        $token->forceFill([
            'totp_secret_hash' => $this->encrypt($secret),
            'offline_pin_encrypted' => $this->encrypt($offlinePin),
            'rotated_at' => now(),
        ]);

        if ($token->created_at === null) {
            $token->created_at = now();
        }

        $token->save();

        return [$token, $secret, $offlinePin];
    }

    private function composePayload(Booking $booking, HandoverToken $token, string $secret, string $offlinePin): array
    {
        $rotatedAt = $token->rotated_at ?? now();
        $rotationSeconds = (int) config('passes.rotation_seconds', 900);
        $expiresAt = $rotatedAt->copy()->addSeconds($rotationSeconds);
        $otpauthUri = $this->buildOtpauthUri($booking, $token, $secret);

        return [
            'token_id' => $token->id,
            'leg' => $token->leg,
            'rotated_at' => $rotatedAt->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
            'otpauth_uri' => $otpauthUri,
            'qr_payload' => $otpauthUri,
            'secret' => $secret,
            'offline_pin' => $offlinePin,
            'deeplink' => $this->buildDeeplink($booking, $token),
        ];
    }

    private function buildOtpauthUri(Booking $booking, HandoverToken $token, string $secret): string
    {
        $issuer = config('passes.issuer', config('app.name', 'Safe Handover'));
        $label = rawurlencode(sprintf('%s:%s-%s', $issuer, $booking->id, $token->leg));
        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::OTP_DIGITS,
            'period' => self::TOTP_STEP_SECONDS,
        ]);

        return sprintf('otpauth://totp/%s?%s', $label, $query);
    }

    private function buildDeeplink(Booking $booking, HandoverToken $token): string
    {
        $base = rtrim(config('passes.deeplink_scheme', 'handover://booking'), '/');

        return sprintf('%s/%d/%s', $base, $booking->id, strtolower($token->leg));
    }

    private function encrypt(string $value): string
    {
        return Crypt::encryptString($value);
    }

    private function decryptSecret(HandoverToken $token): ?string
    {
        return $this->decrypt($token->totp_secret_hash);
    }

    private function decryptOfflinePin(HandoverToken $token): ?string
    {
        return $this->decrypt($token->offline_pin_encrypted);
    }

    private function decrypt(?string $payload): ?string
    {
        if (! $payload) {
            return null;
        }

        try {
            return Crypt::decryptString($payload);
        } catch (Throwable $exception) {
            Log::error('Failed to decrypt pass token payload.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(self::SECRET_BYTES));
    }

    private function generateOfflinePin(): string
    {
        return str_pad((string) random_int(0, 999999), self::OTP_DIGITS, '0', STR_PAD_LEFT);
    }

    private function verifyTotp(string $secret, string $code): bool
    {
        $window = (int) config('passes.totp_window', 1);
        $timestamp = time();

        for ($i = -$window; $i <= $window; $i++) {
            $candidate = $this->totpAt($secret, $timestamp + ($i * self::TOTP_STEP_SECONDS));

            if ($candidate !== null && hash_equals($candidate, $code)) {
                return true;
            }
        }

        return false;
    }

    private function totpAt(string $secret, int $timestamp): ?string
    {
        $binarySecret = $this->base32Decode($secret);

        if ($binarySecret === '') {
            return null;
        }

        $counter = intdiv($timestamp, self::TOTP_STEP_SECONDS);
        $high = ($counter >> 32) & 0xFFFFFFFF;
        $low = $counter & 0xFFFFFFFF;
        $binaryCounter = pack('NN', $high, $low);
        $hash = hash_hmac('sha1', $binaryCounter, $binarySecret, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $slice = substr($hash, $offset, 4);
        $value = unpack('N', $slice)[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % (10 ** self::OTP_DIGITS)), self::OTP_DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $binary): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';

        foreach (str_split($binary) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';

        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }

            $encoded .= $alphabet[bindec($chunk)];
        }

        return $encoded;
    }

    private function base32Decode(string $encoded): string
    {
        $alphabet = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
        $clean = strtoupper(preg_replace('/[^A-Z2-7]/', '', $encoded));
        $bits = '';

        foreach (str_split($clean) as $char) {
            if (! array_key_exists($char, $alphabet)) {
                return '';
            }

            $bits .= str_pad(decbin($alphabet[$char]), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $binary .= chr(bindec($chunk));
            }
        }

        return $binary;
    }
}