<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#aa2643">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
        <link rel="apple-touch-icon" href="{{ asset('assets/brand/icon-180.png') }}">
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <title>{{ config('app.name') }}</title>
        <script>
            (() => {
                const defaults = { version: 1, theme: 'system', locale: 'pt-BR', fontScale: 'default', spacingScale: 'default', componentScale: 'default' };
                const allowed = {
                    theme: ['system', 'light', 'dark'],
                    locale: ['pt-BR', 'en', 'es', 'fr'],
                    scale: ['compact', 'default', 'comfortable'],
                };
                const key = 'rinos-one.visual-preferences.v1';
                let preferences = defaults;

                try {
                    const value = JSON.parse(window.localStorage.getItem(key) || 'null');

                    if (value?.version === defaults.version) {
                        preferences = {
                            version: defaults.version,
                            theme: allowed.theme.includes(value.theme) ? value.theme : defaults.theme,
                            locale: allowed.locale.includes(value.locale) ? value.locale : defaults.locale,
                            fontScale: allowed.scale.includes(value.fontScale) ? value.fontScale : defaults.fontScale,
                            spacingScale: allowed.scale.includes(value.spacingScale) ? value.spacingScale : defaults.spacingScale,
                            componentScale: allowed.scale.includes(value.componentScale) ? value.componentScale : defaults.componentScale,
                        };
                    }
                } catch {
                    // Preferências indisponíveis ou inválidas não impedem a primeira pintura.
                }

                const root = document.documentElement;
                root.dataset.theme = preferences.theme;
                root.dataset.fontScale = preferences.fontScale;
                root.dataset.spacingScale = preferences.spacingScale;
                root.dataset.componentScale = preferences.componentScale;
                root.lang = preferences.locale;
            })();
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
