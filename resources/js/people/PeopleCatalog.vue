<script setup lang="ts">
import axios from "axios";
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from "vue";
import { useI18n } from "vue-i18n";
import { trapFocus } from "../accessibility/focusTrap";
import type { PeopleCapabilities } from "../tenant/tenantTypes";
import type { WorkspaceSurface } from "../workspace/workspaceTypes";
import { loadPeople, type PeoplePage } from "./peopleApi";

const props = withDefaults(
    defineProps<{
        surface: WorkspaceSurface;
        capabilities?: PeopleCapabilities;
        tenantName?: string | null;
    }>(),
    { capabilities: () => ({}), tenantName: null },
);
type CatalogAction =
    "open" | "duplicate" | "inactivate" | "reactivate" | "delete";
const emit = defineEmits<{
    create: [];
    quickCreate: [];
    open: [personId: number];
    action: [personId: number, action: CatalogAction, trigger: HTMLElement];
}>();
const { t } = useI18n();
const search = ref("");
const status = ref("ACTIVE");
const personType = ref("");
const page = ref(1);
const result = ref<PeoplePage | null>(null);
const loading = ref(false);
const error = ref("");
const root = ref<HTMLElement | null>(null);
const offline = ref(!navigator.onLine);
const filtersOpen = ref(false);
const filterSheet = ref<HTMLElement | null>(null);
let debounce: ReturnType<typeof setTimeout> | null = null;
let filtersTrigger: HTMLElement | null = null;

async function refresh(): Promise<void> {
    if (props.surface.tenantId === null || !navigator.onLine) return;

    loading.value = true;
    error.value = "";

    try {
        result.value = await loadPeople(props.surface.tenantId, {
            search: search.value || undefined,
            status: status.value || undefined,
            personType: personType.value || undefined,
            page: page.value,
        });
    } catch (reason) {
        error.value =
            axios.isAxiosError(reason) && reason.response?.status === 403
                ? t("access.people.accessDenied")
                : t("access.people.loadFailed");
    } finally {
        loading.value = false;
    }
}

function previous(): void {
    if (page.value > 1) {
        page.value -= 1;
        void refresh();
    }
}

function next(): void {
    if (result.value && page.value < result.value.pagination.lastPage) {
        page.value += 1;
        void refresh();
    }
}

function handleOnline(): void {
    offline.value = false;
    void refresh();
}

function handleOffline(): void {
    offline.value = true;
}

const hasPagination = computed(
    () => (result.value?.pagination.lastPage ?? 1) > 1,
);
const hasFilters = computed(() =>
    Boolean(search.value || personType.value || status.value !== "ACTIVE"),
);

function clearFilters(): void {
    search.value = "";
    status.value = "ACTIVE";
    personType.value = "";
}

async function openFilters(event: MouseEvent): Promise<void> {
    filtersTrigger = event.currentTarget as HTMLElement;
    filtersOpen.value = true;
    await nextTick();
    filterSheet.value?.focus();
}

function closeFilters(): void {
    filtersOpen.value = false;
    void nextTick(() => filtersTrigger?.focus());
}

watch([search, status, personType], () => {
    page.value = 1;

    if (debounce !== null) clearTimeout(debounce);
    debounce = setTimeout(() => void refresh(), 300);
});

onMounted(() => {
    window.addEventListener("online", handleOnline);
    window.addEventListener("offline", handleOffline);
    void refresh();
});

onBeforeUnmount(() => {
    if (debounce !== null) clearTimeout(debounce);
    window.removeEventListener("online", handleOnline);
    window.removeEventListener("offline", handleOffline);
});

async function focus(): Promise<void> {
    await nextTick();
    root.value?.focus();
}
function requestAction(
    personId: number,
    action: CatalogAction,
    event: MouseEvent,
): void {
    if (action === "open") {
        emit("open", personId);
        return;
    }
    emit("action", personId, action, event.currentTarget as HTMLElement);
}
defineExpose({ refresh, focus });
</script>

<template>
    <section
        ref="root"
        class="people-catalog"
        tabindex="-1"
        :aria-label="t('access.people.title')"
    >
        <p v-if="tenantName" class="people-catalog__tenant">
            {{ t("access.people.organization", { name: tenantName }) }}
        </p>
        <div class="people-catalog__tools">
            <button
                v-if="capabilities.canCreatePeople"
                type="button"
                @click="emit('create')"
            >
                {{ t("access.people.create") }}
            </button>
            <button
                v-if="capabilities.canCreatePeople"
                type="button"
                @click="emit('quickCreate')"
            >
                {{ t("access.people.quickCreate") }}
            </button>
            <input
                v-model="search"
                type="search"
                :placeholder="t('access.people.search')"
                :aria-label="t('access.people.search')"
            />
            <button
                type="button"
                class="people-catalog__filters-toggle"
                :aria-expanded="filtersOpen"
                @click="openFilters"
            >
                {{ t("access.people.filters") }}
            </button>
            <button v-if="hasFilters" type="button" @click="clearFilters">
                {{ t("access.people.clearFilters") }}
            </button>
            <button
                type="button"
                :disabled="loading || offline"
                @click="refresh"
            >
                {{ t("access.people.reload") }}
            </button>
        </div>

        <section
            v-if="filtersOpen"
            ref="filterSheet"
            class="people-catalog__filter-sheet"
            role="dialog"
            aria-modal="true"
            :aria-label="t('access.people.filters')"
            tabindex="-1"
            @keydown.escape="closeFilters"
            @keydown="trapFocus"
        >
            <header>
                <h3>{{ t("access.people.filters") }}</h3>
                <button type="button" @click="closeFilters">
                    {{ t("access.people.closeFilters") }}
                </button>
            </header>
            <label>
                {{ t("access.people.status") }}
                <select v-model="status">
                    <option value="ACTIVE">
                        {{ t("access.people.active") }}
                    </option>
                    <option value="INACTIVE">
                        {{ t("access.people.inactive") }}
                    </option>
                    <option value="">{{ t("access.people.all") }}</option>
                </select>
            </label>
            <label>
                {{ t("access.people.type") }}
                <select v-model="personType">
                    <option value="">{{ t("access.people.all") }}</option>
                    <option value="PF">PF</option>
                    <option value="PJ">PJ</option>
                </select>
            </label>
            <div class="people-catalog__filter-sheet-actions">
                <button type="button" @click="clearFilters">
                    {{ t("access.people.clearFilters") }}
                </button>
                <button type="button" @click="closeFilters">
                    {{ t("access.people.applyFilters") }}
                </button>
            </div>
        </section>

        <div
            class="people-catalog__content"
            :class="{
                'people-catalog__content--with-filter-panel': filtersOpen,
            }"
        >
            <aside
                v-if="filtersOpen"
                class="people-catalog__filter-panel"
                :aria-label="t('access.people.filters')"
            >
                <header>
                    <h3>{{ t("access.people.filters") }}</h3>
                    <button type="button" @click="closeFilters">
                        {{ t("access.people.closeFilters") }}
                    </button>
                </header>
                <label>
                    {{ t("access.people.status") }}
                    <select v-model="status">
                        <option value="ACTIVE">
                            {{ t("access.people.active") }}
                        </option>
                        <option value="INACTIVE">
                            {{ t("access.people.inactive") }}
                        </option>
                        <option value="">{{ t("access.people.all") }}</option>
                    </select>
                </label>
                <label>
                    {{ t("access.people.type") }}
                    <select v-model="personType">
                        <option value="">{{ t("access.people.all") }}</option>
                        <option value="PF">PF</option>
                        <option value="PJ">PJ</option>
                    </select>
                </label>
                <div class="people-catalog__filter-sheet-actions">
                    <button type="button" @click="clearFilters">
                        {{ t("access.people.clearFilters") }}
                    </button>
                    <button type="button" @click="closeFilters">
                        {{ t("access.people.applyFilters") }}
                    </button>
                </div>
            </aside>
            <div class="people-catalog__content-main">
                <p v-if="offline" role="status">
                    {{ t("access.people.offline") }}
                </p>
                <p v-else-if="loading" role="status">
                    {{ t("access.people.loading") }}
                </p>
                <p v-if="error" role="alert">
                    {{ error
                    }}<span v-if="result?.people.length">
                        {{ t("access.people.stale") }}</span
                    >
                </p>
                <p
                    v-if="!loading && !offline && !error && !result?.people.length"
                >
                    {{ t("access.people.empty") }}
                </p>

                <p
                    v-if="result?.people.length"
                    class="people-catalog__count"
                    role="status"
                >
                    {{
                        t(
                            "access.people.results",
                            { count: result.pagination.total },
                            result.pagination.total,
                        )
                    }}
                </p>
                <div v-if="result?.people.length" class="people-catalog__list">
            <table class="people-catalog__table">
                <thead>
                    <tr>
                        <th scope="col">{{ t("access.people.person") }}</th>
                        <th scope="col">{{ t("access.people.type") }}</th>
                        <th scope="col">{{ t("access.people.document") }}</th>
                        <th scope="col">{{ t("access.people.contacts") }}</th>
                        <th scope="col">{{ t("access.people.status") }}</th>
                        <th scope="col">{{ t("access.people.actions") }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="person in result.people" :key="person.id">
                        <td>{{ person.displayName }}</td>
                        <td>{{ person.personType }}</td>
                        <td>{{ person.document ?? "—" }}</td>
                        <td>
                            {{
                                t(
                                    "access.people.contactsSummary",
                                    { count: person.contactCount },
                                    person.contactCount,
                                )
                            }}
                        </td>
                        <td>
                            <span
                                class="people-catalog__status"
                                :data-status="person.status"
                                >{{
                                    person.status === "ACTIVE"
                                        ? t("access.people.active")
                                        : t("access.people.inactive")
                                }}</span
                            >
                        </td>
                        <td>
                            <details class="people-catalog__actions">
                                <summary>
                                    {{ t("access.people.actions") }}
                                </summary>
                                <button
                                    type="button"
                                    @click="
                                        requestAction(person.id, 'open', $event)
                                    "
                                >
                                    {{ t("access.people.open") }}</button
                                ><button
                                    v-if="capabilities.canDuplicatePeople"
                                    type="button"
                                    @click="
                                        requestAction(
                                            person.id,
                                            'duplicate',
                                            $event,
                                        )
                                    "
                                >
                                    {{ t("access.people.duplicate") }}</button
                                ><button
                                    v-if="
                                        person.status === 'ACTIVE' &&
                                        capabilities.canInactivatePeople
                                    "
                                    type="button"
                                    @click="
                                        requestAction(
                                            person.id,
                                            'inactivate',
                                            $event,
                                        )
                                    "
                                >
                                    {{ t("access.people.inactivate") }}</button
                                ><button
                                    v-if="
                                        person.status === 'INACTIVE' &&
                                        capabilities.canReactivatePeople
                                    "
                                    type="button"
                                    @click="
                                        requestAction(
                                            person.id,
                                            'reactivate',
                                            $event,
                                        )
                                    "
                                >
                                    {{ t("access.people.reactivate") }}</button
                                ><button
                                    v-if="capabilities.canDeletePeople"
                                    type="button"
                                    @click="
                                        requestAction(
                                            person.id,
                                            'delete',
                                            $event,
                                        )
                                    "
                                >
                                    {{ t("access.people.delete") }}
                                </button>
                            </details>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="people-catalog__cards">
                <article v-for="person in result.people" :key="person.id">
                    <button
                        type="button"
                        class="people-catalog__open"
                        @click="requestAction(person.id, 'open', $event)"
                    >
                        {{ person.displayName }}</button
                    ><span
                        >{{ person.personType }} ·
                        {{ person.document ?? "—" }} ·
                        {{
                            t(
                                "access.people.contactsSummary",
                                { count: person.contactCount },
                                person.contactCount,
                            )
                        }}</span
                    ><small
                        class="people-catalog__status"
                        :data-status="person.status"
                        >{{
                            person.status === "ACTIVE"
                                ? t("access.people.active")
                                : t("access.people.inactive")
                        }}</small
                    >
                    <details class="people-catalog__actions">
                        <summary>{{ t("access.people.actions") }}</summary>
                        <button
                            type="button"
                            @click="requestAction(person.id, 'open', $event)"
                        >
                            {{ t("access.people.open") }}</button
                        ><button
                            v-if="capabilities.canDuplicatePeople"
                            type="button"
                            @click="
                                requestAction(person.id, 'duplicate', $event)
                            "
                        >
                            {{ t("access.people.duplicate") }}</button
                        ><button
                            v-if="
                                person.status === 'ACTIVE' &&
                                capabilities.canInactivatePeople
                            "
                            type="button"
                            @click="
                                requestAction(person.id, 'inactivate', $event)
                            "
                        >
                            {{ t("access.people.inactivate") }}</button
                        ><button
                            v-if="
                                person.status === 'INACTIVE' &&
                                capabilities.canReactivatePeople
                            "
                            type="button"
                            @click="
                                requestAction(person.id, 'reactivate', $event)
                            "
                        >
                            {{ t("access.people.reactivate") }}</button
                        ><button
                            v-if="capabilities.canDeletePeople"
                            type="button"
                            @click="requestAction(person.id, 'delete', $event)"
                        >
                            {{ t("access.people.delete") }}
                        </button>
                    </details>
                </article>
            </div>
        </div>

        <nav
            v-if="hasPagination"
            class="people-catalog__pagination"
            :aria-label="t('access.people.pagination')"
        >
            <button
                type="button"
                :disabled="page === 1 || loading"
                @click="previous"
            >
                {{ t("access.people.previous") }}
            </button>
            <span
                >{{ result?.pagination.page }} /
                {{ result?.pagination.lastPage }}</span
            >
            <button
                type="button"
                :disabled="page === result?.pagination.lastPage || loading"
                @click="next"
            >
                {{ t("access.people.next") }}
            </button>
        </nav>
            </div>
        </div>
    </section>
</template>

<style scoped>
.people-catalog {
    display: grid;
    gap: var(--space-3);
    padding: var(--space-4);
}
.people-catalog__tenant {
    margin: 0;
    color: var(--color-text-secondary);
    font-size: var(--font-size-sm);
}
.people-catalog__tools,
.people-catalog__pagination {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-2);
}
.people-catalog__filters-toggle {
    display: block;
}
.people-catalog__filter-sheet {
    display: none;
}
.people-catalog__content {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: var(--space-3);
}
.people-catalog__content--with-filter-panel {
    grid-template-columns: minmax(0, 1fr) minmax(14rem, 18rem);
}
.people-catalog__content-main {
    grid-column: 1;
    grid-row: 1;
    min-width: 0;
}
.people-catalog__filter-panel {
    grid-column: 2;
    grid-row: 1;
    display: grid;
    align-content: start;
    gap: var(--space-3);
    padding: var(--space-3);
    border: var(--component-border-width) solid var(--color-border-subtle);
    border-radius: var(--radius-md);
    background: var(--color-surface-raised);
}
.people-catalog__filter-panel header,
.people-catalog__filter-panel label {
    display: grid;
    gap: var(--space-2);
}
.people-catalog__filter-panel header {
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
}
.people-catalog__filter-panel h3 {
    margin: 0;
}
.people-catalog input,
.people-catalog select {
    min-height: var(--control-height-sm);
    padding: 0 var(--space-2);
    border: var(--component-border-width) solid var(--color-border-subtle);
    border-radius: var(--radius-sm);
    background: var(--color-surface-raised);
    color: var(--color-text-primary);
    font: inherit;
}
.people-catalog button {
    min-height: var(--control-height-sm);
    padding: 0 var(--space-2);
    font: inherit;
}
.people-catalog__list {
    display: grid;
    gap: var(--space-2);
}
.people-catalog__table {
    width: 100%;
    border-collapse: collapse;
}
.people-catalog__table th,
.people-catalog__table td {
    padding: var(--space-2);
    border-bottom: var(--component-border-width) solid
        var(--color-border-subtle);
    text-align: start;
}
.people-catalog__cards {
    display: none;
}
.people-catalog article {
    display: grid;
    gap: var(--space-1);
    padding: var(--space-3);
    border: var(--component-border-width) solid var(--color-border-subtle);
    border-radius: var(--radius-md);
    background: var(--color-surface-raised);
}
.people-catalog__open {
    width: fit-content;
    padding: 0;
    border: 0;
    color: inherit;
    background: transparent;
    font: inherit;
    font-weight: 700;
    text-align: start;
    cursor: pointer;
}
.people-catalog__status {
    font-weight: 600;
}
.people-catalog__status[data-status="INACTIVE"] {
    opacity: 0.7;
}
.people-catalog__actions {
    display: grid;
    gap: var(--space-1);
}
.people-catalog__actions summary {
    cursor: pointer;
}

@media (max-width: 700px) {
    .people-catalog__tools > * {
        flex: 1 1 10rem;
    }
    .people-catalog__content--with-filter-panel {
        grid-template-columns: minmax(0, 1fr);
    }
    .people-catalog__filter-panel {
        display: none;
    }
    .people-catalog__content-main {
        grid-column: 1;
    }
    .people-catalog__filter-sheet {
        position: fixed;
        z-index: 20;
        inset: 0;
        display: grid;
        align-content: start;
        gap: var(--space-4);
        overflow: auto;
        padding: var(--space-4);
        padding-bottom: max(var(--space-4), env(safe-area-inset-bottom));
        background: var(--color-surface);
    }
    .people-catalog__filter-sheet header,
    .people-catalog__filter-sheet label {
        display: grid;
        gap: var(--space-2);
    }
    .people-catalog__filter-sheet header {
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
    }
    .people-catalog__filter-sheet h3 {
        margin: 0;
    }
    .people-catalog__filter-sheet select {
        width: 100%;
    }
    .people-catalog__filter-sheet-actions {
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-2);
    }
    .people-catalog__pagination button {
        flex: 1;
    }
    .people-catalog__table {
        display: none;
    }
    .people-catalog__cards {
        display: grid;
        gap: var(--space-2);
    }
}
</style>
