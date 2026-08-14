<?php

namespace App\Support\Csp;

use App\Models\Config;
use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Preset;

class Policy implements Preset
{
    public function configure(\Spatie\Csp\Policy $policy): void
    {
        $foundryUrl = Config::first()?->foundry_url;

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
}
