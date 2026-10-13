<?php

namespace App\Support\Csp;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Preset;

class Policy implements Preset
{
    public function configure(\Spatie\Csp\Policy $policy): void
    {
        // The *current* user's Foundry. Config::first() returned whichever row
        // happened to be first in the table, so on the hosted instance one
        // arbitrary user's Foundry was allow-listed for everybody and every
        // other GM's images were blocked.
        $foundryUrl = auth()->user()?->config?->foundry_url ?? $this->embedParentOrigin();

        $policy
            ->add(Directive::SCRIPT, [
                Keyword::SELF,
                Keyword::UNSAFE_INLINE,
                Keyword::UNSAFE_EVAL,
                'https://unpkg.com',
                'https://unpkg.com/lucide.min.js.map',
                'https://unpkg.com/lucide@latest',
                'https://challenges.cloudflare.com',
                'https://storage.ko-fi.com',
            ])
            ->add(Directive::SCRIPT_ELEM, [
                Keyword::SELF,
                Keyword::UNSAFE_INLINE,
                Keyword::UNSAFE_EVAL,
                'https://unpkg.com/lucide@latest',
                'https://challenges.cloudflare.com',
                'https://storage.ko-fi.com',
            ])
            ->add(Directive::STYLE, [
                Keyword::SELF,
                Keyword::UNSAFE_INLINE,
                Keyword::UNSAFE_EVAL,
                'https://fonts.googleapis.com',
                'https://fonts.bunny.net',
                'https://cdn.jsdelivr.net',
                'https://fonts.cdnfonts.com',
                Keyword::UNSAFE_HASHES,

            ])
            ->add(Directive::FONT, [
                Keyword::SELF,
                'https://fonts.gstatic.com',
                'https://fonts.bunny.net',
                'https://cdn.jsdelivr.net',
                'https://fonts.cdnfonts.com',
            ])
            ->add(Directive::STYLE, [
                'https://storage.ko-fi.com',
            ])
            ->add(Directive::FRAME, [
                'https://challenges.cloudflare.com',
                'https://ko-fi.com',
            ])
            ->add(Directive::IMG, [
                Keyword::SELF,
                'data:',
                'https://storage.ko-fi.com',
                'https://ko-fi.com',
                $foundryUrl ? $foundryUrl : '',
            ])
            ->add(Directive::CONNECT, [
                Keyword::SELF,
                Keyword::UNSAFE_INLINE,
                Keyword::UNSAFE_EVAL,
                'ws:',
                'wss:',
                'https://unpkg.com/lucide.min.js.map',
                'https://challenges.cloudflare.com',
                'https://storage.ko-fi.com',
                'https://ko-fi.com',
            ]);
    }

    /**
     * The Foundry page framing /embed, taken from the Referer.
     *
     * The embed is auth-less, so there is no user config to read the Foundry
     * URL from, and img-src fell back to 'self' only: every item icon was
     * blocked and painted as a v-cpr-img colour block. The client already
     * trusts document.referrer as its parent origin (resources/js/embed/index.js),
     * and this is the same value seen server-side. It only widens img-src on a
     * page that holds no data until the parent sends it, so a forged Referer
     * gains nothing.
     */
    private function embedParentOrigin(): ?string
    {
        $request = request();

        if (! $request->routeIs('embed')) {
            return null;
        }

        $referer = parse_url((string) $request->headers->get('referer'));

        if (! $referer || ! in_array($referer['scheme'] ?? '', ['http', 'https'], true) || empty($referer['host'])) {
            return null;
        }

        // Origin only: scheme, host, port. Never echo a path or query into the header.
        if (! preg_match('/^[A-Za-z0-9.\-\[\]:]+$/', $referer['host'])) {
            return null;
        }

        return $referer['scheme'].'://'.$referer['host'].(isset($referer['port']) ? ':'.$referer['port'] : '');
    }
}
