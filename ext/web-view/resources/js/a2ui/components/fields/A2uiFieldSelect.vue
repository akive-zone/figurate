<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: [String, Number, null],
        default: '',
    },
    field: {
        type: Object,
        default: () => ({}),
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

const options = computed(() => {
    const values = Array.isArray(props.field?.options) ? props.field.options : [];

    return values
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
});
</script>

<template>
    <select
        :value="modelValue ?? ''"
        class="slide-chat__input"
        :disabled="disabled"
        @change="emit('update:modelValue', $event.target.value)"
    >
        <option value="">Select...</option>
        <option
            v-for="option in options"
            :key="option.value"
            :value="option.value"
        >
            {{ option.label }}
        </option>
    </select>
</template>
