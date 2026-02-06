<script setup>
import SignalLayout from '../../../Layouts/SignalLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    conversation: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id);

const form = useForm({
    body: '',
});

const submitMessage = () => {
    form.post(`/signal/chat/${props.conversation.id}/messages`, {
        preserveScroll: true,
        onSuccess: () => form.reset('body'),
    });
};

const acceptQuote = (quoteId) => {
    router.post(`/signal/chat/${props.conversation.id}/quotes/${quoteId}/accept`, {}, {
        preserveScroll: true,
    });
};

const formatTimestamp = (value) => new Date(value).toLocaleString();
</script>

<template>
    <Head :title="`Chat #${conversation.id}`" />

    <SignalLayout>
        <section class="signal-thread">
            <header class="signal-thread__header">
                <div>
                    <p class="signal-thread__kicker">Conversation</p>
                    <h2 class="signal-thread__title">{{ conversation.request?.title ?? 'Untitled Request' }}</h2>
                    <p class="signal-thread__meta">
                        With {{ conversation.profile?.display_name ?? 'Unknown profile' }}
                    </p>
                </div>
                <Link href="/signal" class="signal-link">Back</Link>
            </header>

            <section class="signal-thread__request" v-if="conversation.request">
                <h3>Request Summary</h3>
                <p>{{ conversation.request.description }}</p>
                <p class="signal-card__status">Status: {{ conversation.request.status }}</p>

                <div v-if="conversation.request.quotes?.length" class="signal-quote-list">
                    <h4>Quotes</h4>
                    <article
                        v-for="quote in conversation.request.quotes"
                        :key="quote.id"
                        class="signal-quote"
                    >
                        <p class="signal-quote__amount">
                            {{ quote.currency }} {{ Number(quote.amount).toFixed(2) }}
                        </p>
                        <p class="signal-card__status">Status: {{ quote.status }}</p>
                        <p v-if="quote.details">{{ quote.details }}</p>
                        <button
                            v-if="conversation.actions.can_accept_quote && quote.status === 'pending'"
                            type="button"
                            class="signal-button"
                            @click="acceptQuote(quote.id)"
                        >
                            Accept Quote and Kickoff Order
                        </button>
                    </article>
                </div>

                <p v-if="conversation.request.order" class="signal-card__status">
                    Order #{{ conversation.request.order.id }} is {{ conversation.request.order.status }}.
                </p>
            </section>

            <section class="signal-thread__messages">
                <article
                    v-for="message in conversation.messages"
                    :key="message.id"
                    class="signal-message"
                    :class="{
                        'signal-message--mine': message.sender_id === currentUserId,
                    }"
                >
                    <p class="signal-message__author">{{ message.sender_name }}</p>
                    <p>{{ message.body }}</p>
                    <p class="signal-message__time">{{ formatTimestamp(message.created_at) }}</p>
                </article>

                <article v-if="!conversation.messages.length" class="signal-empty">
                    <h3>No messages yet</h3>
                    <p>Send the first message to begin the chat side of fulfillment.</p>
                </article>
            </section>

            <form class="signal-form" @submit.prevent="submitMessage">
                <label for="body" class="signal-label">Message</label>
                <textarea
                    id="body"
                    v-model="form.body"
                    class="signal-input"
                    rows="4"
                    placeholder="Send a message, ask questions, or confirm next steps..."
                />
                <p v-if="form.errors.body" class="signal-error">{{ form.errors.body }}</p>
                <button class="signal-button" :disabled="form.processing">Send Message</button>
            </form>
        </section>
    </SignalLayout>
</template>
