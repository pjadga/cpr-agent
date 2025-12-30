{{-- Save as resources/views/foundry-api-tester.blade.php --}}
@extends('welcome') {{-- Ensure your layout.app includes TailwindCSS and a CSRF token meta tag --}}

@section('content')
    <div id="app">
        <character-sheet></character-sheet>
    </div>
@endsection
