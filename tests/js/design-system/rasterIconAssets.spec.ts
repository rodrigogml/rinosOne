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
});
