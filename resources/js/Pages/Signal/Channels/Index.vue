<script setup>
import SignalLayout from '../../../Layouts/SignalLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    channels: {
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
                v-for="channel in channels"
                :key="channel.id"
                class="signal-card"
            >
                <div class="signal-card__top">
                    <p class="signal-card__name">{{ channel.profile?.display_name ?? 'Unknown profile' }}</p>
                    <p class="signal-card__time">{{ formatTimestamp(channel.last_message_at) }}</p>
                </div>

                <h2 class="signal-card__title">{{ channel.request?.title ?? 'Untitled request' }}</h2>
                <p class="signal-card__status">Request: {{ channel.request?.status ?? 'pending' }}</p>
                <p class="signal-card__preview">{{ channel.latest_message?.body ?? 'No messages yet.' }}</p>

                <Link :href="`/signal/chat/${channel.id}`" class="signal-link signal-link--cta">
                    Open Chat
                </Link>
            </article>

            <article v-if="!channels.length" class="signal-empty">
                <h2>No channels yet</h2>
                <p>Start a request to open the first channel and begin the fulfillment flow.</p>
                <Link href="/signal/requests/new" class="signal-button">Create Request</Link>
            </article>
        </section>
    </SignalLayout>
</template>
