<script setup>
import SignalLayout from '../../../Layouts/SignalLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    conversations: {
        type: Array,
        required: true,
    },
});

const formatTimestamp = (value) => {
    if (!value) {
        return 'No messages yet';
    }

    return new Date(value).toLocaleString();
};
</script>

<template>
    <Head title="Signal Chat" />

    <SignalLayout>
        <section class="signal-grid">
            <article
                v-for="conversation in conversations"
                :key="conversation.id"
                class="signal-card"
            >
                <div class="signal-card__top">
                    <p class="signal-card__name">{{ conversation.profile?.display_name ?? 'Unknown profile' }}</p>
                    <p class="signal-card__time">{{ formatTimestamp(conversation.last_message_at) }}</p>
                </div>

                <h2 class="signal-card__title">{{ conversation.request?.title ?? 'Untitled request' }}</h2>
                <p class="signal-card__status">Request: {{ conversation.request?.status ?? 'pending' }}</p>
                <p class="signal-card__preview">{{ conversation.latest_message?.body ?? 'No messages yet.' }}</p>

                <Link :href="`/signal/chat/${conversation.id}`" class="signal-link signal-link--cta">
                    Open Chat
                </Link>
            </article>

            <article v-if="!conversations.length" class="signal-empty">
                <h2>No conversations yet</h2>
                <p>Start a request to open the first conversation and begin the fulfillment flow.</p>
                <Link href="/signal/requests/new" class="signal-button">Create Request</Link>
            </article>
        </section>
    </SignalLayout>
</template>
