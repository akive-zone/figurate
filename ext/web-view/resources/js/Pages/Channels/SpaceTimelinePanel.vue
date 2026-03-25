<script setup>
import DOMPurify from 'dompurify';
import { marked } from 'marked';

const props = defineProps({
    spaceId: {
        type: String,
        default: '',
    },
    messages: {
        type: Array,
        default: () => [],
    },
    threads: {
        type: Array,
        default: () => [],
    },
    isLoading: {
        type: Boolean,
        default: false,
    },
    errorMessage: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['manage-mcp', 'open-thread', 'create-thread']);

const formatTimestamp = (value) => new Date(value).toLocaleString();

const renderMessageContent = (content) => {
    const rawContent = (content ?? '').toString();

    if (rawContent.trim() === '') {
        return '';
    }

    const parsed = marked.parse(rawContent, {
        breaks: true,
        gfm: true,
    });

    return DOMPurify.sanitize(typeof parsed === 'string' ? parsed : '', {
        USE_PROFILES: {
            html: true,
        },
    });
};
</script>

<template>
    <section class="thread">
        <header class="thread__header">
            <div>
                <p class="thread__kicker">Space</p>
                <h2 class="thread__title">Space {{ props.spaceId }}</h2>
                <p class="thread__meta">Relevant space posts for your account.</p>
            </div>
            <div class="thread__header-actions">
                <button type="button" class="button" @click="emit('create-thread')">
                    New Workstream
                </button>
                <button type="button" class="button button--outline" @click="emit('manage-mcp')">
                    Manage MCP
                </button>
            </div>
        </header>

        <section class="thread__messages">
            <template v-for="message in props.messages" :key="`${message.kind ?? 'message'}-${message.scope ?? 'space'}-${message.id}`">
                <article
                    v-if="message.kind === 'thread_event'"
                    class="history-marker"
                >
                    <div class="history-marker__line"></div>
                    <div class="history-marker__content">
                        <span class="history-marker__icon">{{ message.nature === 'agent' ? '✦' : '💬' }}</span>
                        <p class="history-marker__text" v-html="renderMessageContent(message.content)"></p>
                        <button
                            type="button"
                            class="history-marker__action"
                            @click="emit('open-thread', { threadId: message.id, thread: message })"
                        >
                            Jump to conversation →
                        </button>
                    </div>
                    <p class="message__time">{{ formatTimestamp(message.created_at) }}</p>
                </article>

                <article
                    v-else
                    class="message"
                    :class="[
                        message.scope === 'space' ? 'message--space' : 'message--conversation'
                    ]"
                >
                    <p class="message__author">
                        {{ message.scope === 'space' ? (message.type ? message.type.toUpperCase() : 'Space') : 'Conversation' }}
                    </p>
                    <div class="message__content" v-html="renderMessageContent(message.content)" />
                    <ul v-if="message.attachments?.length" class="thread__attachments">
                        <li v-for="attachment in message.attachments" :key="attachment.path">
                            {{ attachment.name }} ({{ attachment.mime }})
                        </li>
                    </ul>
                    <p class="message__time">{{ formatTimestamp(message.created_at) }}</p>
                </article>
            </template>

            <article v-if="!props.messages.length" class="empty">
                <header class="empty__header">
                    <h3>Start a Conversation</h3>
                    <p>Start a new conversation or jump into an existing thread.</p>
                </header>

                <div v-if="props.threads.length > 0" class="empty__grid">
                    <button
                        v-for="thread in props.threads"
                        :key="thread.id"
                        type="button"
                        class="thread-card"
                        @click="emit('open-thread', { threadId: thread.id, thread: thread })"
                    >
                        <span class="thread-card__icon">{{ (thread.nature === 'agent' || thread.nature === 'mixed') ? '✦' : '💬' }}</span>
                        <div class="thread-card__body">
                            <p class="thread-card__title">{{ thread.title }}</p>
                            <p class="thread-card__meta">Started {{ formatTimestamp(thread.created_at) }}</p>
                        </div>
                    </button>
                </div>

                <div v-else class="empty__fallback">
                    <p v-if="props.errorMessage" class="error">{{ props.errorMessage }}</p>
                    <p v-else-if="props.isLoading">Loading workspace...</p>
                    <p v-else>No conversations started yet.</p>
                </div>
            </article>
        </section>
    </section>
</template>
