import A2uiFieldBoolean from '../components/fields/A2uiFieldBoolean.vue';
import A2uiFieldNumber from '../components/fields/A2uiFieldNumber.vue';
import A2uiFieldSelect from '../components/fields/A2uiFieldSelect.vue';
import A2uiFieldText from '../components/fields/A2uiFieldText.vue';
import A2uiFieldTextarea from '../components/fields/A2uiFieldTextarea.vue';

const FIELD_TYPE_MAP = {
    boolean: 'boolean',
    checkbox: 'boolean',
    toggle: 'boolean',
    select: 'select',
    dropdown: 'select',
    number: 'number',
    integer: 'number',
    float: 'number',
    textarea: 'textarea',
    multiline: 'textarea',
    text: 'text',
};

const DEFAULT_COMPONENTS = {
    text: A2uiFieldText,
    textarea: A2uiFieldTextarea,
    number: A2uiFieldNumber,
    select: A2uiFieldSelect,
    boolean: A2uiFieldBoolean,
};

export const normalizeA2uiFieldType = (field) => {
    const rawType = (field?.type ?? field?.input ?? 'text').toString().trim().toLowerCase();

    return FIELD_TYPE_MAP[rawType] ?? 'text';
};

export const createA2uiFieldRegistry = (overrides = {}) => {
    return {
        ...DEFAULT_COMPONENTS,
        ...(overrides && typeof overrides === 'object' ? overrides : {}),
    };
};

export const resolveA2uiFieldComponent = (registry, field) => {
    const normalizedType = normalizeA2uiFieldType(field);

    return registry?.[normalizedType] ?? DEFAULT_COMPONENTS.text;
};
