<?php

namespace App\Support\Csp;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Preset;
use function Symfony\Component\String\s;

class Policy implements Preset
{
    public function configure(\Spatie\Csp\Policy $policy): void
    {
        $policy
            ->add(Directive::SCRIPT, [
                Keyword::SELF,
                Keyword::UNSAFE_INLINE,
                Keyword::UNSAFE_EVAL,
                'https://unpkg.com',
                'https://unpkg.com/lucide.min.js.map',
                'https://unpkg.com/lucide@latest'
            ])
            ->add(Directive::SCRIPT_ELEM, [
                Keyword::SELF,
                Keyword::UNSAFE_INLINE,
                Keyword::UNSAFE_EVAL,
                'https://unpkg.com/lucide@latest'
            ])
            ->add(Directive::STYLE, [
                Keyword::SELF,
                'https://fonts.googleapis.com',
                'https://fonts.bunny.net',
            ])
            ->add(Directive::FONT, [
                Keyword::SELF,
                'https://fonts.gstatic.com',
                'https://fonts.bunny.net',
            ])
            ->add(Directive::IMG, [
                Keyword::SELF,
                'data:',
            ])
            ->add(Directive::CONNECT, [
                Keyword::SELF,
                Keyword::UNSAFE_INLINE,
                'ws:',
                'wss:',
                'https://unpkg.com/lucide.min.js.map'
            ])
            ->addNonce(Directive::SCRIPT)
            ->addNonce(Directive::STYLE);
    }
}
