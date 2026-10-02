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
