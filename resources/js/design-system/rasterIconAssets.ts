export type RasterIconSize = 'sm' | 'md' | 'lg';

const pixelsBySize: Record<RasterIconSize, 24 | 32 | 48> = { sm: 24, md: 32, lg: 48 };

/**
 * Registro único dos ícones PNG distribuídos pela interface.
 *
 * Para disponibilizar um novo ícone raster, publique as variantes 24, 32 e
 * 48 px no catálogo versionado e acrescente somente sua chave aqui. A rotina
 * ociosa de preload passa a incluí-lo automaticamente.
 */
export const registeredRasterIconNames = [
    'logout',
    'maintenance',
    'rinoUser',
    'rinoUser-tweek',
    'taskbar',
    'taskbar2',
    'theme',
    'tweek',
    'user',
] as const;

const registeredNames = new Set<string>(registeredRasterIconNames);
const requestedSources = new Set<string>();

export function isRegisteredRasterIcon(name: string): boolean {
    return registeredNames.has(name);
}

export function rasterIconSource(name: string, size: RasterIconSize): string | null {
    if (!isRegisteredRasterIcon(name)) return null;

    return `/assets/icons/${name}_${pixelsBySize[size]}.png`;
}

export function rasterIconSources(name: string): string[] {
    return (Object.keys(pixelsBySize) as RasterIconSize[])
        .map((size) => rasterIconSource(name, size))
        .filter((source): source is string => source !== null);
}

/** Carrega silenciosamente variantes de interface já registradas no cache do navegador. */
export function preloadRegisteredRasterIcons(): void {
    if (typeof Image === 'undefined') return;

    for (const name of registeredRasterIconNames) {
        for (const source of rasterIconSources(name)) {
            if (requestedSources.has(source)) continue;
            requestedSources.add(source);

            const image = new Image();
            image.decoding = 'async';
            image.src = source;
        }
    }
}

/** Agenda o preload somente após o navegador ficar ocioso depois da renderização inicial. */
export function scheduleRegisteredRasterIconPreload(): () => void {
    if (typeof window === 'undefined') return () => undefined;

    type IdleWindow = Window & {
        requestIdleCallback?: (callback: () => void, options?: { timeout: number }) => number;
        cancelIdleCallback?: (id: number) => void;
    };
    const idleWindow = window as IdleWindow;
    if (idleWindow.requestIdleCallback) {
        const id = idleWindow.requestIdleCallback(preloadRegisteredRasterIcons, { timeout: 2_000 });
        return () => idleWindow.cancelIdleCallback?.(id);
    }

    const id = window.setTimeout(preloadRegisteredRasterIcons, 250);
    return () => window.clearTimeout(id);
}
