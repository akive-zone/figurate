<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: true,
    },
    title: {
        type: String,
        default: 'Chat',
    },
    subtitle: {
        type: String,
        default: 'Active now',
    },
    messages: {
        type: Array,
        default: () => [],
    },
    outgoingScopes: {
        type: Array,
        default: () => ['request', 'conversation'],
    },
    sending: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'send']);

const draft = ref('');
const isOpen = computed(() => props.modelValue === true);
const canSend = computed(() => draft.value.trim() !== '' && !props.sending);

const closeWindow = () => {
    emit('update:modelValue', false);
};

const isOutgoing = (message) => {
    return props.outgoingScopes.includes(message?.scope ?? '');
};

const submitDraft = () => {
    const content = draft.value.trim();
    if (content === '' || props.sending) {
        return;
    }

    emit('send', content);
    draft.value = '';
};
</script>

<template>
    <section v-if="isOpen" class="float-chat" aria-label="Floating chat">
        <header class="float-chat__header">
            <div class="float-chat__identity">
                <div class="float-chat__avatar">{{ title.slice(0, 1).toUpperCase() }}</div>
                <div>
                    <p class="float-chat__title">{{ title }}</p>
                    <p class="float-chat__subtitle">{{ subtitle }}</p>
                </div>
            </div>

            <button type="button" class="float-chat__close" @click="closeWindow" aria-label="Close floating chat">×</button>
        </header>

        <section class="float-chat__messages">
            <article
                v-for="message in props.messages"
                :key="`float-${message.kind ?? 'message'}-${message.id ?? message.created_at}`"
                class="float-bubble"
                :class="{ 'float-bubble--outgoing': isOutgoing(message) }"
            >
                {{ message.content }}
            </article>
            <p v-if="props.messages.length === 0" class="float-chat__empty">No messages yet.</p>
        </section>

        <form class="float-chat__composer" @submit.prevent="submitDraft">
            <input
                v-model="draft"
                type="text"
                class="float-chat__input"
                placeholder="Message..."
                :disabled="props.sending"
            />
            <button type="submit" class="float-chat__send" :disabled="!canSend">
                Send
            </button>
        </form>
    </section>
</template>
