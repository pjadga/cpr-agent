<?php

namespace App\Support;

/**
 * Plain-language explanations for the ways the Agent can fail to reach the rest
 * of its own stack.
 *
 * There are three moving parts behind every character sheet, and when one is
 * missing the raw failure says nothing useful: "Request failed with status code
 * 500" is true of a dead Reverb, a closed Foundry and a blank config alike.
 * Each code here names which part is missing and what to do about it.
 *
 * The browser has a matching catalogue in
 * resources/js/composables/useFoundryDiagnostics.js. The server sends only the
 * code; the sheet renders its own copy of the text. This file exists for the
 * server-rendered settings banner, which has no JavaScript to lean on and is
 * the page people land on precisely when things are broken.
 */
class ConnectionDiagnostics
{
    /**
     * @var array<string, array{title: string, detail: string, hint: string}>
     */
    private const CODES = [
        'realtime_unavailable' => [
            'title' => 'Live updates are offline',
            'detail' => 'The Agent could not reach its own realtime server (Reverb), so nothing can be sent to or received from Foundry.',
            'hint' => 'Reverb runs alongside the web server and is not started by opening the site. Start it with `php artisan agent:serve` (or `ddev agent-serve`), leave it running, then reload this page.',
        ],
        'foundry_not_configured' => [
            'title' => 'No Foundry address saved yet',
            'detail' => 'The Agent does not know where your Foundry is, so it has nothing to talk to.',
            'hint' => 'Set your Foundry URL below, then paste a setup code into the Cyberpunk Red Agent Bridge module in Foundry. Players do not need to do this; they join with a code from their GM.',
        ],
        'foundry_no_response' => [
            'title' => 'Foundry did not answer',
            'detail' => 'The request reached the realtime server but nothing in Foundry replied, so the Bridge is not listening.',
            'hint' => 'Open your Foundry world and check the Cyberpunk Red Agent Bridge module is enabled and shows "Bridge: Online". If it says offline, re-paste your setup code: the API key changes every time you generate a new one.',
        ],
    ];

    /**
     * @return array{title: string, detail: string, hint: string}
     */
    public static function get(?string $code): array
    {
        return self::CODES[$code] ?? [
            'title' => 'Something went wrong',
            'detail' => 'An unexpected error occurred.',
            'hint' => 'Reload the page and try again.',
        ];
    }

    public static function has(?string $code): bool
    {
        return $code !== null && array_key_exists($code, self::CODES);
    }
}
