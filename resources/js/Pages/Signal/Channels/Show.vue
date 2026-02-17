<script setup>
import SignalLayout from '../../../Layouts/SignalLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { sendSignalChatMessage } from '../../../api/signalChat';

const props = defineProps({
    channels: {
        type: Array,
        default: () => [],
    },
    channel: {
        type: Object,
        default: null,
    },
});

const activeChannel = computed(() => props.channel);
const page = usePage();
const runtime = computed(() => page.props.runtime ?? {});
const signalRoutes = computed(() => runtime.value.signal_routes ?? {});
const signalIndexUrl = computed(() => {
    const configured = (signalRoutes.value.index ?? '').toString().trim();
    if (configured !== '') {
        return configured;
    }

    const fallback = (runtime.value.signal_index_path ?? '').toString().trim();

    return fallback !== '' ? fallback : '/';
});
const signalShowTemplate = computed(() => {
    const configured = (signalRoutes.value.show_template ?? '').toString().trim();
    if (configured !== '') {
        return configured;
    }

    return '/channels/__CHANNEL__';
});
const signalChannelUrl = (channelId) => signalShowTemplate.value.replace('__CHANNEL__', channelId);

const promptForm = reactive({
    content: '',
});

const promptErrors = ref({});
const promptErrorMessage = ref('');
const isPrompting = ref(false);

const submitPrompt = async () => {
    promptErrors.value = {};
    promptErrorMessage.value = '';
    isPrompting.value = true;

    try {
        await sendSignalChatMessage({
            channel: activeChannel.value.id,
            thread: activeChannel.value.active_thread ?? null,
            content: promptForm.content,
        }, runtime.value);
        promptForm.content = '';
        router.reload({ only: ['channel'] });
    } catch (error) {
        if (error.response?.status === 422) {
            promptErrors.value = error.response.data.errors ?? {};
        } else {
            promptErrorMessage.value = error.response?.data?.message ?? `Message failed (${error.response?.status ?? 'network'}).`;
        }
    } finally {
        isPrompting.value = false;
    }
};

const switchThread = (threadId) => {
    router.get(signalChannelUrl(activeChannel.value.id), { thread: threadId }, { preserveState: true });
};

const formatTimestamp = (value) => new Date(value).toLocaleString();
</script>

<template>
    <Head :title="activeChannel ? `Chat #${activeChannel.id}` : 'Signal Chat'" />

    <SignalLayout :channels="props.channels" :active-channel-id="activeChannel?.id ?? null">
        <section class="signal-thread" v-if="activeChannel">
            <header class="signal-thread__header">
                <div>
                    <p class="signal-thread__kicker">Channel</p>
                    <h2 class="signal-thread__title">{{ activeChannel.request?.title ?? 'Untitled Channel' }}</h2>
                    <p class="signal-thread__meta">Open channel, one chatbox, and thread orchestration behind the scenes.</p>
                </div>
            </header>

            <section class="signal-thread__request" v-if="activeChannel.request">
                <h3>Channel Context</h3>
                <p>{{ activeChannel.request.description }}</p>
                <p class="signal-card__status">Status: {{ activeChannel.request.status }}</p>

                <div v-if="activeChannel.request.quotes?.length" class="signal-quote-list">
                    <h4>Quotes</h4>
                    <article v-for="quote in activeChannel.request.quotes" :key="quote.id" class="signal-quote">
                        <p class="signal-quote__amount">{{ quote.currency }} {{ Number(quote.amount).toFixed(2) }}</p>
                        <p class="signal-card__status">Status: {{ quote.status }}</p>
                        <p v-if="quote.details">{{ quote.details }}</p>
                    </article>
                    <p class="signal-thread__meta">To accept a quote or change thread state, instruct the agent in chat.</p>
                </div>
            </section>

            <section class="signal-threads">
                <div class="signal-threads__header">
                    <h3>Request Threads</h3>
                </div>
                <div class="signal-threads__list">
                    <button
                        v-for="thread in activeChannel.threads"
                        :key="thread.id"
                        type="button"
                        class="signal-thread-chip"
                        :class="{ 'signal-thread-chip--active': thread.id === activeChannel.active_thread }"
                        @click="switchThread(thread.id)"
                    >
                        <span>{{ thread.title }}</span>
                        <small>{{ thread.handler_actor }}</small>
                    </button>
                </div>
            </section>

            <section class="signal-thread__messages">
                <article
                    v-for="message in activeChannel.thread_messages"
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
                    v-for="message in activeChannel.agent_messages"
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

                <article v-if="!activeChannel.agent_messages.length" class="signal-empty">
                    <h3>No agent messages yet</h3>
                    <p>Send a message and the orchestration flow will continue in this channel.</p>
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
                <p v-if="promptErrorMessage" class="signal-error">{{ promptErrorMessage }}</p>
                <button class="signal-button" :disabled="isPrompting">
                    Send
                </button>
            </form>
        </section>

        <section v-else class="signal-empty">
            <h3>Unable to load channel</h3>
            <p>Check your API connection and try again.</p>
            <Link :href="signalIndexUrl" class="signal-link">Back</Link>
        </section>
    </SignalLayout>
</template>
