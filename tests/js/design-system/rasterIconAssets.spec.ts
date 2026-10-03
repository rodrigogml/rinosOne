import { describe, expect, it } from 'vitest';
import { isRegisteredRasterIcon, rasterIconSource, rasterIconSources } from '../../../resources/js/design-system/rasterIconAssets';

describe('raster icon assets', () => {
    it('resolves every display-size variant for registered interface icons', () => {
        expect(rasterIconSources('drive')).toEqual([
            '/assets/icons/drive_24.png',
            '/assets/icons/drive_32.png',
            '/assets/icons/drive_48.png',
        ]);
    });

    it('does not turn unknown semantic SVG keys into raster requests', () => {
        expect(isRegisteredRasterIcon('contacts')).toBe(false);
        expect(rasterIconSource('contacts', 'md')).toBeNull();
    });

    it('registers the security icons used by permission management', () => {
        expect(rasterIconSource('secPermissions', 'lg')).toBe('/assets/icons/secPermissions_48.png');
        expect(rasterIconSource('secRoles', 'sm')).toBe('/assets/icons/secRoles_24.png');
        expect(rasterIconSource('secGroups', 'sm')).toBe('/assets/icons/secGroups_24.png');
    });

    it('registers the dedicated shared-with-me Drive icon', () => {
        expect(rasterIconSource('fileSharedWithMe', 'md')).toBe('/assets/icons/fileSharedWithMe_32.png');
    });

    it('registers the file deletion and restoration icons used by Drive actions', () => {
        expect(rasterIconSource('fileDelete', 'sm')).toBe('/assets/icons/fileDelete_24.png');
        expect(rasterIconSource('fileRestore', 'md')).toBe('/assets/icons/fileRestore_32.png');
    });
});
