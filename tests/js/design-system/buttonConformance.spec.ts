import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import { parse } from '@vue/compiler-sfc';
import { parse as parseTemplate } from '@vue/compiler-dom';
import { describe, expect, it } from 'vitest';

function vueFiles(directory: string): string[] {
    return readdirSync(directory, { withFileTypes: true }).flatMap(entry => entry.isDirectory() ? vueFiles(join(directory, entry.name)) : entry.name.endsWith('.vue') ? [join(directory, entry.name)] : []);
}

describe('button conformance gate', () => {
    it('rejects retired components and direct HTML imitations of action buttons', () => {
        for (const file of vueFiles('resources/js')) {
            const source = readFileSync(file, 'utf8');
            expect(source, file).not.toMatch(/\b(?:UiButton|IconButton|UiCommandButton|UiListCommandButton)\b/);
            if (!file.endsWith('UIRinoButton.vue')) expect(source, file).not.toMatch(/<button\b[^>]*class=["'][^"']*\bui-(?:button|icon-button)\b/);
        }
    });

    it('keeps all live guide examples on the single public API without icon/text slots', () => {
        for (const file of vueFiles('resources/js/developer-guide')) {
            const template = parse(readFileSync(file, 'utf8')).descriptor.template;
            if (!template) continue;
            const ast = parseTemplate(template.content);
            function check(node: { type: number; tag?: string; children?: unknown[]; loc: { source: string } }): void {
                if (node.tag === 'UIRinoButton') expect(node.children, file).toHaveLength(0);
                for (const child of node.children ?? []) check(child as typeof node);
            }
            check(ast);
        }
    });
});
