<script setup>
defineProps({
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
</script>

<template>
    <input
        :value="modelValue ?? ''"
        type="text"
        class="slide-chat__input"
        :placeholder="field.placeholder ?? ''"
        :disabled="disabled"
        @input="emit('update:modelValue', $event.target.value)"
    />
</template>
