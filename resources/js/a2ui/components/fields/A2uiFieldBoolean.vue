<script setup>
defineProps({
    modelValue: {
        type: [Boolean, null],
        default: false,
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
    <label class="slide-a2ui-card__toggle">
        <input
            :checked="Boolean(modelValue)"
            type="checkbox"
            :disabled="disabled"
            @change="emit('update:modelValue', $event.target.checked)"
        />
        <span>{{ field.help ?? 'Toggle' }}</span>
    </label>
</template>
