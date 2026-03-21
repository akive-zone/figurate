<script setup>
defineProps({
    modelValue: {
        type: [String, Number, null],
        default: null,
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

const onInput = (event) => {
    const rawValue = event.target.value;

    if (rawValue === '') {
        emit('update:modelValue', null);

        return;
    }

    const numeric = Number(rawValue);

    emit('update:modelValue', Number.isNaN(numeric) ? null : numeric);
};
</script>

<template>
    <input
        :value="modelValue ?? ''"
        type="number"
        class="slide-chat__input"
        :placeholder="field.placeholder ?? ''"
        :disabled="disabled"
        @input="onInput"
    />
</template>
