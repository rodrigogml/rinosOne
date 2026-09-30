import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import { describe, expect, it, vi } from 'vitest';
import ResourceSharingPanel from '../../../resources/js/authorization/ResourceSharingPanel.vue';

vi.mock('axios');

describe('ResourceSharingPanel', () => {
    it('loads workspace responsibility and distinguishes direct from inherited sharing', async () => {
        vi.mocked(axios.get).mockResolvedValueOnce({ data: {
            workspaceResponsible: { type: 'USER', id: 1, displayName: 'Responsável pessoal' },
            shares: [
                { id: 2, resourceType: 'FOLDER', resourceId: 7, grantee: { subjectId: 3, subjectType: 'USER', displayName: 'Ana', accessSources: [], effectiveCapabilities: [], expiresAt: null }, relation: 'READ', origin: 'DIRECT', inheritedFrom: null },
                { id: 4, resourceType: 'FOLDER', resourceId: 7, grantee: { subjectId: 5, subjectType: 'USER', displayName: 'Bruno', accessSources: [], effectiveCapabilities: [], expiresAt: null }, relation: 'EDIT', origin: 'INHERITED', inheritedFrom: { resourceType: 'FOLDER', resourceId: 6 } },
            ],
        } });
        const wrapper = mount(ResourceSharingPanel, { props: { target: { kind: 'personal' }, folder: { id: 7, kind: 'folder', displayName: 'Projetos', parentFolderId: null, logicalSizeBytes: null, detectedMimeType: null, modifiedAt: null, capabilities: { read: true, edit: true, trash: true } } } });
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/api/v1/authorization/personal/resources/FOLDER/7/shares');
        expect(wrapper.text()).toContain('Responsável pessoal');
        expect(wrapper.text()).toContain('Acesso direto');
        expect(wrapper.text()).toContain('Acesso herdado da pasta #6');
    });
});
