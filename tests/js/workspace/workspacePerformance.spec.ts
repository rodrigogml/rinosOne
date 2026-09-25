import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';
import { useWorkspaceStore } from '../../../resources/js/workspace/workspaceStore';
import type { WorkspaceDestination } from '../../../resources/js/workspace/workspaceTypes';

function destination(id: string): WorkspaceDestination {
    return { id, scope: 'personal', category: 'workspace', titleKey: 'access.workspace.title', icon: id, createSurface: () => ({ titleKey: 'access.workspace.title', icon: id }) };
}

describe('workspace local interaction performance', () => {
    beforeEach(() => setActivePinia(createPinia()));

    it('switches taskbar surfaces within the one-second local interaction budget', () => {
        const workspace = useWorkspaceStore();
        const first = workspace.openDestination(destination('first'), { tenantId: null })!;
        const second = workspace.openDestination(destination('second'), { tenantId: null })!;
        const samples: number[] = [];

        for (let index = 0; index < 100; index += 1) {
            const startedAt = performance.now();
            workspace.activateSurface(index % 2 === 0 ? first.id : second.id);
            samples.push(performance.now() - startedAt);
        }

        const p95 = samples.sort((left, right) => left - right)[Math.ceil(samples.length * 0.95) - 1]!;
        expect(p95).toBeLessThan(1000);
    });
});
