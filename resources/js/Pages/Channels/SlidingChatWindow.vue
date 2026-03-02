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
    subtitle: {
        type: String,
        default: 'Active now',
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
    outgoingScopes: {
        type: Array,
        default: () => ['request', 'conversation'],
    },
    sending: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'send', 'switch-thread', 'close-thread', 'retry-turn']);

const draft = ref('');
const isOpen = computed(() => props.modelValue === true);
const canSend = computed(() => draft.value.trim() !== '' && !props.sending);
const selectedTurnContext = ref(null);

const closeWindow = () => {
    emit('update:modelValue', false);
};

const isOutgoing = (message) => {
    return props.outgoingScopes.includes(message?.scope ?? '');
};

const turnStatusLabel = (turn) => {
    const status = (turn?.status ?? '').toString().toLowerCase();

    if (status === 'completed') {
        return 'Completed';
    }

    if (status === 'failed') {
        return 'Failed';
    }

    if (status === 'pending') {
        return 'Pending';
    }

    return 'Unknown';
};

const turnSummary = (turn) => {
    const status = (turn?.status ?? '').toString().toLowerCase();
    const error = (turn?.error_message ?? '').toString().trim();
    const text = (turn?.assistant_text ?? '').toString().trim();

    if (status === 'failed' && error !== '') {
        return error;
    }

    if (text !== '') {
        return text.length > 88 ? `${text.slice(0, 88)}...` : text;
    }

    if (status === 'pending') {
        return 'Waiting for handler response...';
    }

    return 'No response content yet.';
};

const totalTokens = (turn) => {
    const usage = turn?.usage ?? {};
    const promptTokens = Number(usage?.prompt_tokens ?? 0);
    const completionTokens = Number(usage?.completion_tokens ?? 0);
    const reasoningTokens = Number(usage?.reasoning_tokens ?? 0);
    const total = promptTokens + completionTokens + reasoningTokens;

    return total > 0 ? total : null;
};

const openTurnContext = (turn, promptText = '') => {
    selectedTurnContext.value = {
        ...turn,
        prompt_text: (promptText ?? '').toString(),
    };
};

const closeTurnContext = () => {
    selectedTurnContext.value = null;
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
    <aside v-if="isOpen" class="slide-chat" aria-label="Sliding assistant chat">
        <header class="slide-chat__header">
            <div class="slide-chat__identity">
                <span class="slide-chat__sparkle">✦</span>
                <div>
                    <h3 class="slide-chat__title">{{ title }}</h3>
                    <p class="float-chat__subtitle">{{ subtitle }}</p>
                </div>
            </div>

            <button type="button" class="slide-chat__close" @click="closeWindow" aria-label="Close sliding chat">
                ×
            </button>
        </header>

        <nav v-if="props.threads.length > 1" class="workspace-tabs workspace-tabs--mini">
            <div
                v-for="thread in props.threads"
                :key="thread.id"
                class="workspace-tab workspace-tab--mini"
                :class="{ 'workspace-tab--active': props.activeThreadId === thread.id }"
                @click="emit('switch-thread', thread.id)"
            >
                <span class="workspace-tab__title">{{ thread.title ?? 'Thread' }}</span>
                <button
                    type="button"
                    class="workspace-tab__close"
                    @click.stop="emit('close-thread', thread.id)"
                >
                    ×
                </button>
            </div>
        </nav>

        <section class="slide-chat__messages">
            <article
                v-for="message in props.messages"
                :key="`slide-${message.kind ?? 'message'}-${message.id ?? message.created_at}`"
                class="slide-message"
            >
                <template v-if="message.kind === 'turn_prompt'">
                    <section class="slide-prompt">
                        <article class="slide-bubble slide-bubble--outgoing">
                            {{ message.content }}
                        </article>

                        <article
                            v-for="turn in (Array.isArray(message.turns) && message.turns.length > 0 ? message.turns : [{ status: 'pending', actor_key: 'handler', assistant_text: '', error_message: '', invocation_id: null, usage: {}, prompt_text: message.content }])"
                            :key="`assistant-${turn.id ?? turn.invocation_id ?? message.id}`"
                            class="slide-bubble slide-bubble--assistant"
                        >
                            <p class="slide-bubble__text">
                                {{ turnSummary(turn) }}
                            </p>
                            <footer class="slide-bubble__meta">
                                <span class="slide-turn__actor">{{ turn.actor_key ?? 'handler' }}</span>
                                <button
                                    type="button"
                                    class="slide-turn-chip"
                                    @click="openTurnContext(turn, message.content)"
                                >
                                    Context
                                    <span class="slide-turn__status" :class="`slide-turn__status--${(turn.status ?? '').toString().toLowerCase()}`">
                                        {{ turnStatusLabel(turn) }}
                                    </span>
                                </button>
                            </footer>
                        </article>
                    </section>
                </template>
                <template v-else>
                    <article
                        class="slide-bubble"
                        :class="{ 'slide-bubble--outgoing': isOutgoing(message) }"
                    >
                        {{ message.content }}
                    </article>
                </template>
            </article>

            <p v-if="props.messages.length === 0" class="slide-chat__empty">
                Responses are generated using AI and may contain mistakes.
            </p>
        </section>

        <teleport to="body">
            <div v-if="selectedTurnContext" class="turn-drawer-overlay" @click.self="closeTurnContext">
                <aside class="turn-drawer" aria-label="Turn context details">
                    <header class="turn-drawer__header">
                        <h4 class="turn-drawer__title">Turn context</h4>
                        <button type="button" class="slide-chat__close" @click="closeTurnContext">×</button>
                    </header>
                    <section class="turn-drawer__body">
                        <p class="turn-drawer__label">Prompt</p>
                        <p class="turn-drawer__prompt">{{ selectedTurnContext.prompt_text || 'N/A' }}</p>

                        <p class="turn-drawer__label">Status</p>
                        <p>
                            <span class="slide-turn__status" :class="`slide-turn__status--${(selectedTurnContext.status ?? '').toString().toLowerCase()}`">
                                {{ turnStatusLabel(selectedTurnContext) }}
                            </span>
                        </p>

                        <p v-if="selectedTurnContext.assistant_text" class="turn-drawer__label">Handler response</p>
                        <p v-if="selectedTurnContext.assistant_text" class="slide-turn__text">{{ selectedTurnContext.assistant_text }}</p>

                        <p v-if="selectedTurnContext.error_message" class="turn-drawer__label">Error</p>
                        <p v-if="selectedTurnContext.error_message" class="slide-turn__error">{{ selectedTurnContext.error_message }}</p>

                        <p v-if="selectedTurnContext.invocation_id" class="turn-drawer__meta">Turn: {{ selectedTurnContext.invocation_id }}</p>
                        <p v-if="totalTokens(selectedTurnContext)" class="turn-drawer__meta">Tokens: {{ totalTokens(selectedTurnContext) }}</p>
                    </section>
                    <footer class="turn-drawer__footer">
                        <button
                            v-if="(selectedTurnContext.status ?? '').toString().toLowerCase() === 'failed'"
                            type="button"
                            class="slide-turn__retry"
                            @click="emit('retry-turn', selectedTurnContext)"
                        >
                            Retry
                        </button>
                    </footer>
                </aside>
            </div>
        </teleport>

        <form class="slide-chat__composer" @submit.prevent="submitDraft">
            <input
                v-model="draft"
                type="text"
                class="slide-chat__input"
                placeholder="Ask a question..."
                :disabled="props.sending"
            />
            <button type="submit" class="slide-chat__send" :disabled="!canSend">
                ↑
            </button>
        </form>
    </aside>
</template>
