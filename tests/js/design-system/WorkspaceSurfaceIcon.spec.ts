import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import WorkspaceSurfaceIcon from '../../../resources/js/design-system/WorkspaceSurfaceIcon.vue';

describe('WorkspaceSurfaceIcon', () => {
    it.each(['overview', 'documents', 'attachments', 'cashflow', 'contacts', 'opportunities', 'items', 'invoices', 'ledger', 'performance', 'settings'])('renders the approved 48px source viewport for %s', (name) => {
        const wrapper = mount(WorkspaceSurfaceIcon, { props: { name } });

        expect(wrapper.get('svg').attributes()).toMatchObject({ width: '48', height: '48', viewBox: '0 0 48 48' });
        expect(wrapper.findAll('path').length).toBeGreaterThan(0);
    });

    it('uses the approved stronger stroke for performance indicators', () => {
        const wrapper = mount(WorkspaceSurfaceIcon, { props: { name: 'performance' } });

        expect(wrapper.get('svg').attributes('stroke-width')).toBe('4');
    });

    it('renders the approved raster asset for user settings', () => {
        const wrapper = mount(WorkspaceSurfaceIcon, { props: { name: 'rinoUser-tweek', size: 'lg' } });

        expect(wrapper.find('svg').exists()).toBe(false);
        expect(wrapper.get('img').attributes()).toMatchObject({ src: '/assets/icons/rinoUser-tweek_48.png', alt: '' });
        expect(wrapper.get('img').classes()).toContain('workspace-surface-icon--lg');
    });

    it('renders the approved raster asset for platform maintenance', () => {
        const wrapper = mount(WorkspaceSurfaceIcon, { props: { name: 'maintenance' } });

        expect(wrapper.find('svg').exists()).toBe(false);
        expect(wrapper.get('img').attributes()).toMatchObject({ src: '/assets/icons/maintenance_32.png', alt: '' });
    });

    it.each(['planet', 'holiday'])('renders the approved raster asset for %s', (name) => {
        const wrapper = mount(WorkspaceSurfaceIcon, { props: { name } });

        expect(wrapper.find('svg').exists()).toBe(false);
        expect(wrapper.get('img').attributes('src')).toBe(`/assets/icons/${name}_32.png`);
    });
});
