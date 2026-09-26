<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use Spatie\Sheets\Facades\Sheets;
use Symfony\Component\HttpFoundation\Response;

class WikiController extends Controller
{
    public function index(): Response
    {
        $first = Sheets::collection('wiki')->all()->sortBy('order')->first();

        return redirect()->route('wiki.show', $first->slug);
    }

    public function show(string $slug): View
    {
        $pages = Sheets::collection('wiki')->all()->sortBy('order')->values();

        $current = $pages->search(fn ($sheet) => $sheet->slug === $slug);

        abort_if($current === false, 404);

        return view('wiki.show', [
            'pages' => $pages,
            'page' => $pages[$current],
            'previous' => $pages->get($current - 1),
            'next' => $pages->get($current + 1),
        ]);
    }
}
