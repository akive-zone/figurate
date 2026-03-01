<script setup>
import FloatingChatWindow from './FloatingChatWindow.vue';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: true,
    },
    messages: {
        type: Array,
        default: () => [],
    },
    sending: {
        type: Boolean,
        default: false,
    },
    activeThreadId: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue', 'send']);
</script>

<template>
    <FloatingChatWindow
        :model-value="props.modelValue"
        title="Human conversation"
        :subtitle="props.activeThreadId !== '' ? 'Human thread active' : 'Select a thread'"
        :messages="props.messages"
        :sending="props.sending"
        @update:modelValue="emit('update:modelValue', $event)"
        @send="emit('send', $event)"
    />
</template>
