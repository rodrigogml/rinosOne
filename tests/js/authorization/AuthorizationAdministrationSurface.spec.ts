import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createI18n } from 'vue-i18n';
import { nextTick } from 'vue';
import AuthorizationAdministrationSurface from '../../../resources/js/authorization/AuthorizationAdministrationSurface.vue';
import AdvancedAuthorizationControls from '../../../resources/js/authorization/AdvancedAuthorizationControls.vue';

vi.mock('axios');
const surface = { id: 'authorization-1', destinationId: 'tenant.authorization-administration', scope: 'tenant' as const, tenantId: 18, titleKey: 'access.authorization.title', label: 'Segurança', icon: 'settings', dirty: false, status: 'active' as const };
const i18n = createI18n({ legacy: false, locale: 'pt-BR', messages: { 'pt-BR': { access: { authorization: { loading: 'Carregando', offline: 'Offline', requestFailed: 'Falhou', accessDenied: 'Negado', stale: 'Desatualizado', reload: 'Recarregar', filter: 'Filtrar', roleAssigned: 'Papel associado', assignRole: 'Associar papel', confirm: 'Confirmar', cancel: 'Cancelar', audit: 'Auditoria', emptyAudit: 'Nenhum evento', occurredAt: 'Ocorrido em', operation: 'Operação', target: 'Alvo', lastAdministrator: 'Último administrador', contextual: { noExpiration: 'Sem expiração', peopleAndIdentities: 'Pessoas e identidades', searchSubject: 'Buscar pessoa ou identidade', emptySubjects: 'Nenhuma pessoa ou identidade disponível neste contexto.', serviceIdentity: 'Identidade de serviço', person: 'Pessoa', viewAccess: 'Ver acessos', participantPagination: 'Paginação de participantes', subjectCanDo: 'O que {name} pode fazer?', accessValidity: 'Vigência do acesso: {value}.', whyAccess: 'Por que este acesso existe?', emptySources: 'Nenhuma fonte adicional de acesso foi identificada.', openSharing: 'Abrir compartilhamento', effectiveCapabilities: 'Capacidades efetivas', emptyCapabilities: 'Nenhuma capacidade efetiva disponível.' } } } } } });
const context = { scope: 'TENANT', tenantId: 18, displayName: 'Empresa', workspaceKind: 'TENANT', capabilities: { canReadAccess: true, canManageRoles: true, canManageSharing: true, canUseAdvancedControls: false } };
const subject = { subjectId: 44, subjectType: 'USER', displayName: 'Ana', accessSources: [], effectiveCapabilities: [], expiresAt: null };
const subjectWithSources = { ...subject, accessSources: [{ type: 'GROUP', displayName: 'Financeiro', scope: 'TENANT', expiresAt: '2026-10-01T10:00:00Z' }, { type: 'DIRECT_GRANT', displayName: 'Acesso excepcional', scope: 'TENANT', expiresAt: null }], expiresAt: '2026-10-02T10:00:00Z' };
const role = { id: 7, catalogType: 'ROLE', key: 'tenant.viewer', displayName: 'Leitor', description: 'Consulta dados.', scope: 'TENANT', systemManaged: false, active: true };

function mountSurface() { return mount(AuthorizationAdministrationSurface, { props: { surface }, global: { plugins: [i18n] } }); }
function contextualLoad(includeAudit = false) { const request = vi.mocked(axios.get).mockResolvedValueOnce({ data: { context, contextVersion: '3' } }).mockResolvedValueOnce({ data: { subjects: [subject], pagination: { page: 1, lastPage: 1 } } }).mockResolvedValueOnce({ data: { roles: [role] } }).mockResolvedValueOnce({ data: { groups: [] } }); if (includeAudit) request.mockResolvedValueOnce({ data: { events: [] } }); }

describe('AuthorizationAdministrationSurface', () => {
    beforeEach(() => { vi.resetAllMocks(); contextualLoad(true); });

    it('loads a route-derived context and presents subjects by name without technical ID fields', async () => {
        const wrapper = mountSurface(); await flushPromises();
        expect(axios.get).toHaveBeenCalledWith('/api/v1/tenants/18/authorization/context');
        expect(wrapper.text()).toContain('Empresa');
        expect(wrapper.text()).toContain('Ana');
        expect(wrapper.find('#authorization-assignment-user').exists()).toBe(false);
        expect(wrapper.find('#authorization-assignment-role').exists()).toBe(false);
    });

    it('moves focus to the contextual heading when the surface opens', async () => {
        const wrapper = mount(AuthorizationAdministrationSurface, { attachTo: document.body, props: { surface }, global: { plugins: [i18n] } });
        await nextTick();

        expect(document.activeElement).toBe(wrapper.get('.authorization-administration__context h2').element);
        wrapper.unmount();
    });

    it('mounts advanced controls only after the context authorizes them', async () => {
        vi.resetAllMocks();
        const advancedContext = { ...context, capabilities: { ...context.capabilities, canUseAdvancedControls: true } };
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { context: advancedContext, contextVersion: '3' } }).mockResolvedValueOnce({ data: { subjects: [], pagination: { page: 1, lastPage: 1 } } }).mockResolvedValueOnce({ data: { roles: [] } }).mockResolvedValueOnce({ data: { groups: [] } }).mockResolvedValueOnce({ data: { events: [] } }).mockResolvedValueOnce({ data: { accessRequests: [] } });
        const wrapper = mountSurface(); await flushPromises();

        expect(wrapper.findComponent(AdvancedAuthorizationControls).exists()).toBe(true);
        expect(wrapper.get('details.authorization-administration__advanced-disclosure').attributes('open')).toBeUndefined();
    });

    it('reads effective access through the selected subject type and identifier', async () => {
        const wrapper = mountSurface(); await flushPromises();
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { effectiveAccess: { subject: subjectWithSources, effectiveCapabilities: ['tenant.people.read'] } } });
        await wrapper.get('.authorization-administration__subject-list .ui-button').trigger('click'); await flushPromises();
        expect(axios.get).toHaveBeenCalledWith('/api/v1/tenants/18/authorization/subjects/USER/44/effective-access');
        expect(wrapper.text()).toContain('tenant.people.read');
        expect(wrapper.text()).toContain('Financeiro');
        expect(wrapper.text()).toContain('Acesso excepcional');
        expect(wrapper.text()).toContain('TENANT');
    });

    it('opens a folder sharing panel only from a server-provided contextual resource reference', async () => {
        const wrapper = mountSurface(); await flushPromises();
        const sharedSubject = { ...subject, accessSources: [{ type: 'SHARE', displayName: 'Pasta compartilhada diretamente', scope: 'TENANT', expiresAt: null, resource: { resourceType: 'FOLDER', resourceId: 31 } }] };
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { effectiveAccess: { subject: sharedSubject, effectiveCapabilities: [] } } }).mockResolvedValueOnce({ data: { workspaceResponsible: { type: 'TENANT', id: 18, displayName: 'Empresa' }, shares: [] } });
        await wrapper.get('.authorization-administration__subject-list .ui-button').trigger('click'); await flushPromises();
        await wrapper.get('.authorization-administration__result .ui-button').trigger('click'); await flushPromises();

        expect(axios.get).toHaveBeenLastCalledWith('/api/v1/tenants/18/authorization/resources/FOLDER/31/shares');
        expect(wrapper.text()).toContain('Pasta compartilhada');
    });

    it('confirms an assignment using the selected role and contextual version', async () => {
        const wrapper = mountSurface(); await flushPromises();
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { effectiveAccess: { subject, effectiveCapabilities: [] } } });
        await wrapper.get('.authorization-administration__subject-list .ui-button').trigger('click'); await flushPromises();
        await wrapper.findAll('select')[1]!.setValue(String(role.id));
        await wrapper.findAll('.authorization-administration__form')[1]!.get('.ui-button').trigger('click');
        expect(wrapper.text()).toContain('Associar');
        contextualLoad(); vi.mocked(axios.post).mockResolvedValueOnce({ data: { assignment: { id: 1 } } });
        await wrapper.get('.authorization-administration__confirmation .ui-button').trigger('click'); await flushPromises();
        expect(axios.post).toHaveBeenCalledWith('/api/v1/tenants/18/authorization/roles/7/assignments', { subjectType: 'USER', subjectId: 44, expectedContextVersion: '3' });
        expect(wrapper.text()).toContain('Papel associado');
    });

    it('exposes an accessible loading state while contextual data is pending', async () => {
        vi.resetAllMocks();
        vi.mocked(axios.get).mockReturnValue(new Promise(() => {}) as never);
        const wrapper = mountSurface();
        await nextTick();

        expect(wrapper.attributes('aria-busy')).toBe('true');
    });

    it('communicates an empty contextual directory without suggesting another context', async () => {
        vi.resetAllMocks();
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { context, contextVersion: '3' } }).mockResolvedValueOnce({ data: { subjects: [], pagination: { page: 1, lastPage: 1 } } }).mockResolvedValueOnce({ data: { roles: [role] } }).mockResolvedValueOnce({ data: { groups: [] } }).mockResolvedValueOnce({ data: { events: [] } });
        const wrapper = mountSurface(); await flushPromises();

        expect(wrapper.text()).toContain('Nenhuma pessoa ou identidade disponível neste contexto.');
    });

    it('sends contextual audit filters without changing the active scope', async () => {
        const wrapper = mountSurface(); await flushPromises();
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { events: [] } });
        await wrapper.get('#authorization-audit-operation').setValue('ROLE_ASSIGNED');
        await wrapper.get('#authorization-audit-target-type').setValue('ROLE');
        await wrapper.get('#authorization-audit-target-id').setValue('7');
        await wrapper.get('.authorization-administration__audit-filters').trigger('submit'); await flushPromises();

        expect(axios.get).toHaveBeenLastCalledWith('/api/v1/tenants/18/authorization/audit-events/contextual?perPage=25&operation=ROLE_ASSIGNED&targetType=ROLE&targetId=7');
    });

    it('communicates access denial and stale information safely', async () => {
        const wrapper = mountSurface(); await flushPromises();
        vi.mocked(axios.isAxiosError).mockReturnValue(true);
        vi.mocked(axios.get).mockRejectedValueOnce({ isAxiosError: true, response: { status: 403 } });
        await wrapper.findAll('.authorization-administration__form')[0]!.get('.ui-button').trigger('click'); await flushPromises();
        expect(wrapper.text()).toContain('Negado');
        expect(wrapper.emitted('accessDenied')).toHaveLength(1);

        vi.mocked(axios.get).mockRejectedValueOnce({ isAxiosError: true, response: { status: 412 } });
        await wrapper.findAll('.authorization-administration__form')[0]!.get('.ui-button').trigger('click'); await flushPromises();
        expect(wrapper.text()).toContain('Desatualizado');
    });

    it('keeps mutations disabled while the browser is offline', async () => {
        const online = vi.spyOn(window.navigator, 'onLine', 'get').mockReturnValue(false);
        const wrapper = mountSurface(); await flushPromises();

        expect(wrapper.text()).toContain('Offline');
        expect(wrapper.findAll('.authorization-administration__form')[1]!.get('.ui-button').attributes('disabled')).toBeDefined();
        online.mockRestore();
    });
});
