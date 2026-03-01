<script setup>
import SlidingChatWindow from './SlidingChatWindow.vue';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    messages: {
        type: Array,
        default: () => [],
    },
    sending: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'send']);
</script>

<template>
    <SlidingChatWindow
        :model-value="props.modelValue"
        title="Agent conversation"
        :messages="props.messages"
        :sending="props.sending"
        @update:modelValue="emit('update:modelValue', $event)"
        @send="emit('send', $event)"
    />
</template>
