<script setup>
import { computed, toRef } from 'vue';
import { useA2uiSurface } from '../composables/useA2uiSurface';

const props = defineProps({
    payload: {
        type: Object,
        default: () => ({}),
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    protocol: {
        type: String,
        default: 'a2ui',
    },
    defaultSubmitLabel: {
        type: String,
        default: 'Submit',
    },
    fieldRegistry: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['submit-action']);

const {
    surface,
    fields,
    actions,
    form,
    fieldKey,
    labelFor,
    resolveFieldComponent,
    buildActionPayload,
} = useA2uiSurface(toRef(props, 'payload'), {
    protocol: computed(() => props.protocol),
    defaultSubmitLabel: computed(() => props.defaultSubmitLabel),
    fieldRegistry: computed(() => props.fieldRegistry),
});

const submit = (action) => {
    emit('submit-action', buildActionPayload(action));
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

            <component
                :is="resolveFieldComponent(field)"
                :model-value="form[fieldKey(field, index)]"
                :field="field"
                :disabled="disabled"
                @update:model-value="form[fieldKey(field, index)] = $event"
            />
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
