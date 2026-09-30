import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import { createI18n } from 'vue-i18n';
import { describe, expect, it, vi } from 'vitest';
import ResourceSharingPanel from '../../../resources/js/authorization/ResourceSharingPanel.vue';

vi.mock('axios');

const i18n = createI18n({ legacy: false, locale: 'pt-BR', messages: { 'pt-BR': { access: { authorization: { sharing: {
    folderSharing: 'Compartilhamento de pasta · {workspace}', personalWorkspace: 'Workspace pessoal', tenantWorkspace: 'Workspace da organização', close: 'Fechar compartilhamento', workspaceResponsible: 'Responsável pelo workspace: {name}.', loading: 'Carregando compartilhamentos…', loadFailed: 'Não foi possível carregar os compartilhamentos desta pasta.', newShare: 'Novo compartilhamento', recipient: 'Destinatário', recipientFound: 'Destinatário encontrado', selectRecipient: 'Selecione uma pessoa', accessLevel: 'Nível de acesso', read: 'Leitura', edit: 'Edição', confirm: 'Confirmar compartilhamento', empty: 'Esta pasta não possui compartilhamentos visíveis.', direct: 'Acesso direto', inheritedFrom: 'Acesso herdado da pasta #{resourceId}', change: 'Alterar', revoke: 'Revogar', newAccessLevel: 'Novo nível de acesso', confirmChange: 'Confirmar alteração', cancel: 'Cancelar', revokeQuestion: 'Revogar o acesso direto selecionado nesta pasta?', confirmRevoke: 'Confirmar revogação', reload: 'Recarregar', created: 'Compartilhamento criado.', updated: 'Compartilhamento atualizado.', revoked: 'Compartilhamento revogado.', stale: 'O contexto foi alterado. Recarregue os compartilhamentos antes de confirmar novamente.', conflict: 'Este compartilhamento não pode mais ser alterado nesta pasta; ele pode ser herdado ou ter sido modificado.', commandFailed: 'Não foi possível concluir a alteração. Atualize os dados e tente novamente.', offline: 'Você está sem conexão. Reconecte-se antes de alterar compartilhamentos.',
} } } } } });
const panel = (props: InstanceType<typeof ResourceSharingPanel>['$props'], attachTo?: HTMLElement) => mount(ResourceSharingPanel, { attachTo, props, global: { plugins: [i18n] } });

describe('ResourceSharingPanel', () => {
    it('loads workspace responsibility and distinguishes direct from inherited sharing', async () => {
        vi.mocked(axios.get).mockResolvedValueOnce({ data: {
            workspaceResponsible: { type: 'USER', id: 1, displayName: 'Responsável pessoal' },
            shares: [
                { id: 2, resourceType: 'FOLDER', resourceId: 7, grantee: { subjectId: 3, subjectType: 'USER', displayName: 'Ana', accessSources: [], effectiveCapabilities: [], expiresAt: null }, relation: 'READ', origin: 'DIRECT', inheritedFrom: null },
                { id: 4, resourceType: 'FOLDER', resourceId: 7, grantee: { subjectId: 5, subjectType: 'USER', displayName: 'Bruno', accessSources: [], effectiveCapabilities: [], expiresAt: null }, relation: 'EDIT', origin: 'INHERITED', inheritedFrom: { resourceType: 'FOLDER', resourceId: 6 } },
            ],
        } });
        const wrapper = panel({ target: { kind: 'personal' }, folder: { id: 7, displayName: 'Projetos' } });
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/api/v1/authorization/personal/resources/FOLDER/7/shares');
        expect(wrapper.text()).toContain('Responsável pessoal');
        expect(wrapper.text()).toContain('Acesso direto');
        expect(wrapper.text()).toContain('Acesso herdado da pasta #6');
    });

    it('searches by name and confirms a direct sharing command with the contextual version', async () => {
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { workspaceResponsible: { type: 'TENANT', id: 9, displayName: 'Empresa' }, contextVersion: '12', shares: [] } })
            .mockResolvedValueOnce({ data: { recipients: [{ subjectId: 4, displayName: 'Ana' }] } })
            .mockResolvedValueOnce({ data: { workspaceResponsible: { type: 'TENANT', id: 9, displayName: 'Empresa' }, contextVersion: '13', shares: [] } });
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { share: {} } });
        const wrapper = panel({ target: { kind: 'tenant', tenantId: 9 }, folder: { id: 7, displayName: 'Projetos' } });
        await flushPromises();

        await wrapper.get('#share-recipient-query').setValue('Ana'); await flushPromises();
        await wrapper.get('[aria-label="Destinatário encontrado"]').setValue('4');
        await wrapper.get('.resource-sharing-panel__create .ui-button').trigger('click'); await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/api/v1/tenants/9/authorization/share-recipients?query=Ana');
        expect(axios.post).toHaveBeenCalledWith('/api/v1/tenants/9/authorization/resources/FOLDER/7/shares', { subjectId: 4, relation: 'READ', expectedContextVersion: '12' });
    });

    it('opens as a labelled dialog and moves keyboard focus to its heading', async () => {
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { workspaceResponsible: { type: 'USER', id: 1, displayName: 'Responsável' }, shares: [] } });
        const wrapper = panel({ target: { kind: 'personal' }, folder: { id: 7, displayName: 'Projetos' } }, document.body);
        await flushPromises();

        expect(wrapper.attributes('role')).toBe('dialog');
        expect(document.activeElement).toBe(wrapper.get('#resource-sharing-title').element);
        wrapper.unmount();
    });

    it('returns focus to the triggering share action after closing', async () => {
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { workspaceResponsible: { type: 'USER', id: 1, displayName: 'Responsável' }, shares: [] } });
        const opener = document.createElement('button'); document.body.append(opener); opener.focus();
        const wrapper = panel({ target: { kind: 'personal' }, folder: { id: 7, displayName: 'Projetos' } }, document.body);
        await flushPromises();

        await wrapper.get('header button').trigger('click'); await flushPromises();
        expect(document.activeElement).toBe(opener);
        wrapper.unmount(); opener.remove();
    });
});
