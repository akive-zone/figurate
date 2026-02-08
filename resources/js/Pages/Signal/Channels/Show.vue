<script setup>
import SignalLayout from '../../../Layouts/SignalLayout.vue';
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const props = defineProps({
    channel: {
        type: Object,
        required: true,
    },
});

const promptForm = reactive({
    content: '',
});

const promptErrors = ref({});
const isPrompting = ref(false);
const isAcceptingQuoteId = ref(null);

const submitPrompt = async () => {
    promptErrors.value = {};
    isPrompting.value = true;

    try {
        await axios.post(`/api/chat/${props.channel.id}`, {
            thread_id: props.channel.active_thread_id ?? null,
            content: promptForm.content,
        });
        promptForm.content = '';
        router.reload({ only: ['channel'] });
    } catch (error) {
        if (error.response?.status === 422) {
            promptErrors.value = error.response.data.errors ?? {};
        }
    } finally {
        isPrompting.value = false;
    }
};

const switchThread = (threadId) => {
    router.get(`/signal/chat/${props.channel.id}`, { thread: threadId }, { preserveState: true });
};

const acceptQuote = async (quoteId) => {
    isAcceptingQuoteId.value = quoteId;

    try {
        await axios.post(`/api/order/channels/${props.channel.id}/quotes/${quoteId}/accept`);
        router.reload({ only: ['channel'] });
    } finally {
        isAcceptingQuoteId.value = null;
    }
};

const formatTimestamp = (value) => new Date(value).toLocaleString();
</script>

<template>
    <Head :title="`Chat #${channel.id}`" />

    <SignalLayout>
        <section class="signal-thread">
            <header class="signal-thread__header">
                <div>
                    <p class="signal-thread__kicker">Channel</p>
                    <h2 class="signal-thread__title">{{ channel.request?.title ?? 'Untitled Request' }}</h2>
                    <p class="signal-thread__meta">One chatbox, multiple agent threads in the same request context.</p>
                </div>
                <Link href="/signal" class="signal-link">Back</Link>
            </header>

            <section class="signal-thread__request" v-if="channel.request">
                <h3>Request Summary</h3>
                <p>{{ channel.request.description }}</p>
                <p class="signal-card__status">Status: {{ channel.request.status }}</p>

                <div v-if="channel.request.quotes?.length" class="signal-quote-list">
                    <h4>Quotes</h4>
                    <article v-for="quote in channel.request.quotes" :key="quote.id" class="signal-quote">
                        <p class="signal-quote__amount">{{ quote.currency }} {{ Number(quote.amount).toFixed(2) }}</p>
                        <p class="signal-card__status">Status: {{ quote.status }}</p>
                        <p v-if="quote.details">{{ quote.details }}</p>
                        <button
                            v-if="channel.actions.can_accept_quote && quote.status === 'pending'"
                            type="button"
                            class="signal-button"
                            :disabled="isAcceptingQuoteId === quote.id"
                            @click="acceptQuote(quote.id)"
                        >
                            Accept Quote and Kickoff Order
                        </button>
                    </article>
                </div>
            </section>

            <section class="signal-threads">
                <div class="signal-threads__header">
                    <h3>Request Threads</h3>
                </div>
                <div class="signal-threads__list">
                    <button
                        v-for="thread in channel.threads"
                        :key="thread.id"
                        type="button"
                        class="signal-thread-chip"
                        :class="{ 'signal-thread-chip--active': thread.id === channel.active_thread_id }"
                        @click="switchThread(thread.id)"
                    >
                        <span>{{ thread.title }}</span>
                        <small>{{ thread.agent_key }}</small>
                    </button>
                </div>
            </section>

            <section class="signal-thread__messages">
                <article
                    v-for="message in channel.thread_messages"
                    :key="`thread-${message.id}`"
                    class="signal-message signal-message--mine"
                >
                    <p class="signal-message__author">{{ message.sender_name ?? 'You' }}</p>
                    <p>{{ message.content }}</p>
                    <ul v-if="message.attachments?.length" class="signal-thread__attachments">
                        <li v-for="attachment in message.attachments" :key="attachment.path">
                            {{ attachment.name }} ({{ attachment.mime }})
                        </li>
                    </ul>
                    <p class="signal-message__time">{{ formatTimestamp(message.created_at) }}</p>
                </article>

                <article
                    v-for="message in channel.agent_messages"
                    :key="message.id"
                    class="signal-message"
                    :class="{ 'signal-message--mine': message.role === 'user' }"
                >
                    <p class="signal-message__author">
                        {{ message.role === 'assistant' ? message.agent : (message.sender_name ?? 'You') }}
                    </p>
                    <p>{{ message.content }}</p>
                    <p class="signal-message__time">{{ formatTimestamp(message.created_at) }}</p>
                </article>

                <article v-if="!channel.agent_messages.length" class="signal-empty">
                    <h3>No agent messages yet</h3>
                    <p>Send a message in the active thread to start the RequestAgent/OrderAgent exchange.</p>
                </article>
            </section>

            <form class="signal-form" @submit.prevent="submitPrompt">
                <label for="prompt" class="signal-label">Message To Active Agent</label>
                <textarea
                    id="prompt"
                    v-model="promptForm.content"
                    class="signal-input"
                    rows="4"
                    placeholder="Write a message for the active thread..."
                />
                <p v-if="promptErrors.content" class="signal-error">{{ promptErrors.content[0] }}</p>
                <button class="signal-button" :disabled="isPrompting">
                    Send
                </button>
            </form>
        </section>
    </SignalLayout>
</template>
