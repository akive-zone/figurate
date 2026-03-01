<script setup>
import DOMPurify from 'dompurify';
import { marked } from 'marked';

const props = defineProps({
    channelId: {
        type: String,
        default: '',
    },
    messages: {
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

const emit = defineEmits(['manage-mcp']);

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
                <p class="thread__kicker">Channel</p>
                <h2 class="thread__title">Channel {{ props.channelId }}</h2>
                <p class="thread__meta">Relevant channel posts for your account.</p>
            </div>
            <div class="thread__header-actions">
                <button type="button" class="button button--outline" @click="emit('manage-mcp')">
                    Manage MCP
                </button>
            </div>
        </header>

        <section class="thread__messages">
            <article
                v-for="message in props.messages"
                :key="`${message.kind ?? 'message'}-${message.scope ?? 'channel'}-${message.id}`"
                class="message"
            >
                <p class="message__author">
                    {{ message.scope === 'channel' ? 'Channel' : 'Conversation' }}
                </p>
                <div class="message__content" v-html="renderMessageContent(message.content)" />
                <ul v-if="message.attachments?.length" class="thread__attachments">
                    <li v-for="attachment in message.attachments" :key="attachment.path">
                        {{ attachment.name }} ({{ attachment.mime }})
                    </li>
                </ul>
                <p class="message__time">{{ formatTimestamp(message.created_at) }}</p>
            </article>

            <article v-if="!props.messages.length" class="empty">
                <h3>No posts yet</h3>
                <p v-if="props.errorMessage" class="error">{{ props.errorMessage }}</p>
                <p v-else-if="props.isLoading">Loading channel posts...</p>
                <p v-else>No channel posts yet.</p>
            </article>
        </section>
    </section>
</template>
