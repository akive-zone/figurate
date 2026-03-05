<script setup>
import { computed, reactive } from 'vue';

const props = defineProps({
    payload: {
        type: Object,
        default: () => ({}),
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['submit']);

const surface = computed(() => {
    if (props.payload?.surfaceUpdate && typeof props.payload.surfaceUpdate === 'object') {
        return props.payload.surfaceUpdate;
    }

    if (props.payload?.beginRendering && typeof props.payload.beginRendering === 'object') {
        return props.payload.beginRendering;
    }

    return props.payload ?? {};
});

const fields = computed(() => {
    const direct = Array.isArray(surface.value?.fields) ? surface.value.fields : [];

    if (direct.length > 0) {
        return direct;
    }

    return Array.isArray(surface.value?.schema?.fields) ? surface.value.schema.fields : [];
});

const actions = computed(() => {
    const configured = Array.isArray(surface.value?.actions) ? surface.value.actions : [];

    if (configured.length > 0) {
        return configured;
    }

    return [{
        id: 'submit',
        type: 'submit',
        label: 'Submit',
    }];
});

const form = reactive({});

const fieldKey = (field, index) => {
    const candidates = [
        field?.key,
        field?.name,
        field?.id,
    ];

    for (const candidate of candidates) {
        if (typeof candidate === 'string' && candidate.trim() !== '') {
            return candidate.trim();
        }
    }

    return `field_${index + 1}`;
};

const fieldType = (field) => {
    const resolved = (field?.type ?? field?.input ?? 'text').toString().trim().toLowerCase();

    if (['boolean', 'checkbox', 'toggle'].includes(resolved)) {
        return 'boolean';
    }

    if (['select', 'dropdown'].includes(resolved)) {
        return 'select';
    }

    if (['number', 'integer', 'float'].includes(resolved)) {
        return 'number';
    }

    if (['textarea', 'multiline'].includes(resolved)) {
        return 'textarea';
    }

    return 'text';
};

const labelFor = (field, index) => {
    const candidates = [
        field?.label,
        field?.title,
        field?.name,
    ];

    for (const candidate of candidates) {
        if (typeof candidate === 'string' && candidate.trim() !== '') {
            return candidate.trim();
        }
    }

    return `Field ${index + 1}`;
};

const optionsFor = (field) => {
    const options = Array.isArray(field?.options) ? field.options : [];

    return options
        .map((option) => {
            if (typeof option === 'string') {
                return {
                    label: option,
                    value: option,
                };
            }

            if (option && typeof option === 'object') {
                const value = (option.value ?? option.id ?? option.key ?? '').toString();
                const label = (option.label ?? option.name ?? value).toString();

                if (value.trim() !== '' && label.trim() !== '') {
                    return {
                        label: label.trim(),
                        value: value.trim(),
                    };
                }
            }

            return null;
        })
        .filter(Boolean);
};

const submit = (action) => {
    const actionName = (action?.name ?? action?.type ?? action?.id ?? 'submit').toString().trim() || 'submit';
    const actionId = (action?.id ?? actionName).toString().trim();
    const surfaceId = (surface.value?.id ?? props.payload?.surfaceId ?? '').toString().trim();
    const sourceComponentId = (action?.sourceComponentId ?? '').toString().trim();

    emit('submit', {
        protocol: 'a2ui',
        name: actionName,
        id: actionId !== '' ? actionId : null,
        surfaceId: surfaceId !== '' ? surfaceId : null,
        sourceComponentId: sourceComponentId !== '' ? sourceComponentId : null,
        timestamp: new Date().toISOString(),
        context: {
            surfaceTitle: (surface.value?.title ?? '').toString().trim() || null,
        },
        values: { ...form },
    });
};
</script>

<template>
    <section class="slide-a2ui-card">
        <p v-if="surface.title" class="slide-a2ui-card__title">{{ surface.title }}</p>
        <p v-if="surface.question || surface.description" class="slide-a2ui-card__description">
            {{ surface.question ?? surface.description }}
        </p>

        <div v-for="(field, index) in fields" :key="fieldKey(field, index)" class="slide-a2ui-card__field">
            <label class="slide-a2ui-card__label">{{ labelFor(field, index) }}</label>

            <input
                v-if="fieldType(field) === 'text'"
                v-model="form[fieldKey(field, index)]"
                type="text"
                class="slide-chat__input"
                :placeholder="field.placeholder ?? ''"
                :disabled="disabled"
            />

            <textarea
                v-else-if="fieldType(field) === 'textarea'"
                v-model="form[fieldKey(field, index)]"
                class="slide-chat__input"
                :placeholder="field.placeholder ?? ''"
                :disabled="disabled"
                rows="3"
            />

            <input
                v-else-if="fieldType(field) === 'number'"
                v-model.number="form[fieldKey(field, index)]"
                type="number"
                class="slide-chat__input"
                :placeholder="field.placeholder ?? ''"
                :disabled="disabled"
            />

            <select
                v-else-if="fieldType(field) === 'select'"
                v-model="form[fieldKey(field, index)]"
                class="slide-chat__input"
                :disabled="disabled"
            >
                <option value="">Select...</option>
                <option
                    v-for="option in optionsFor(field)"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>

            <label v-else class="slide-a2ui-card__toggle">
                <input
                    v-model="form[fieldKey(field, index)]"
                    type="checkbox"
                    :disabled="disabled"
                />
                <span>{{ field.help ?? 'Toggle' }}</span>
            </label>
        </div>

        <footer class="slide-a2ui-card__actions">
            <button
                v-for="action in actions"
                :key="(action.id ?? action.type ?? 'submit').toString()"
                type="button"
                class="slide-chat__send"
                :disabled="disabled"
                @click="submit(action)"
            >
                {{ action.label ?? action.title ?? 'Submit' }}
            </button>
        </footer>
    </section>
</template>
