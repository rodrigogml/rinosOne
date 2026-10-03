<script setup lang="ts">
import { ref } from 'vue';
import UiField from '../../design-system/UiField.vue';
import { formatCpf, isValidCpf, normalizeCpf } from '../../design-system/fieldValidation';

const cpf = ref('');
const cpfError = ref('');

function updateCpf(event: Event): void {
    cpf.value = normalizeCpf((event.target as HTMLInputElement).value);
    cpfError.value = '';
}

function validateCpf(): void {
    cpf.value = formatCpf(cpf.value);
    cpfError.value = cpf.value === '' || isValidCpf(cpf.value) ? '' : 'CPF inválido. Confira os dígitos informados.';
}
</script>

<template>
    <article class="developer-guide-article">
        <header id="fields-overview" class="developer-guide-article__header" tabindex="-1">
            <p class="developer-guide-article__eyebrow">Entrada · dados</p>
            <h1>Campos</h1>
            <p>Esta página é a norma de campos do Rinos One, não uma galeria de sugestões. Todo campo de formulário usa <code>UiField</code> como invólucro normativo; ele mantém rótulo, ajuda contextual, requisito e erro ligados ao controle, enquanto a tela define somente o tipo HTML e as regras próprias do dado.</p>
        </header>

        <section id="fields-norms" class="developer-guide-section" aria-labelledby="fields-norms-title" tabindex="-1">
            <div class="developer-guide-section__heading">
                <h2 id="fields-norms-title">Normas obrigatórias</h2>
                <p>As regras desta seção prevalecem sobre decisões locais de composição. Os exemplos das demais seções apenas demonstram o contrato.</p>
            </div>
            <div class="developer-guide-norms">
                <aside class="developer-guide-note developer-guide-note--mandatory" role="note"><strong>Obrigatório.</strong> Em uma linha ou grade de formulário, todos os <code>UiField</code> devem alinhar-se pelo topo e manter somente a sua própria altura. A altura de um <code>textarea</code>, popover ou conteúdo de outro campo não pode esticar os campos vizinhos.</aside>
                <aside class="developer-guide-note developer-guide-note--prohibited" role="note"><strong>Proibido.</strong> Não use <code>align-items: stretch</code>, alturas compartilhadas, linhas de ajuda ou mensagens de validação estáticas para preencher o espaço deixado por um campo mais alto. Ajuda e erro usam exclusivamente os popovers circulares definidos por <code>UiField</code>.</aside>
                <aside class="developer-guide-note developer-guide-note--exception" role="note"><strong>Exceção.</strong> Um controle composto que exija altura própria — como um editor rico — deve ser registrado antes como componente compartilhado. Mesmo nesse caso, ele não define nem amplia a altura dos demais campos da linha.</aside>
            </div>
        </section>

        <section id="fields-models" class="developer-guide-section" aria-labelledby="fields-models-title" tabindex="-1">
            <div class="developer-guide-section__heading">
                <h2 id="fields-models-title">Modelos base</h2>
                <p>Use controles HTML nativos enquanto não houver um componente compartilhado que amplie esse contrato. O modelo é escolhido pela natureza do dado, não pelo espaço disponível na tela.</p>
            </div>
            <div class="developer-guide-showcase developer-guide-fields__grid">
                <UiField id="fields-name" label="Nome completo" help="Como a pessoa deve ser identificada nesta tela." required v-slot="field">
                    <input id="fields-name" type="text" autocomplete="name" :aria-describedby="field.describedBy" :aria-invalid="field.invalid" required>
                </UiField>
                <UiField id="fields-email" label="E-mail" required v-slot="field">
                    <input id="fields-email" type="email" autocomplete="email" inputmode="email" :aria-describedby="field.describedBy" :aria-invalid="field.invalid" required>
                </UiField>
                <UiField id="fields-description" label="Descrição" help="Use texto multilinha apenas quando a resposta puder conter parágrafos." v-slot="field">
                    <textarea id="fields-description" rows="3" :aria-describedby="field.describedBy" :aria-invalid="field.invalid"></textarea>
                </UiField>
                <UiField id="fields-person-type" label="Tipo de pessoa" required v-slot="field">
                    <select id="fields-person-type" :aria-describedby="field.describedBy" :aria-invalid="field.invalid" required>
                        <option value="">Selecione</option>
                        <option value="individual">Pessoa física</option>
                        <option value="company">Pessoa jurídica</option>
                    </select>
                </UiField>
            </div>
            <ul class="developer-guide-fields__rules">
                <li><code>type=&quot;text&quot;</code>, <code>email</code>, <code>number</code> e <code>textarea</code> representam valores digitados. Use <code>inputmode</code> para sugerir o teclado adequado, sem mudar o valor persistido.</li>
                <li>O <code>select</code> nativo é obrigatório para uma lista curta, estável e exclusiva. Pesquisa remota, múltipla seleção ou milhares de opções exigem um modelo compartilhado próprio; não improvise um combobox local.</li>
                <li>O placeholder apenas exemplifica o formato; nunca substitui o rótulo. Quando o formato precisa de orientação, use <code>help</code>: ele cria o botão “?” antes do rótulo e nunca texto permanente acima ou abaixo do campo.</li>
                <li>Campos em uma mesma linha alinham-se pelo topo e preservam a própria altura. Um <code>textarea</code>, mensagem de erro ou qualquer outro conteúdo mais alto não estica os campos vizinhos.</li>
            </ul>
        </section>

        <section id="fields-temporal" class="developer-guide-section" aria-labelledby="fields-temporal-title" tabindex="-1">
            <div class="developer-guide-section__heading">
                <h2 id="fields-temporal-title">Data e hora</h2>
                <p>A resolução deve ser a menor unidade que o domínio realmente usa. Não solicite hora quando basta uma data, nem aceite data sem hora quando a operação depende do instante.</p>
            </div>
            <div class="developer-guide-showcase developer-guide-fields__temporal-grid">
                <UiField id="fields-date" label="Data" help="Dia, mês e ano." required v-slot="field"><input id="fields-date" type="date" :aria-describedby="field.describedBy" :aria-invalid="field.invalid" required></UiField>
                <UiField id="fields-month" label="Mês de referência" help="Mês e ano." required v-slot="field"><input id="fields-month" type="month" :aria-describedby="field.describedBy" :aria-invalid="field.invalid" required></UiField>
                <UiField id="fields-time" label="Horário" help="Hora local, sem data." required v-slot="field"><input id="fields-time" type="time" :aria-describedby="field.describedBy" :aria-invalid="field.invalid" required></UiField>
                <UiField id="fields-datetime" label="Início" help="Data e hora locais." required v-slot="field"><input id="fields-datetime" type="datetime-local" :aria-describedby="field.describedBy" :aria-invalid="field.invalid" required></UiField>
            </div>
            <aside class="developer-guide-note" role="note"><strong>Fuso horário.</strong> <code>datetime-local</code> coleta uma data e hora sem offset. A tela deve declarar o fuso de referência quando ele importar; conversão, armazenamento UTC e regras de calendário pertencem ao contrato de domínio e à API.</aside>
        </section>

        <section id="fields-validation" class="developer-guide-section" aria-labelledby="fields-validation-title" tabindex="-1">
            <div class="developer-guide-section__heading">
                <h2 id="fields-validation-title">Dados validados</h2>
                <p>Máscaras melhoram a leitura, mas não são validação. A interface preserva a digitação e associa a mensagem ao campo; a regra definitiva continua no servidor.</p>
            </div>
            <div class="developer-guide-showcase developer-guide-fields__grid">
                <UiField id="fields-cpf" label="CPF" help="Digite os 11 dígitos; ao sair do campo, um CPF válido recebe a máscara padrão." :error="cpfError" required v-slot="field">
                    <input id="fields-cpf" :value="cpf" inputmode="numeric" autocomplete="off" :aria-describedby="field.describedBy" :aria-invalid="field.invalid" required @input="updateCpf" @blur="validateCpf">
                </UiField>
                <UiField id="fields-cep" label="CEP" help="Digite os 8 dígitos; a apresentação pode exibir hífen." required v-slot="field">
                    <input id="fields-cep" inputmode="numeric" autocomplete="postal-code" :aria-describedby="field.describedBy" :aria-invalid="field.invalid" required>
                </UiField>
                <UiField id="fields-cnpj" label="CNPJ" help="Aceita 14 posições numéricas ou alfanuméricas, conforme o cadastro oficial." required v-slot="field">
                    <input id="fields-cnpj" autocapitalize="characters" autocomplete="off" :aria-describedby="field.describedBy" :aria-invalid="field.invalid" required>
                </UiField>
                <UiField id="fields-password" label="Senha" help="A regra de composição é explicada antes do envio." required v-slot="field">
                    <input id="fields-password" type="password" autocomplete="new-password" :aria-describedby="field.describedBy" :aria-invalid="field.invalid" required>
                </UiField>
            </div>
            <ul class="developer-guide-fields__rules">
                <li>CPF e CEP usam teclado numérico, mas a validação aceita apenas o formato definido pelo contrato. Não converta o valor em número: zeros à esquerda são dados.</li>
                <li>CNPJ é texto de 14 posições e pode conter letras. Não use <code>type=&quot;number&quot;</code>, nem suponha unicidade ou identidade a partir dele.</li>
                <li>Erro de validação local ou retornado pela API usa <code>error</code> no <code>UiField</code> e <code>aria-invalid=&quot;true&quot;</code> no controle. O indicador laranja “!” fica antes da ajuda “?” e abre o popover com a mensagem; texto de validação nunca ocupa uma linha acima ou abaixo do campo.</li>
            </ul>
        </section>

        <section id="fields-adoption" class="developer-guide-section" aria-labelledby="fields-adoption-title" tabindex="-1">
            <div class="developer-guide-section__heading">
                <h2 id="fields-adoption-title">Contrato de adoção</h2>
                <p>Os campos compartilham medidas, cores, foco e escala por meio dos tokens e de <code>UiField</code>. Não crie bordas, rótulos, mensagens ou estados de erro em CSS local.</p>
            </div>
            <pre class="developer-guide-code"><code>&lt;UiField id=&quot;person-cpf&quot; label=&quot;CPF&quot; :error=&quot;cpfError&quot; required v-slot=&quot;field&quot;&gt;
  &lt;input
    id=&quot;person-cpf&quot;
    :value=&quot;cpf&quot;
    inputmode=&quot;numeric&quot;
    :aria-describedby=&quot;field.describedBy&quot;
    :aria-invalid=&quot;field.invalid&quot;
    required
    @input=&quot;updateCpf&quot;
    @blur=&quot;validateCpf&quot;
  &gt;
&lt;/UiField&gt;</code></pre>
            <ul class="developer-guide-fields__rules">
                <li>Todo controle recebe um <code>id</code> estável e único, igual ao informado ao <code>UiField</code>. O rótulo clicável, popover de ajuda e popover de validação dependem desse vínculo.</li>
                <li>O asterisco visual vermelho exige também <code>required</code> no controle quando o dado for obrigatório. Dados condicionais explicam a condição em ajuda ou no fluxo.</li>
                <li>Novos tipos de campo, máscaras ou seletores só podem ser reutilizados depois de serem definidos aqui e entregues como componente compartilhado com testes de teclado, toque e acessibilidade.</li>
            </ul>
        </section>
    </article>
</template>
