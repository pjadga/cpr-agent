<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // spatie/sheets (the /wiki renderer) only binds this if nothing already
        // has, registering it here first lets us add GFM tables/strikethrough/
        // autolinks, which the wiki markdown uses, on top of bare CommonMark.
        $this->app->singleton(CommonMarkConverter::class, function () {
            $converter = new CommonMarkConverter([
                'html_input' => 'strip',
                // The docs carry hand-written tables of contents linking to
                // #anchors, which need ids on the headings to resolve.
                'heading_permalink' => [
                    'apply_id_to_heading' => true,
                    'id_prefix' => '',
                    'insert' => 'none',
                    'min_heading_level' => 2,
                ],
            ]);

            $converter->getEnvironment()
                ->addExtension(new GithubFlavoredMarkdownExtension)
                ->addExtension(new HeadingPermalinkExtension);

            return $converter;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
