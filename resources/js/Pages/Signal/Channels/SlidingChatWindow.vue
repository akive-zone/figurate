<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Assistant',
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

const openWindow = () => {
    emit('update:modelValue', true);
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
    <button v-if="!isOpen" type="button" class="signal-slide-chat__tab" @click="openWindow">
        Assistant
    </button>

    <aside v-else class="signal-slide-chat" aria-label="Sliding assistant chat">
        <header class="signal-slide-chat__header">
            <div class="signal-slide-chat__identity">
                <span class="signal-slide-chat__sparkle">✦</span>
                <h3 class="signal-slide-chat__title">{{ title }}</h3>
            </div>

            <button type="button" class="signal-slide-chat__close" @click="closeWindow" aria-label="Close sliding chat">
                ×
            </button>
        </header>

        <section class="signal-slide-chat__messages">
            <article
                v-for="message in props.messages"
                :key="`slide-${message.kind ?? 'message'}-${message.id ?? message.created_at}`"
                class="signal-slide-bubble"
                :class="{ 'signal-slide-bubble--outgoing': isOutgoing(message) }"
            >
                {{ message.content }}
            </article>

            <p v-if="props.messages.length === 0" class="signal-slide-chat__empty">
                Responses are generated using AI and may contain mistakes.
            </p>
        </section>

        <form class="signal-slide-chat__composer" @submit.prevent="submitDraft">
            <input
                v-model="draft"
                type="text"
                class="signal-slide-chat__input"
                placeholder="Ask a question..."
                :disabled="props.sending"
            />
            <button type="submit" class="signal-slide-chat__send" :disabled="!canSend">
                ↑
            </button>
        </form>
    </aside>
</template>
