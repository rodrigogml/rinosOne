/** Browser-only fixture: synthetic API data, no authenticated session or real mutations. */
import { createApp } from 'vue';
import axios from 'axios';
import DriveExplorer from '../../resources/js/drive/DriveExplorer.vue';
import { i18n } from '../../resources/js/i18n';
import '../../resources/css/app.css';

const capabilities = { read: true, edit: true, trash: true };
const usage = { workspaceBytes: 2048, systemManagedBytes: 0, trashBytes: 0, totalBytes: 2048 };
const folder = { id: 7, kind: 'folder', displayName: 'Documentos', parentFolderId: null, logicalSizeBytes: 1024, detectedMimeType: null, modifiedAt: '2026-10-03T12:00:00Z', capabilities };
const file = { ...folder, id: 8, kind: 'file', displayName: 'Relatório.pdf', detectedMimeType: 'application/pdf' };
const catalog = { drives: [{ target: { kind: 'personal', tenantId: null }, displayName: 'Meu Drive', category: 'PERSONAL', usage }, { target: { kind: 'tenant', tenantId: 42 }, displayName: 'Organização de teste', category: 'TENANT', usage }], sharedWithMe: { kind: 'shared-with-me', displayName: 'Compartilhados comigo' } };
axios.get = (async (url: string) => {
    if (url.endsWith('/catalog')) return { data: catalog };
    if (url.endsWith('/tree')) return { data: { folders: [folder] } };
    if (url.endsWith('/shared-with-me')) return { data: { folders: [], files: [] } };
    const nested = url.endsWith('/folders/7');
    const trash = url.endsWith('/trash');
    return { data: { location: { kind: nested ? 'folder' : trash ? 'trash' : 'root', id: nested ? 7 : null, displayName: nested ? 'Documentos' : trash ? 'Lixeira' : 'Meu Drive', parentFolderId: null }, breadcrumbs: nested ? [{ ...folder, kind: 'folder' }] : [], folders: nested || trash ? [] : [folder], files: trash ? [] : [file], capabilities, usage } };
}) as typeof axios.get;
axios.post = (async () => { throw new Error('Esta fixture não executa comandos de servidor.'); }) as typeof axios.post;
createApp(DriveExplorer, { surface: { id: 'fixture', destinationId: 'global.drive', scope: 'global', tenantId: null, titleKey: '', label: 'Rinos Drive', icon: 'drive', dirty: false, status: 'active' } }).use(i18n).mount('#drive-fixture');
