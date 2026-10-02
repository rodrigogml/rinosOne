<script setup lang="ts">
import axios from "axios";
import { computed, nextTick, onMounted, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { trapFocus } from "../accessibility/focusTrap";
import SegmentedChoiceGroup from "../design-system/SegmentedChoiceGroup.vue";
import { useOnlineStatus } from "../network/useOnlineStatus";
import type { PeopleCapabilities } from "../tenant/tenantTypes";
import {
    createPerson,
    loadBrazilMunicipalities,
    loadBrazilStates,
    loadCountries,
    loadFinancialInstitutions,
    loadPeople,
    loadPerson,
    lookupPostalReferences,
    type PersonAddressWrite,
    type PersonBankAccountWrite,
    type PersonContactWrite,
    type PersonDetail,
    type PersonPixKeyWrite,
    type PersonRelationshipWrite,
    type PersonWrite,
    type PostalReferenceCandidate,
    updatePerson,
} from "./peopleApi";
import PersonLifecycleActions from "./PersonLifecycleActions.vue";
import PersonDuplicationActions from "./PersonDuplicationActions.vue";

type InitialAction = "duplicate" | "inactivate" | "reactivate" | "delete";
type CollectionName =
    "addresses" | "bankAccounts" | "contacts" | "pixKeys" | "relationships";
const props = withDefaults(
    defineProps<{
        tenantId: number;
        personId?: number | null;
        capabilities?: PeopleCapabilities;
        initialAction?: InitialAction | null;
        initialActionTrigger?: HTMLElement | null;
    }>(),
    { personId: null, capabilities: () => ({}) },
);
const emit = defineEmits<{
    cancel: [];
    saved: [person: PersonDetail];
    duplicated: [person: PersonDetail];
}>();
const { t } = useI18n();
const loading = ref(false),
    saving = ref(false),
    error = ref(""),
    success = ref(""),
    fieldErrors = ref<Record<string, string>>({}),
    version = ref<number | null>(null),
    loadedPerson = ref<PersonDetail | null>(null),
    initialDraft = ref(""),
    discardConfirmation = ref(false),
    discardTitle = ref<HTMLElement | null>(null),
    removal = ref<{
        collection: "address" | "bank" | "contact" | "pix" | "relationship";
        index: number;
    } | null>(null),
    removalTitle = ref<HTMLElement | null>(null);
let discardTrigger: HTMLElement | null = null;
let removalTrigger: HTMLElement | null = null;
const nameInput = ref<HTMLInputElement | null>(null);
const formRoot = ref<HTMLFormElement | null>(null);
const activeCollection = ref<CollectionName | null>(null);
let collectionTrigger: HTMLElement | null = null;
const duplicationActions = ref<InstanceType<
    typeof PersonDuplicationActions
> | null>(null);
const lifecycleActions = ref<InstanceType<
    typeof PersonLifecycleActions
> | null>(null);
const model = ref<PersonWrite>({
    personType: "PF",
    name: "",
    alias: null,
    cpf: null,
    cnpj: null,
    rg: null,
    rgIssuer: null,
    pisNis: null,
    passportNumber: null,
    foreignDocumentNumber: null,
    birthDate: null,
    foundationDate: null,
    notes: null,
});
const addresses = ref<PersonAddressWrite[]>([]),
    bankAccounts = ref<PersonBankAccountWrite[]>([]),
    contacts = ref<PersonContactWrite[]>([]),
    pixKeys = ref<PersonPixKeyWrite[]>([]),
    relationships = ref<PersonRelationshipWrite[]>([]),
    people = ref<{ id: number; displayName: string }[]>([]);
const countries = ref<{ id: number; isoAlpha2: string; name: string }[]>([]),
    addressStates = ref<{ id: number; abbreviation: string; name: string }[][]>(
        [],
    ),
    addressMunicipalities = ref<{ id: number; name: string }[][]>([]),
    postalCandidates = ref<PostalReferenceCandidate[][]>([]),
    institutions = ref<{ id: number; name: string; code: string | null }[]>([]);
const relationshipSearch = ref("");
let relationshipSearchTimer: ReturnType<typeof setTimeout> | null = null;
const isNew = computed(() => props.personId === null);
const activeSection = ref("basic");
const cancelIcon = "/assets/icons/btCancel_24.png";
const saveIcon = "/assets/icons/floppyDisk_24.png";
const operationTitle = computed(() => {
    if (isNew.value) return "Inserindo Pessoa";
    if (props.initialAction === "duplicate") return "Duplicando Pessoa";
    return "Editando Pessoa";
});
const displayNamePreview = computed(() => {
    const name = model.value.name.trim();
    const alias = model.value.alias?.trim();

    if (!name) {
        return "";
    }

    return alias ? `${name} (${alias})` : name;
});
const { online } = useOnlineStatus();
const canEdit = computed(
    () =>
        (isNew.value && props.capabilities.canCreatePeople) ||
        (!isNew.value && props.capabilities.canUpdatePeople),
);
const canSave = computed(() => online.value && !saving.value && canEdit.value);
const isBrazil = (address: PersonAddressWrite) =>
    countries.value.find((country) => country.id === address.idCountry)
        ?.isoAlpha2 === "BR";
const clean = (value: string | null | undefined): string | null =>
    value?.trim() || null;
function personTypeChanged(personType: "PF" | "PJ"): void {
    model.value.personType = personType;
    if (model.value.personType === "PF") {
        model.value.cnpj = null;
        model.value.foundationDate = null;
        return;
    }
    model.value.cpf = null;
    model.value.rg = null;
    model.value.rgIssuer = null;
    model.value.pisNis = null;
    model.value.birthDate = null;
}
function addAddress(): void {
    addresses.value.push({
        label: "",
        addressType: "RESIDENTIAL",
        idCountry: 0,
        idBrazilState: null,
        idBrazilMunicipality: null,
        idLocalityReference: null,
        stateText: null,
        cityText: null,
        street: null,
        number: null,
        complement: null,
        district: null,
        reference: null,
        postalCode: null,
    });
    addressStates.value.push([]);
    addressMunicipalities.value.push([]);
    postalCandidates.value.push([]);
}
async function openCollection(
    collection: CollectionName,
    event: MouseEvent,
): Promise<void> {
    collectionTrigger = event.currentTarget as HTMLElement;
    activeCollection.value = collection;
    await nextTick();
    formRoot.value?.querySelector<HTMLElement>(".collection--active")?.focus();
}
function closeCollection(): void {
    activeCollection.value = null;
    void nextTick(() => collectionTrigger?.focus());
}
function removeAddress(index: number): void {
    addresses.value.splice(index, 1);
    addressStates.value.splice(index, 1);
    addressMunicipalities.value.splice(index, 1);
    postalCandidates.value.splice(index, 1);
}

function confirmRemoval(): void {
    if (!removal.value) return;
    const { collection, index } = removal.value;
    if (collection === "address") removeAddress(index);
    if (collection === "bank") bankAccounts.value.splice(index, 1);
    if (collection === "contact") contacts.value.splice(index, 1);
    if (collection === "pix") pixKeys.value.splice(index, 1);
    if (collection === "relationship") relationships.value.splice(index, 1);
    removal.value = null;
    void nextTick(() => removalTrigger?.focus());
}

async function requestRemoval(
    collection: "address" | "bank" | "contact" | "pix" | "relationship",
    index: number,
    event: MouseEvent,
): Promise<void> {
    removalTrigger = event.currentTarget as HTMLElement;
    removal.value = { collection, index };
    await nextTick();
    removalTitle.value?.focus();
}

function cancelRemoval(): void {
    removal.value = null;
    void nextTick(() => removalTrigger?.focus());
}
function addBankAccount(): void {
    bankAccounts.value.push({
        label: "",
        idFinancialInstitution: null,
        accountType: "CHECKING",
        agency: null,
        agencyDigit: null,
        accountNumber: null,
        accountDigit: null,
        status: "ACTIVE",
    });
}
function addContact(): void {
    contacts.value.push({ contactType: "EMAIL", value: "", description: null });
}
function addPix(): void {
    pixKeys.value.push({ keyType: "EMAIL", value: "", status: "ACTIVE" });
}
function addRelationship(): void {
    relationships.value.push({
        idTargetPerson: 0,
        relationshipType: "OTHER",
        description: null,
    });
}
const inverseRelationshipType: Record<
    PersonRelationshipWrite["relationshipType"],
    PersonRelationshipWrite["relationshipType"]
> = {
    CHILD_OF: "PARENT_OF",
    PARENT_OF: "CHILD_OF",
    GRANDCHILD_OF: "GRANDPARENT_OF",
    GRANDPARENT_OF: "GRANDCHILD_OF",
    SPOUSE_OF: "SPOUSE_OF",
    PARTNER_OF: "PARTNER_OF",
    EMPLOYEE_OF: "EMPLOYER_OF",
    EMPLOYER_OF: "EMPLOYEE_OF",
    CONTRACTOR_OF: "CONTRACTING_PARTY_OF",
    CONTRACTING_PARTY_OF: "CONTRACTOR_OF",
    OTHER: "OTHER",
};
async function countryChanged(
    address: PersonAddressWrite,
    index: number,
): Promise<void> {
    address.idBrazilState = null;
    address.idBrazilMunicipality = null;
    addressStates.value[index] = address.idCountry
        ? await loadBrazilStates(props.tenantId, address.idCountry)
        : [];
    addressMunicipalities.value[index] = [];
}
async function stateChanged(
    address: PersonAddressWrite,
    index: number,
): Promise<void> {
    address.idBrazilMunicipality = null;
    addressMunicipalities.value[index] = address.idBrazilState
        ? await loadBrazilMunicipalities(props.tenantId, address.idBrazilState)
        : [];
}
async function findPostalCandidates(
    address: PersonAddressWrite,
    index: number,
): Promise<void> {
    const country = countries.value.find(
        (item) => item.id === address.idCountry,
    );
    if (!online.value || !country || !address.postalCode?.trim()) return;
    try {
        postalCandidates.value[index] = await lookupPostalReferences(
            country.isoAlpha2,
            address.postalCode,
        );
    } catch {
        postalCandidates.value[index] = [];
    }
}
async function hydrateAddressCatalogs(): Promise<void> {
    addressStates.value = addresses.value.map(() => []);
    addressMunicipalities.value = addresses.value.map(() => []);
    postalCandidates.value = addresses.value.map(() => []);
    await Promise.all(
        addresses.value.map(async (address, index) => {
            if (address.idCountry)
                addressStates.value[index] = await loadBrazilStates(
                    props.tenantId,
                    address.idCountry,
                );
            if (address.idBrazilState)
                addressMunicipalities.value[index] =
                    await loadBrazilMunicipalities(
                        props.tenantId,
                        address.idBrazilState,
                    );
        }),
    );
}
function payload(): PersonWrite {
    const value = model.value;
    return {
        ...value,
        name: value.name.trim(),
        alias: clean(value.alias),
        cpf: value.personType === "PF" ? clean(value.cpf) : null,
        cnpj: value.personType === "PJ" ? clean(value.cnpj) : null,
        rg: value.personType === "PF" ? clean(value.rg) : null,
        rgIssuer: value.personType === "PF" ? clean(value.rgIssuer) : null,
        pisNis: value.personType === "PF" ? clean(value.pisNis) : null,
        birthDate: value.personType === "PF" ? clean(value.birthDate) : null,
        foundationDate:
            value.personType === "PJ" ? clean(value.foundationDate) : null,
        notes: clean(value.notes),
        addresses: addresses.value.filter((address) => address.idCountry > 0),
        bankAccounts: bankAccounts.value.filter((account) =>
            account.label.trim(),
        ),
        contacts: contacts.value
            .map((contact) => ({
                ...contact,
                value: contact.value.trim(),
                description: clean(contact.description),
            }))
            .filter((contact) => contact.value),
        pixKeys: pixKeys.value
            .map((key) => ({ ...key, value: key.value.trim() }))
            .filter((key) => key.value),
        relationships: relationships.value.filter(
            (relationship) =>
                relationship.idTargetPerson > 0 &&
                relationship.idTargetPerson !== props.personId,
        ),
    };
}
const isDirty = computed(
    () => JSON.stringify(payload()) !== initialDraft.value,
);
async function requestCancel(event: MouseEvent): Promise<void> {
    if (isDirty.value) {
        discardTrigger = event.currentTarget as HTMLElement;
        discardConfirmation.value = true;
        await nextTick();
        discardTitle.value?.focus();
    } else emit("cancel");
}

function cancelDiscard(): void {
    discardConfirmation.value = false;
    void nextTick(() => discardTrigger?.focus());
}
function setFieldErrors(fields: unknown): void {
    if (!fields || typeof fields !== "object") return;
    fieldErrors.value = Object.fromEntries(
        Object.entries(fields as Record<string, unknown>)
            .map(([field, messages]) => [
                field,
                Array.isArray(messages)
                    ? String(messages[0] ?? "")
                    : String(messages),
            ])
            .filter(([, message]) => message),
    );
}

function collectionError(collection: string, index: number): string | null {
    return (
        Object.entries(fieldErrors.value).find(([field]) =>
            field.startsWith(`${collection}.${index}.`),
        )?.[1] ?? null
    );
}
async function focusFirstError(): Promise<void> {
    await nextTick();
    if (fieldErrors.value.name) nameInput.value?.focus();
}
function validateAddresses(): boolean {
    const errors: Record<string, string> = {};
    addresses.value.forEach((address, index) => {
        if (!address.idCountry || !isBrazil(address)) return;
        if (!address.idBrazilState)
            errors[`addresses.${index}.idBrazilState`] = t(
                "access.people.brazilStateRequired",
            );
        if (!address.idBrazilMunicipality)
            errors[`addresses.${index}.idBrazilMunicipality`] = t(
                "access.people.brazilMunicipalityRequired",
            );
    });
    if (!Object.keys(errors).length) return true;
    fieldErrors.value = errors;
    error.value = t("access.people.validationFailed");
    return false;
}
async function openInitialAction(): Promise<void> {
    if (!loadedPerson.value || !props.initialAction) return;

    if (
        props.initialAction === "duplicate" &&
        props.capabilities.canDuplicatePeople
    )
        await duplicationActions.value?.openFromCatalog(
            props.initialActionTrigger ?? null,
        );
    if (
        props.initialAction === "inactivate" &&
        props.capabilities.canInactivatePeople
    )
        await lifecycleActions.value?.openFromCatalog(
            "INACTIVATE",
            props.initialActionTrigger ?? null,
        );
    if (
        props.initialAction === "reactivate" &&
        props.capabilities.canReactivatePeople
    )
        await lifecycleActions.value?.openFromCatalog(
            "REACTIVATE",
            props.initialActionTrigger ?? null,
        );
    if (props.initialAction === "delete" && props.capabilities.canDeletePeople)
        await lifecycleActions.value?.openFromCatalog(
            "DELETE",
            props.initialActionTrigger ?? null,
        );
}
async function initialize(): Promise<void> {
    loading.value = true;
    try {
        const [countryCatalog, institutionCatalog, personPage] =
            await Promise.all([
                loadCountries(props.tenantId),
                loadFinancialInstitutions(props.tenantId),
                loadPeople(props.tenantId, { status: "ACTIVE", perPage: 200 }),
            ]);
        countries.value = countryCatalog;
        institutions.value = institutionCatalog;
        people.value = personPage.people;
        if (props.personId !== null) {
            const person = await loadPerson(props.tenantId, props.personId);
            loadedPerson.value = person;
            version.value = person.version;
            model.value = { ...person };
            addresses.value = person.addresses ?? [];
            await hydrateAddressCatalogs();
            bankAccounts.value = person.bankAccounts ?? [];
            contacts.value = person.contacts ?? [];
            pixKeys.value = person.pixKeys ?? [];
            relationships.value = (person.relationships ?? [])
                .filter((relationship) => relationship.direction !== "INCOMING")
                .map((relationship) => ({
                    idTargetPerson:
                        relationship.idTargetPerson ||
                        relationship.idOtherPerson ||
                        0,
                    relationshipType: relationship.relationshipType,
                    description: relationship.description,
                }));
        }
        initialDraft.value = JSON.stringify(payload());
    } catch {
        error.value = t("access.people.loadFailed");
    } finally {
        loading.value = false;
        await nextTick();
        await openInitialAction();
    }
}

watch(relationshipSearch, (search) => {
    if (relationshipSearchTimer !== null) clearTimeout(relationshipSearchTimer);
    relationshipSearchTimer = setTimeout(async () => {
        if (!online.value) return;
        try {
            const page = await loadPeople(props.tenantId, {
                search: search || undefined,
                status: "ACTIVE",
                perPage: 200,
            });
            people.value = page.people;
        } catch {
            /* Existing choices remain available if the lookup fails. */
        }
    }, 300);
});
async function save(): Promise<void> {
    if (!canSave.value) return;
    error.value = "";
    success.value = "";
    fieldErrors.value = {};
    if (model.value.name.trim().length < 2) {
        fieldErrors.value = { name: t("access.people.nameRequired") };
        await focusFirstError();
        return;
    }
    if (!validateAddresses()) return;
    saving.value = true;
    try {
        emit(
            "saved",
            isNew.value
                ? await createPerson(props.tenantId, payload())
                : await updatePerson(
                      props.tenantId,
                      props.personId!,
                      version.value!,
                      payload(),
                  ),
        );
    } catch (reason) {
        const responseError = axios.isAxiosError(reason)
            ? reason.response?.data?.error
            : null;
        const code = responseError?.code;
        if (code === "PERSON_VALIDATION_FAILED") {
            setFieldErrors(responseError.fields);
            error.value = t("access.people.validationFailed");
            await focusFirstError();
        } else
            error.value =
                code === "PERSON_VERSION_CONFLICT"
                    ? t("access.people.versionConflict")
                    : code === "PERSON_DOCUMENT_CONFLICT"
                      ? t("access.people.documentConflict")
                      : t("access.people.saveFailed");
    } finally {
        saving.value = false;
    }
}
function personLifecycleChanged(person: PersonDetail): void {
    loadedPerson.value = person;
    version.value = person.version;
    success.value = t("access.people.lifecycleSuccess");
}
function navigateToSection(section: string): void {
    activeSection.value = section;
}
onMounted(() => void initialize());
</script>

<template>
    <section
        class="person-form"
        :aria-label="
            isNew ? t('access.people.create') : t('access.people.edit')
        "
    >
        <header class="person-form__header">
            <h3>{{ operationTitle }}</h3>
        </header>
        <p v-if="loading" role="status">{{ t("access.people.loading") }}</p>
        <form ref="formRoot" class="person-form__editor" @submit.prevent="save">
            <div class="person-form__feedback">
                <p v-if="!online" role="status">{{ t("access.people.offline") }}</p>
                <p v-if="success" role="status" aria-live="polite">
                    {{ success }}
                </p>
                <p v-if="error" role="alert">{{ error }}</p>
                <p v-if="Object.keys(fieldErrors).length" role="alert">
                    {{ t("access.people.validationSummary") }}
                </p>
            </div>
            <div class="person-form__content-frame">
            <section v-show="!loading" class="person-form__tabs">
            <nav
                class="person-form__section-navigation"
                role="tablist"
                :aria-label="t('access.people.sections')"
            >
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeSection === 'basic'"
                    aria-controls="person-basic"
                    @click="navigateToSection('basic')"
                >
                    {{ t("access.people.basicData") }}
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeSection === 'addresses'"
                    aria-controls="person-addresses"
                    @click="navigateToSection('addresses')"
                >
                    {{ t("access.people.addresses") }}
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeSection === 'contacts'"
                    aria-controls="person-contacts"
                    @click="navigateToSection('contacts')"
                >
                    {{ t("access.people.contacts") }}
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeSection === 'bank-accounts'"
                    aria-controls="person-bank-accounts"
                    @click="navigateToSection('bank-accounts')"
                >
                    {{ t("access.people.bankAccounts") }}
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeSection === 'pix-keys'"
                    aria-controls="person-pix-keys"
                    @click="navigateToSection('pix-keys')"
                >
                    {{ t("access.people.pixKeys") }}
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeSection === 'relationships'"
                    aria-controls="person-relationships"
                    @click="navigateToSection('relationships')"
                >
                    {{ t("access.people.relationships") }}
                </button>
            </nav>
            <fieldset
                id="person-basic"
                :disabled="!canEdit || saving"
                class="person-form__fields"
            >
                <section
                    v-show="activeSection === 'basic'"
                    class="person-form__basic-tab"
                    role="tabpanel"
                    aria-label="Dados principais"
                >
                <SegmentedChoiceGroup
                    id="person-type"
                    :label="t('access.people.personType')"
                    :model-value="model.personType"
                    :options="[
                        { value: 'PF', label: 'PF' },
                        { value: 'PJ', label: 'PJ' },
                    ]"
                    @update:model-value="personTypeChanged"
                />
                <label
                    >{{
                        model.personType === "PF"
                            ? t("access.people.name")
                            : t("access.people.legalName")
                    }}<input
                        ref="nameInput"
                        v-model="model.name"
                        :aria-invalid="Boolean(fieldErrors.name)"
                        aria-describedby="person-name-error"
                    /><small
                        v-if="fieldErrors.name"
                        id="person-name-error"
                        role="alert"
                        >{{ fieldErrors.name }}</small
                    ></label
                ><label
                    >{{ t("access.people.alias")
                    }}<input v-model="model.alias" /></label
                ><p class="person-form__display-name">
                    <span>{{ t("access.people.displayName") }}</span>
                    <strong>{{ displayNamePreview || "—" }}</strong>
                </p
                ><label
                    >{{
                        model.personType === "PF"
                            ? t("access.people.cpf")
                            : t("access.people.cnpj")
                    }}<input
                        v-if="model.personType === 'PF'"
                        v-model="model.cpf"
                        :aria-invalid="Boolean(fieldErrors.cpf)"
                        aria-describedby="person-document-error"
                    /><input
                        v-else
                        v-model="model.cnpj"
                        :aria-invalid="Boolean(fieldErrors.cnpj)"
                        aria-describedby="person-document-error"
                    /><small>{{ t("access.people.optional") }}</small
                    ><small
                        v-if="fieldErrors.cpf || fieldErrors.cnpj"
                        id="person-document-error"
                        role="alert"
                        >{{ fieldErrors.cpf || fieldErrors.cnpj }}</small
                    ></label
                >
                <template v-if="model.personType === 'PF'"
                    ><label
                        >{{ t("access.people.rg")
                        }}<input v-model="model.rg" /></label
                    ><label
                        >{{ t("access.people.rgIssuer")
                        }}<input v-model="model.rgIssuer" /></label
                    ><label
                        >{{ t("access.people.pisNis")
                        }}<input v-model="model.pisNis" /></label></template
                ><label
                    >{{ t("access.people.passport")
                    }}<input v-model="model.passportNumber" /></label
                ><label
                    >{{
                        model.personType === "PF"
                            ? t("access.people.birthDate")
                            : t("access.people.foundationDate")
                    }}<input
                        v-if="model.personType === 'PF'"
                        v-model="model.birthDate"
                        type="date" /><input
                        v-else
                        v-model="model.foundationDate"
                        type="date" /></label
                ><label class="wide"
                    >{{ t("access.people.notes")
                    }}<textarea v-model="model.notes" rows="4" />
                </label>
                </section>
                <section
                    id="person-addresses"
                    class="collection"
                    :hidden="activeSection !== 'addresses'"
                    role="tabpanel"
                    tabindex="-1"
                    @keydown.escape="closeCollection"
                    @keydown="trapFocus"
                    :aria-modal="
                        activeCollection === 'addresses' ? 'true' : undefined
                    "
                    :aria-labelledby="
                        activeCollection === 'addresses'
                            ? 'person-addresses-title'
                            : undefined
                    "
                    :class="{
                        'collection--active': activeCollection === 'addresses',
                    }"
                >
                    <div class="collection__heading">
                        <h4 id="person-addresses-title">
                            {{ t("access.people.addresses") }} ({{ addresses.length }})
                        </h4>
                        <button
                            type="button"
                            class="collection__open"
                            @click="openCollection('addresses', $event)"
                        >
                            {{ t("access.people.open") }}
                        </button>
                        <button
                            v-if="activeCollection === 'addresses'"
                            type="button"
                            class="collection__back"
                            @click="closeCollection"
                        >
                            {{ t("access.people.back") }}
                        </button>
                    </div>
                    <button type="button" @click="addAddress">
                        {{ t("access.people.add") }}
                    </button>
                    <div
                        v-for="(address, index) in addresses"
                        :key="`address-${index}`"
                    >
                        <input
                            v-model="address.label"
                            :placeholder="t('access.people.addressLabel')"
                            :aria-label="t('access.people.addressLabel')"
                        /><select
                            v-model="address.addressType"
                            :aria-label="t('access.people.addressType')"
                        >
                            <option>RESIDENTIAL</option>
                            <option>COMMERCIAL</option>
                            <option>BILLING</option>
                            <option>DELIVERY</option>
                            <option>BRANCH</option>
                            <option>OTHER</option></select
                        ><select
                            v-model.number="address.idCountry"
                            :aria-label="t('access.people.country')"
                            @change="countryChanged(address, index)"
                        >
                            <option :value="0">País</option>
                            <option
                                v-for="country in countries"
                                :key="country.id"
                                :value="country.id"
                            >
                                {{ country.name }}
                            </option></select
                        ><template v-if="isBrazil(address)"
                            ><select
                                v-model.number="address.idBrazilState"
                                :aria-label="t('access.people.brazilState')"
                                @change="stateChanged(address, index)"
                            >
                                <option :value="null">UF</option>
                                <option
                                    v-for="state in addressStates[index]"
                                    :key="state.id"
                                    :value="state.id"
                                >
                                    {{ state.abbreviation }}
                                </option></select
                            ><select
                                v-if="address.idBrazilState"
                                v-model.number="address.idBrazilMunicipality"
                                :aria-label="
                                    t('access.people.brazilMunicipality')
                                "
                            >
                                <option :value="null">Município</option>
                                <option
                                    v-for="municipality in addressMunicipalities[
                                        index
                                    ]"
                                    :key="municipality.id"
                                    :value="municipality.id"
                                >
                                    {{ municipality.name }}
                                </option>
                            </select></template
                        ><template v-else
                            ><input
                                v-model="address.stateText"
                                :placeholder="t('access.people.stateText')"
                                :aria-label="
                                    t('access.people.stateText')
                                " /><input
                                v-model="address.cityText"
                                :placeholder="t('access.people.cityText')"
                                :aria-label="
                                    t('access.people.cityText')
                                " /></template
                        ><input
                            v-model="address.street"
                            :placeholder="t('access.people.street')"
                            :aria-label="t('access.people.street')"
                        /><input
                            v-model="address.number"
                            :placeholder="t('access.people.number')"
                            :aria-label="t('access.people.number')"
                        /><input
                            v-model="address.complement"
                            :placeholder="t('access.people.complement')"
                            :aria-label="t('access.people.complement')"
                        /><input
                            v-model="address.district"
                            :placeholder="t('access.people.district')"
                            :aria-label="t('access.people.district')"
                        /><input
                            v-model="address.postalCode"
                            :placeholder="t('access.people.postalCode')"
                            :aria-label="t('access.people.postalCode')"
                            @change="findPostalCandidates(address, index)"
                        /><select
                            v-if="postalCandidates[index]?.length"
                            v-model.number="address.idLocalityReference"
                            :aria-label="t('access.people.localityReference')"
                        >
                            <option :value="null">
                                Referência de localidade opcional
                            </option>
                            <option
                                v-for="candidate in postalCandidates[index]"
                                :key="candidate.id"
                                :value="candidate.id"
                            >
                                {{ candidate.streetType }}
                                {{ candidate.streetName }}
                            </option></select
                        ><input
                            v-model="address.reference"
                            :placeholder="t('access.people.reference')"
                            :aria-label="t('access.people.reference')"
                        /><button
                            type="button"
                            :aria-label="t('access.people.removeItem')"
                            @click="requestRemoval('address', index, $event)"
                        >
                            ×</button
                        ><small
                            v-if="collectionError('addresses', index)"
                            role="alert"
                            >{{ collectionError("addresses", index) }}</small
                        >
                    </div>
                </section>
                <section
                    id="person-bank-accounts"
                    class="collection"
                    :hidden="activeSection !== 'bank-accounts'"
                    role="tabpanel"
                    tabindex="-1"
                    @keydown.escape="closeCollection"
                    @keydown="trapFocus"
                    :aria-modal="
                        activeCollection === 'bankAccounts' ? 'true' : undefined
                    "
                    :aria-labelledby="
                        activeCollection === 'bankAccounts'
                            ? 'person-bank-accounts-title'
                            : undefined
                    "
                    :class="{
                        'collection--active':
                            activeCollection === 'bankAccounts',
                    }"
                >
                    <div class="collection__heading">
                        <h4 id="person-bank-accounts-title">
                            {{ t("access.people.bankAccounts") }}
                            ({{ bankAccounts.length }})
                        </h4>
                        <button
                            type="button"
                            class="collection__open"
                            @click="openCollection('bankAccounts', $event)"
                        >
                            {{ t("access.people.open") }}
                        </button>
                        <button
                            v-if="activeCollection === 'bankAccounts'"
                            type="button"
                            class="collection__back"
                            @click="closeCollection"
                        >
                            {{ t("access.people.back") }}
                        </button>
                    </div>
                    <button type="button" @click="addBankAccount">
                        {{ t("access.people.add") }}
                    </button>
                    <div
                        v-for="(account, index) in bankAccounts"
                        :key="`account-${index}`"
                    >
                        <input
                            v-model="account.label"
                            :placeholder="t('access.people.accountLabel')"
                            :aria-label="t('access.people.accountLabel')"
                        /><select
                            v-model="account.accountType"
                            :aria-label="t('access.people.accountType')"
                        >
                            <option>CHECKING</option>
                            <option>SAVINGS</option>
                            <option>INVESTMENT</option>
                            <option>SALARY</option>
                            <option>OTHER</option></select
                        ><select
                            v-model.number="account.idFinancialInstitution"
                            :aria-label="
                                t('access.people.financialInstitution')
                            "
                        >
                            <option :value="null">Instituição opcional</option>
                            <option
                                v-for="institution in institutions"
                                :key="institution.id"
                                :value="institution.id"
                            >
                                {{ institution.name }}
                            </option></select
                        ><input
                            v-model="account.agency"
                            :placeholder="t('access.people.agency')"
                            :aria-label="t('access.people.agency')"
                        /><input
                            v-model="account.agencyDigit"
                            :placeholder="t('access.people.agencyDigit')"
                            :aria-label="t('access.people.agencyDigit')"
                        /><input
                            v-model="account.accountNumber"
                            :placeholder="t('access.people.accountNumber')"
                            :aria-label="t('access.people.accountNumber')"
                        /><input
                            v-model="account.accountDigit"
                            :placeholder="t('access.people.accountDigit')"
                            :aria-label="t('access.people.accountDigit')"
                        /><button
                            type="button"
                            :aria-label="t('access.people.removeItem')"
                            @click="requestRemoval('bank', index, $event)"
                        >
                            ×</button
                        ><small
                            v-if="collectionError('bankAccounts', index)"
                            role="alert"
                            >{{ collectionError("bankAccounts", index) }}</small
                        >
                    </div>
                </section>
                <section
                    id="person-contacts"
                    class="collection"
                    :hidden="activeSection !== 'contacts'"
                    role="tabpanel"
                    tabindex="-1"
                    @keydown.escape="closeCollection"
                    @keydown="trapFocus"
                    :aria-modal="
                        activeCollection === 'contacts' ? 'true' : undefined
                    "
                    :aria-labelledby="
                        activeCollection === 'contacts'
                            ? 'person-contacts-title'
                            : undefined
                    "
                    :class="{
                        'collection--active': activeCollection === 'contacts',
                    }"
                >
                    <div class="collection__heading">
                        <h4 id="person-contacts-title">
                            {{ t("access.people.contacts") }} ({{ contacts.length }})
                        </h4>
                        <button
                            type="button"
                            class="collection__open"
                            @click="openCollection('contacts', $event)"
                        >
                            {{ t("access.people.open") }}
                        </button>
                        <button
                            v-if="activeCollection === 'contacts'"
                            type="button"
                            class="collection__back"
                            @click="closeCollection"
                        >
                            {{ t("access.people.back") }}
                        </button>
                    </div>
                    <button type="button" @click="addContact">
                        {{ t("access.people.add") }}
                    </button>
                    <div
                        v-for="(contact, index) in contacts"
                        :key="`contact-${index}`"
                    >
                        <select
                            v-model="contact.contactType"
                            :aria-label="t('access.people.contactType')"
                        >
                            <option>EMAIL</option>
                            <option>PHONE</option>
                            <option>MOBILE</option>
                            <option>WHATSAPP</option>
                            <option>WEBSITE</option>
                            <option>OTHER</option></select
                        ><input
                            v-model="contact.value"
                            :aria-label="t('access.people.contactValue')"
                        /><button
                            type="button"
                            :aria-label="t('access.people.removeItem')"
                            @click="requestRemoval('contact', index, $event)"
                        >
                            ×</button
                        ><small
                            v-if="collectionError('contacts', index)"
                            role="alert"
                            >{{ collectionError("contacts", index) }}</small
                        >
                    </div>
                </section>
                <section
                    id="person-pix-keys"
                    class="collection"
                    :hidden="activeSection !== 'pix-keys'"
                    role="tabpanel"
                    tabindex="-1"
                    @keydown.escape="closeCollection"
                    @keydown="trapFocus"
                    :aria-modal="
                        activeCollection === 'pixKeys' ? 'true' : undefined
                    "
                    :aria-labelledby="
                        activeCollection === 'pixKeys'
                            ? 'person-pix-keys-title'
                            : undefined
                    "
                    :class="{
                        'collection--active': activeCollection === 'pixKeys',
                    }"
                >
                    <div class="collection__heading">
                        <h4 id="person-pix-keys-title">
                            {{ t("access.people.pixKeys") }} ({{ pixKeys.length }})
                        </h4>
                        <button
                            type="button"
                            class="collection__open"
                            @click="openCollection('pixKeys', $event)"
                        >
                            {{ t("access.people.open") }}
                        </button>
                        <button
                            v-if="activeCollection === 'pixKeys'"
                            type="button"
                            class="collection__back"
                            @click="closeCollection"
                        >
                            {{ t("access.people.back") }}
                        </button>
                    </div>
                    <button type="button" @click="addPix">
                        {{ t("access.people.add") }}
                    </button>
                    <div v-for="(key, index) in pixKeys" :key="`pix-${index}`">
                        <select
                            v-model="key.keyType"
                            :aria-label="t('access.people.pixKeyType')"
                        >
                            <option>CPF</option>
                            <option>CNPJ</option>
                            <option>EMAIL</option>
                            <option>PHONE</option>
                            <option>RANDOM</option></select
                        ><input
                            v-model="key.value"
                            :aria-label="t('access.people.pixValue')"
                        /><button
                            type="button"
                            :aria-label="t('access.people.removeItem')"
                            @click="requestRemoval('pix', index, $event)"
                        >
                            ×</button
                        ><small
                            v-if="collectionError('pixKeys', index)"
                            role="alert"
                            >{{ collectionError("pixKeys", index) }}</small
                        >
                    </div>
                </section>
                <section
                    id="person-relationships"
                    class="collection"
                    :hidden="activeSection !== 'relationships'"
                    role="tabpanel"
                    tabindex="-1"
                    @keydown.escape="closeCollection"
                    @keydown="trapFocus"
                    :aria-modal="
                        activeCollection === 'relationships'
                            ? 'true'
                            : undefined
                    "
                    :aria-labelledby="
                        activeCollection === 'relationships'
                            ? 'person-relationships-title'
                            : undefined
                    "
                    :class="{
                        'collection--active':
                            activeCollection === 'relationships',
                    }"
                >
                    <div class="collection__heading">
                        <h4 id="person-relationships-title">
                            {{ t("access.people.relationships") }}
                            ({{ relationships.length }})
                        </h4>
                        <button
                            type="button"
                            class="collection__open"
                            @click="openCollection('relationships', $event)"
                        >
                            {{ t("access.people.open") }}
                        </button>
                        <button
                            v-if="activeCollection === 'relationships'"
                            type="button"
                            class="collection__back"
                            @click="closeCollection"
                        >
                            {{ t("access.people.back") }}
                        </button>
                    </div>
                    <button type="button" @click="addRelationship">
                        {{ t("access.people.add") }}
                    </button>
                    <input
                        v-model="relationshipSearch"
                        type="search"
                        :placeholder="t('access.people.relationshipSearch')"
                        :aria-label="t('access.people.relationshipSearch')"
                    />
                    <div
                        v-for="(relationship, index) in relationships"
                        :key="`relationship-${index}`"
                    >
                        <select
                            v-model.number="relationship.idTargetPerson"
                            :aria-label="t('access.people.relationshipTarget')"
                        >
                            <option :value="0">Selecione uma Pessoa</option>
                            <option
                                v-for="person in people.filter(
                                    (candidate) => candidate.id !== personId,
                                )"
                                :key="person.id"
                                :value="person.id"
                            >
                                {{ person.displayName }}
                            </option></select
                        ><select
                            v-model="relationship.relationshipType"
                            :aria-label="t('access.people.relationshipType')"
                        >
                            <option>CHILD_OF</option>
                            <option>PARENT_OF</option>
                            <option>GRANDCHILD_OF</option>
                            <option>GRANDPARENT_OF</option>
                            <option>SPOUSE_OF</option>
                            <option>PARTNER_OF</option>
                            <option>EMPLOYEE_OF</option>
                            <option>EMPLOYER_OF</option>
                            <option>CONTRACTOR_OF</option>
                            <option>CONTRACTING_PARTY_OF</option>
                            <option>OTHER</option></select
                        ><input
                            v-model="relationship.description"
                            :placeholder="
                                t('access.people.relationshipDescription')
                            "
                            :aria-label="
                                t('access.people.relationshipDescription')
                            "
                        /><small
                            >Na outra Pessoa:
                            {{
                                inverseRelationshipType[
                                    relationship.relationshipType
                                ]
                            }}</small
                        ><button
                            type="button"
                            :aria-label="t('access.people.removeItem')"
                            @click="
                                requestRemoval('relationship', index, $event)
                            "
                        >
                            ×</button
                        ><small
                            v-if="collectionError('relationships', index)"
                            role="alert"
                            >{{
                                collectionError("relationships", index)
                            }}</small
                        >
                    </div>
                </section>
            </fieldset>
            </section>
            <footer class="person-form__command-bar">
                <div v-if="loadedPerson" class="person-form__secondary-actions">
                    <PersonDuplicationActions
                        ref="duplicationActions"
                        :tenant-id="tenantId"
                        :person="loadedPerson"
                        :capabilities="capabilities"
                        @duplicated="emit('duplicated', $event)"
                    />
                    <PersonLifecycleActions
                        ref="lifecycleActions"
                        :tenant-id="tenantId"
                        :person="loadedPerson"
                        :capabilities="capabilities"
                        @changed="personLifecycleChanged"
                        @deleted="emit('cancel')"
                    />
                </div>
                <button
                    type="button"
                    class="ui-button ui-button--destructive"
                    @click="requestCancel($event)"
                >
                    <img :src="cancelIcon" alt="" aria-hidden="true" />
                    {{ t("access.people.cancel") }}
                </button>
                <button
                    type="button"
                    class="ui-button ui-button--primary"
                    :disabled="!canSave"
                    @click="save"
                >
                    <img :src="saveIcon" alt="" aria-hidden="true" />
                    {{ saving ? t("access.people.saving") : t("access.people.save") }}
                </button>
            </footer>
            </div>
        </form>
        <section
            v-if="discardConfirmation"
            class="person-form__discard"
            role="dialog"
            aria-modal="true"
            tabindex="-1"
            @keydown="trapFocus"
        >
            <h4 ref="discardTitle" tabindex="-1">
                {{ t("access.workspace.dialog.discardTitle") }}
            </h4>
            <p>{{ t("access.workspace.dialog.discardDescription") }}</p>
            <button type="button" @click="cancelDiscard">
                {{ t("access.workspace.dialog.cancel") }}</button
            ><button type="button" @click="emit('cancel')">
                {{ t("access.workspace.dialog.discard") }}
            </button>
        </section>
        <section
            v-if="removal !== null"
            class="person-form__discard"
            role="dialog"
            aria-modal="true"
            tabindex="-1"
            @keydown="trapFocus"
        >
            <h4 ref="removalTitle" tabindex="-1">
                {{ t("access.workspace.dialog.confirmationTitle") }}
            </h4>
            <p>{{ t("access.people.removeItemConfirmation") }}</p>
            <button type="button" @click="cancelRemoval">
                {{ t("access.workspace.dialog.cancel") }}
            </button>
            <button type="button" @click="confirmRemoval">
                {{ t("access.people.delete") }}
            </button>
        </section>
    </section>
</template>

<style scoped>
.person-form {
    display: grid;
    grid-template-rows: auto minmax(0, 1fr);
    gap: 0;
    min-block-size: 100%;
    block-size: 100%;
    min-height: 0;
    padding: 0;
    overflow: hidden;
}
.person-form__header {
    display: flex;
    align-items: center;
    min-block-size: var(--control-height-md);
    padding: var(--space-3) var(--space-4);
    border-bottom: var(--component-border-width) solid var(--color-border-subtle);
}
.person-form__header h3 {
    margin: 0;
    color: var(--color-text-primary);
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-semibold);
}
.person-form__editor {
    display: grid;
    grid-template-rows: auto minmax(0, 1fr);
    block-size: 100%;
    gap: 0;
    min-height: 0;
    overflow: hidden;
}
.person-form__content-frame {
    display: grid;
    grid-template-rows: minmax(0, 1fr) auto;
    gap: var(--space-3);
    min-width: 0;
    min-height: 0;
    block-size: 100%;
    box-sizing: border-box;
    padding: var(--component-workspace-surface-padding);
    overflow: hidden;
}
.person-form__feedback {
    display: grid;
    gap: var(--space-1);
    margin: var(--space-3) var(--space-4) 0;
}
.person-form__feedback:empty {
    display: none;
}
.person-form__feedback p {
    margin: 0;
}
.person-form__tabs {
    display: grid;
    grid-template-rows: auto minmax(0, 1fr);
    min-height: 0;
    min-width: 0;
    overflow: hidden;
    border: var(--component-border-width) solid var(--color-border-subtle);
    border-radius: var(--radius-md);
    background: var(--color-surface-raised);
}
.person-form__fields {
    display: block;
    block-size: 100%;
    min-height: 0;
    min-width: 0;
    overflow: auto;
    margin: 0;
    padding: var(--space-4);
    border: 0;
}
.person-form__fields:disabled {
    opacity: 0.75;
}
.person-form__section-navigation {
    display: flex;
    gap: 0;
    min-width: 0;
    overflow-x: auto;
    padding: var(--space-2) var(--space-3) 0;
    border-bottom: var(--component-border-width) solid var(--color-border-subtle);
    background: var(--color-surface);
}
.person-form__section-navigation button {
    flex: 0 0 auto;
    min-height: var(--control-height-sm);
    padding: 0 var(--space-3);
    border: 0;
    border-bottom: calc(var(--component-border-width) * 2) solid transparent;
    border-radius: var(--radius-sm) var(--radius-sm) 0 0;
    background: transparent;
    color: var(--color-text-secondary);
    font: inherit;
    cursor: pointer;
}
.person-form__section-navigation button[aria-selected="true"] {
    border-bottom-color: var(--color-action-primary);
    background: var(--color-surface-raised);
    color: var(--color-text-primary);
    font-weight: var(--font-weight-semibold);
}
.person-form__basic-tab {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--space-3);
}
.person-form__display-name {
    display: grid;
    align-content: start;
    gap: var(--space-1);
    margin: 0;
    color: var(--color-text-secondary);
}
.person-form__display-name strong {
    color: var(--color-text-primary);
    font-weight: var(--font-weight-semibold);
}
label {
    display: grid;
    gap: var(--space-1);
}
input,
select,
textarea {
    width: 100%;
    min-height: var(--control-height-sm);
    padding: var(--space-2) var(--space-3);
    border: var(--component-border-width) solid var(--color-border-subtle);
    border-radius: var(--radius-sm);
    background: var(--color-surface-raised);
    color: var(--color-text-primary);
    font: inherit;
}
textarea {
    resize: vertical;
}
input[aria-invalid="true"] {
    border-color: var(--color-danger);
}
.wide,
.collection {
    inline-size: 100%;
}
.collection__heading {
    display: flex;
    align-items: center;
    gap: var(--space-2);
}
.collection__heading h4 {
    flex: 1;
}
.collection__open,
.collection__back {
    display: none;
}
.collection {
    display: grid;
    gap: var(--space-2);
    min-block-size: 100%;
    align-content: start;
    padding: 0;
    border: 0;
}
.collection > div {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-2);
}
.person-form__command-bar {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: var(--space-2);
    min-width: 0;
    padding: 0;
    border: 0;
    background: transparent;
}
.person-form__secondary-actions {
    display: flex;
    flex: 1 1 auto;
    min-width: 0;
    flex-wrap: wrap;
    gap: var(--space-2);
}
.person-form__secondary-actions :deep(.person-duplication-actions),
.person-form__secondary-actions :deep(.person-lifecycle-actions) {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-2);
}
.person-form__command-bar .ui-button {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
}
.person-form__command-bar img {
    inline-size: var(--icon-size-md);
    block-size: var(--icon-size-md);
    object-fit: contain;
}
.person-form__discard {
    position: fixed;
    z-index: 20;
    inset: 50% auto auto 50%;
    width: min(30rem, calc(100vw - 2rem));
    display: grid;
    gap: var(--space-3);
    padding: var(--space-4);
    transform: translate(-50%, -50%);
    border: var(--component-border-width) solid var(--color-border-subtle);
    border-radius: var(--radius-md);
    background: var(--color-surface-raised);
    box-shadow: var(--shadow-lg);
}
@media (max-width: 700px) {
    .person-form__section-navigation {
        padding-inline: var(--space-2);
    }
    .person-form__fields,
    .person-form__basic-tab {
        grid-template-columns: 1fr;
    }
    input,
    select,
    textarea {
        font-size: max(1rem, 16px);
    }
    .person-form__discard {
        inset: auto 0 0;
        width: 100%;
        transform: none;
        padding-bottom: max(var(--space-4), env(safe-area-inset-bottom));
        border-radius: var(--radius-md) var(--radius-md) 0 0;
    }
    .person-form__command-bar {
        position: sticky;
        bottom: 0;
    }
}
</style>
