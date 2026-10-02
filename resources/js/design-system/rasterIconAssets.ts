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
    'drive',
    'driveDownload',
    'driveUpload',
    'dataDelete',
    'dataDuplicate',
    'dataEdit',
    'dataInsert',
    'dataView',
    'file',
    'fileDetails',
    'fileDetailPanel',
    'fileCleanSelection',
    'fileGrade',
    'fileList',
    'fileMove',
    'fileNewFolder',
    'fileRefresh',
    'fileSidePanel',
    'funnel',
    'fileSharedWithMe',
    'folderClose',
    'folderOpen',
    'holiday',
    'logout',
    'maintenance',
    'lamp-off',
    'lamp-on',
    'navBar',
    'personCompany',
    'padding-l',
    'padding-m',
    'padding-s',
    'planet',
    'object-size-g',
    'object-size-m',
    'object-size-s',
    'search',
    'secGroups',
    'secPermissions',
    'secRoles',
    'rinoUser',
    'rinoUser-tweek',
    'taskbar',
    'taskbar2',
    'tenant2',
    'tableCleanSelection',
    'tableColumns',
    'tableLockSelection',
    'tableShowHiddenSelected',
    'tableShowSelected',
    'theme',
    'themeTextBig',
    'themeTextNormal',
    'themeTextSmall',
    'trashBurn',
    'trashEmpty',
    'trashFull',
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
