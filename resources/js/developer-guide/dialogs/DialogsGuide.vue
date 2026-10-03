<script setup lang="ts">
import UiButton from '../../design-system/UiButton.vue';
import { bugDialogIconNames, dialog, dialogModelPresentations, type DialogModel } from '../../design-system/dialog/dialogService';

const models: readonly DialogModel[] = ['warning', 'error', 'question', 'bug'];
const modelColors: Readonly<Record<DialogModel, string>> = { warning: 'Amarelo suave', error: 'Vermelho coral suave', question: 'Azul suave', bug: 'Vermelho vivo' };

type DialogExample = { title: string; message: string; buttons: ReadonlyArray<{ id: string; label: string; variant?: 'primary' | 'secondary' | 'destructive' }>; initialFocusActionId: string; escapeActionId: string };

const examples: Readonly<Record<DialogModel, DialogExample>> = {
    warning: { title: 'Alterações não salvas', message: 'Você possui alterações pendentes. Deseja continuar sem salvá-las?', buttons: [{ id: 'cancel', label: 'Cancelar', variant: 'secondary' }, { id: 'continue', label: 'Continuar' }], initialFocusActionId: 'cancel', escapeActionId: 'cancel' },
    error: { title: 'Não foi possível concluir', message: 'O serviço não respondeu como esperado. Tente novamente em alguns instantes.', buttons: [{ id: 'acknowledge', label: 'Entendi' }], initialFocusActionId: 'acknowledge', escapeActionId: 'acknowledge' },
    question: { title: 'Excluir pessoa?', message: 'A pessoa será removida desta organização. Esta ação não pode ser desfeita.', buttons: [{ id: 'cancel', label: 'Cancelar', variant: 'secondary' }, { id: 'delete', label: 'Excluir', variant: 'destructive' }], initialFocusActionId: 'cancel', escapeActionId: 'cancel' },
    bug: { title: 'Falha inesperada', message: 'Ocorreu um comportamento inesperado. Registre o contexto da operação antes de continuar.', buttons: [{ id: 'acknowledge', label: 'Entendi' }], initialFocusActionId: 'acknowledge', escapeActionId: 'acknowledge' },
};

async function show(model: DialogModel): Promise<void> {
    await dialog.open({ model, ...examples[model] });
}
</script>

<template>
    <article class="developer-guide-article">
        <header id="dialogs-overview" class="developer-guide-article__header" tabindex="-1">
            <p class="developer-guide-article__eyebrow">Feedback · decisão</p>
            <h1>Caixas de diálogo</h1>
            <p>Diálogos interrompem o fluxo para apresentar uma mensagem e exigir uma decisão explícita. Cada modelo tem uma barra de título forte, com ícone à esquerda, mensagem no corpo e ações centralizadas no rodapé. A aplicação os emite pelo serviço central <code>dialog</code>; <code>UiDialog</code> permanece como a base visual e de acessibilidade.</p>
        </header>

        <section id="dialogs-models" class="developer-guide-section" aria-labelledby="dialogs-models-title" tabindex="-1">
            <div class="developer-guide-section__heading"><h2 id="dialogs-models-title">Modelos visuais</h2><p>O modelo define ícone, cor e urgência visual. O escopo funcional detalhado de cada modelo será consolidado neste guia antes da adoção em telas de negócio.</p></div>
            <div class="developer-guide-table-wrap" tabindex="0">
                <table class="developer-guide-table">
                    <caption>Modelos disponíveis</caption>
                    <thead><tr><th scope="col">Modelo</th><th scope="col">Ícone</th><th scope="col">Barra de título</th><th scope="col">Demonstração</th></tr></thead>
                    <tbody>
                        <tr v-for="model in models" :key="model"><td><code>{{ dialogModelPresentations[model].label }}</code></td><td>{{ model === 'bug' ? `Aleatório: ${bugDialogIconNames.join(', ')} (48 px)` : `${model}_48.png` }}</td><td>{{ modelColors[model] }}</td><td><UiButton variant="secondary" @click="show(model)">Abrir {{ dialogModelPresentations[model].label }}</UiButton></td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="dialogs-behavior" class="developer-guide-section" aria-labelledby="dialogs-behavior-title" tabindex="-1">
            <div class="developer-guide-section__heading"><h2 id="dialogs-behavior-title">Botões, foco e Escape</h2><p>Cada chamada informa as ações possíveis e controla de forma declarativa o botão que recebe o foco e o resultado associado a Escape.</p></div>
            <ul class="developer-guide-rule-list">
                <li><code>initialFocusActionId</code> é obrigatório e deve referenciar um botão declarado.</li>
                <li><code>escapeActionId</code> é opcional. Quando ausente, Escape e o clique fora do diálogo são bloqueados.</li>
                <li>Para decisões irreversíveis, o foco inicial e Escape devem apontar para a alternativa segura — normalmente <code>Cancelar</code> — nunca para <code>Excluir</code>.</li>
                <li>Os botões usam exclusivamente as variantes documentadas no guia de Botões. Não há estilos locais de ação.</li>
                <li>A mensagem preserva quebras de linha. Ao alcançar o espaço disponível, o corpo recebe rolagem vertical e horizontal sem expandir o modal além da janela.</li>
            </ul>
        </section>

        <section id="dialogs-emission" class="developer-guide-section" aria-labelledby="dialogs-emission-title" tabindex="-1">
            <div class="developer-guide-section__heading"><h2 id="dialogs-emission-title">Emissão centralizada</h2><p>A chamada devolve uma promessa com o identificador da ação escolhida. O host global controla uma única fila de modais e não permite sobreposição.</p></div>
            <pre class="developer-guide-code"><code>import &#123; dialog &#125; from '@/design-system/dialog/dialogService';

const result = await dialog.open(&#123;
  model: 'question',
  title: 'Excluir pessoa?',
  message: 'Esta ação não pode ser desfeita.',
  buttons: [
    &#123; id: 'cancel', label: 'Cancelar', variant: 'secondary' &#125;,
    &#123; id: 'delete', label: 'Excluir', variant: 'destructive' &#125;,
  ],
  initialFocusActionId: 'cancel',
  escapeActionId: 'cancel',
&#125;);

if (result.actionId === 'delete') await deletePerson();</code></pre>
        </section>
    </article>
</template>
