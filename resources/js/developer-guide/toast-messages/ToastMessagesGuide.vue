<script setup lang="ts">
import UIRinoButton from '../../design-system/UIRinoButton.vue';
import { toast } from '../../design-system/toast/toastService';

function showSuccess(): void {
    toast.success('Pessoa salva com sucesso.');
}

function showInfo(): void {
    toast.info('A importação foi concluída e está disponível para revisão.');
}

function showSuccessQueue(): void {
    toast.success('Cadastro salvo com sucesso.');
    toast.success('Permissões atualizadas com sucesso.');
}
</script>

<template>
    <article class="developer-guide-article">
        <header id="toast-overview" class="developer-guide-article__header" tabindex="-1">
            <p class="developer-guide-article__eyebrow">Feedback · transitório</p>
            <h1>Toast messages</h1>
            <p>Toasts comunicam uma informação curta sem deslocar o foco da pessoa usuária. O Rinos One possui somente os tipos <code>success</code> e <code>info</code>; todos são emitidos pelo serviço central <code>toast</code>.</p>
        </header>

        <section id="toast-types" class="developer-guide-section" aria-labelledby="toast-types-title" tabindex="-1">
            <div class="developer-guide-section__heading">
                <h2 id="toast-types-title">Tipos, contexto e posição</h2>
                <p>O tipo expressa a origem da mensagem, e não o seu nível de urgência.</p>
            </div>
            <div class="developer-guide-table-wrap" tabindex="0">
                <table class="developer-guide-table">
                    <caption>Tipos permitidos</caption>
                    <thead><tr><th scope="col">Tipo</th><th scope="col">Ícone</th><th scope="col">Quando usar</th><th scope="col">Posição</th></tr></thead>
                    <tbody>
                        <tr><td><code>success</code></td><td><code>success_24.png</code></td><td>Uma ação direta solicitada pela pessoa usuária foi concluída pelo backend, como Salvar um cadastro.</td><td>Topo centralizado, a cerca de 30 px do limite superior.</td></tr>
                        <tr><td><code>info</code></td><td><code>info_24.png</code></td><td>Informação geral sem relação imediata com a ação atual, como término de processamento em segundo plano ou chegada de e-mail.</td><td>Topo direito, abaixo da topbar com intervalo de 12 px e margem lateral de cerca de 30 px.</td></tr>
                    </tbody>
                </table>
            </div>
            <aside class="developer-guide-note" role="note"><strong>Não há outros tipos de toast.</strong> Erros, advertências, validações e confirmações exigem um componente contextual — como <code>UiAlert</code> ou diálogo — e não devem ser convertidos em toasts.</aside>
        </section>

        <section id="toast-lifecycle" class="developer-guide-section" aria-labelledby="toast-lifecycle-title" tabindex="-1">
            <div class="developer-guide-section__heading">
                <h2 id="toast-lifecycle-title">Ciclo de vida e fila</h2>
                <p>A mensagem entra rolando para a posição final e sai com <em>fade out</em>. A transição de entrada e a de saída usam a mesma duração.</p>
            </div>
            <ul class="developer-guide-rule-list">
                <li>Após terminar a entrada, a toast permanece visível por pelo menos <strong>3 segundos</strong>.</li>
                <li>Movimento de ponteiro, teclado, roda de rolagem ou toque libera a saída. Se ocorreu antes dos 3 segundos, a saída espera o prazo mínimo.</li>
                <li>Um clique em qualquer parte da toast dispensa a mensagem imediatamente; não existe botão de fechar.</li>
                <li>Cada tipo mantém sua própria fila, pois sucesso e informação ocupam áreas distintas. A próxima mostra uma prévia de <strong>12 px</strong> na borda da tela, sem sobrepor a mensagem ativa.</li>
            </ul>
            <div class="developer-guide-showcase developer-guide-showcase--split">
                <div><h3>Sucesso</h3><UIRinoButton @click="showSuccess" variant="primary" label="rinoButtons.context.showSuccess" /></div>
                <div><h3>Informação</h3><UIRinoButton variant="secondary" @click="showInfo" label="rinoButtons.context.showInfo" /></div>
            </div>
            <div class="developer-guide-showcase__examples"><UIRinoButton variant="secondary" @click="showSuccessQueue" label="rinoButtons.context.successQueue" /></div>
        </section>

        <section id="toast-emission" class="developer-guide-section" aria-labelledby="toast-emission-title" tabindex="-1">
            <div class="developer-guide-section__heading">
                <h2 id="toast-emission-title">Emissão centralizada</h2>
                <p>Importe o serviço e emita somente o texto já pronto para apresentação. A UI recebe o evento e controla a fila, a permanência e as animações.</p>
            </div>
            <pre class="developer-guide-code"><code>import &#123; toast &#125; from '@/design-system/toast/toastService';

await savePerson(person);
toast.success('Pessoa salva com sucesso.');

toast.info('A importação terminou e está disponível para revisão.');</code></pre>
            <ul class="developer-guide-rule-list">
                <li>Emita <code>success</code> somente depois de a operação do backend resolver com êxito; não a use para informar apenas o início de uma solicitação.</li>
                <li>Escreva uma frase curta, objetiva e completa. A toast não deve conter ações, links, formulário ou conteúdo que precise de leitura prolongada.</li>
                <li>Não crie fila, temporizador, animação ou posicionamento localmente. O <code>ToastHost</code> é instalado uma vez na raiz da aplicação.</li>
            </ul>
        </section>
    </article>
</template>
