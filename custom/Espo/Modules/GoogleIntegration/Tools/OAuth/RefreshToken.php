<?php

namespace Espo\Modules\GoogleIntegration\Tools\OAuth;

/**
 * Google often omits refresh_token on reconnect / repeat consent.
 * Never persist an empty value — that would wipe the stored long-lived token.
 */
final class RefreshToken
{
    /**
     * @param array<string, mixed> $result Authorization-code or refresh response.
     */
    public static function fromAuthorizationResult(array $result): ?string
    {
        $refreshToken = $result['refresh_token'] ?? $result['refreshToken'] ?? null;

        if (!is_string($refreshToken) || $refreshToken === '') {
            return null;
        }

        return $refreshToken;
    }

    public static function shouldWrite(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }
}
