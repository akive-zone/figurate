<script setup>
import SlidingChatWindow from './SlidingChatWindow.vue';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    activeThreadId: {
        type: String,
        default: '',
    },
    threads: {
        type: Array,
        default: () => [],
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

const emit = defineEmits(['update:modelValue', 'send', 'switch-thread', 'close-thread', 'retry-turn', 'submit-a2ui-action']);
</script>

<template>
    <SlidingChatWindow
        :model-value="props.modelValue"
        title="Agent conversation"
        :subtitle="props.activeThreadId !== '' ? 'AI thread active' : 'Assistant'"
        :active-thread-id="props.activeThreadId"
        :threads="props.threads"
        :messages="props.messages"
        :sending="props.sending"
        @update:modelValue="emit('update:modelValue', $event)"
        @send="emit('send', $event)"
        @switch-thread="emit('switch-thread', $event)"
        @close-thread="emit('close-thread', $event)"
        @retry-turn="emit('retry-turn', $event)"
        @submit-a2ui-action="emit('submit-a2ui-action', $event)"
    />
</template>
