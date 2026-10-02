<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#000000">
        <link rel="icon" href="{{ asset('favicon.ico?v=20260930') }}" sizes="32x32">
        <link rel="apple-touch-icon" href="{{ asset('assets/brand/icon-180.png?v=20260930') }}">
        <link rel="manifest" href="{{ asset('manifest.webmanifest?v=20260930') }}">
        <title>{{ config('app.name') }} · Guia de interface</title>
        @include('partials.visual-preferences-bootstrap')
        @vite(['resources/css/app.css', 'resources/js/developer-guide.ts'])
    </head>
    <body>
        <div id="developer-guide"></div>
    </body>
</html>
