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
    message: '',
});

const threadForm = reactive({
    title: '',
    phase: 'general',
    agent_key: 'request_agent',
});

const promptErrors = ref({});
const threadErrors = ref({});
const isPrompting = ref(false);
const isCreatingThread = ref(false);
const isAcceptingQuoteId = ref(null);

const submitPrompt = async () => {
    if (!props.channel.active_thread_id) {
        return;
    }

    promptErrors.value = {};
    isPrompting.value = true;

    try {
        await axios.post(
            `/api/chat/${props.channel.id}/threads/${props.channel.active_thread_id}/prompt`,
            promptForm,
        );
        promptForm.message = '';
        router.reload({ only: ['channel'] });
    } catch (error) {
        if (error.response?.status === 422) {
            promptErrors.value = error.response.data.errors ?? {};
        }
    } finally {
        isPrompting.value = false;
    }
};

const submitThread = async () => {
    threadErrors.value = {};
    isCreatingThread.value = true;

    try {
        const response = await axios.post(`/api/chat/${props.channel.id}/threads`, threadForm);
        const threadId = response.data?.thread_id;
        threadForm.title = '';

        if (threadId) {
            switchThread(threadId);
            return;
        }

        router.reload({ only: ['channel'] });
    } catch (error) {
        if (error.response?.status === 422) {
            threadErrors.value = error.response.data.errors ?? {};
        }
    } finally {
        isCreatingThread.value = false;
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

                <form v-if="channel.actions.can_create_thread" class="signal-form" @submit.prevent="submitThread">
                    <h4>Start New Thread</h4>
                    <label for="thread-title" class="signal-label">Title</label>
                    <input id="thread-title" v-model="threadForm.title" class="signal-input" maxlength="120" />
                    <p v-if="threadErrors.title" class="signal-error">{{ threadErrors.title[0] }}</p>

                    <label for="thread-phase" class="signal-label">Phase</label>
                    <input id="thread-phase" v-model="threadForm.phase" class="signal-input" maxlength="60" />
                    <p v-if="threadErrors.phase" class="signal-error">{{ threadErrors.phase[0] }}</p>

                    <label for="thread-agent" class="signal-label">Agent</label>
                    <select id="thread-agent" v-model="threadForm.agent_key" class="signal-input">
                        <option value="request_agent">RequestAgent</option>
                        <option value="order_agent">OrderAgent</option>
                    </select>
                    <p v-if="threadErrors.agent_key" class="signal-error">{{ threadErrors.agent_key[0] }}</p>

                    <button class="signal-button" :disabled="isCreatingThread">Create Thread</button>
                </form>
            </section>

            <section class="signal-thread__messages">
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
                    v-model="promptForm.message"
                    class="signal-input"
                    rows="4"
                    placeholder="Ask for guidance in this thread..."
                />
                <p v-if="promptErrors.message" class="signal-error">{{ promptErrors.message[0] }}</p>
                <button class="signal-button" :disabled="isPrompting || !channel.actions.can_prompt_agent">
                    Send To Agent
                </button>
            </form>
        </section>
    </SignalLayout>
</template>
