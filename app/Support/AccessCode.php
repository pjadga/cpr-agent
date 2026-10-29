<?php

namespace App\Support;

/**
 * PINs and 3x3 patterns, which sign a user in on their own, like a phone's lock screen. Each must be
 * unique across accounts, so they're stored as a keyed hash (HMAC with the app key) that can be looked
 * up, not a salted one. Changing APP_KEY makes every stored PIN and pattern stop working; users then
 * sign in with their username and set them again.
 */
class AccessCode
{
    /** 4 to 12 digits. */
    public const PIN = '/^\d{4,12}$/';

    /** At least 4 of the 9 dots, numbered 1-9 row by row, each used once, joined by dashes. */
    public const PATTERN = '/^[1-9](-[1-9]){3,8}$/';

    public static function hash(string $kind, string $value): string
    {
        return hash_hmac('sha256', "{$kind}:{$value}", (string) config('app.key'));
    }

    public static function validPin(?string $pin): bool
    {
        return $pin !== null && preg_match(self::PIN, $pin) === 1;
    }

    public static function validPattern(?string $pattern): bool
    {
        if ($pattern === null || preg_match(self::PATTERN, $pattern) !== 1) {
            return false;
        }
        $dots = explode('-', $pattern);

        return count($dots) === count(array_unique($dots));
    }
}
