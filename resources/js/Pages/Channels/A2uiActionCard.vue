<script setup>
import { A2uiSurfaceRenderer } from '../../a2ui';

defineProps({
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
</script>

<template>
    <A2uiSurfaceRenderer
        :payload="payload"
        :disabled="disabled"
        @submit-action="emit('submit', $event)"
    />
</template>
