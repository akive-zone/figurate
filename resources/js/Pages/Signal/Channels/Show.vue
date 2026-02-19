<script setup>
import SignalLayout from '../../../Layouts/SignalLayout.vue';
import FloatingChatWindow from './FloatingChatWindow.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import DOMPurify from 'dompurify';
import { marked } from 'marked';
import { fetchSignalChannelPosts, fetchSignalThreadMessages, sendSignalChatMessage } from '../../../api/signalChat';

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

const promptForm = reactive({
    content: '',
    clientMessageId: '',
    draftForClientId: '',
});

const promptErrors = ref({});
const promptErrorMessage = ref('');
const isPrompting = ref(false);
const channelPosts = ref([]);
const isLoadingChannelPosts = ref(false);
const channelPostsError = ref('');
const threadMessages = ref([]);
const isLoadingThreadMessages = ref(false);
const threadLoadError = ref('');
const agentStatusMessage = ref('');
const subscribedThreadId = ref('');
const isFloatingChatOpen = ref(true);

const makeClientMessageId = () => {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `msg_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
};

const ensureClientMessageId = (content) => {
    const normalizedContent = (content ?? '').toString().trim();

    if (normalizedContent === '') {
        return '';
    }

    if (promptForm.clientMessageId === '' || promptForm.draftForClientId !== normalizedContent) {
        promptForm.clientMessageId = makeClientMessageId();
        promptForm.draftForClientId = normalizedContent;
    }

    return promptForm.clientMessageId;
};

const submitPrompt = async () => {
    promptErrors.value = {};
    promptErrorMessage.value = '';
    isPrompting.value = true;
    const clientMessageId = ensureClientMessageId(promptForm.content);

    try {
        await sendSignalChatMessage({
            channel: activeChannel.value.id,
            thread: activeChannel.value.active_thread ?? null,
            content: promptForm.content,
        }, runtime.value, {
            idempotencyKey: clientMessageId,
        });
        agentStatusMessage.value = 'Agent is thinking...';
        promptForm.content = '';
        promptForm.clientMessageId = '';
        promptForm.draftForClientId = '';
        await loadThreadMessages();
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

const formatTimestamp = (value) => new Date(value).toLocaleString();
const activeThreadId = computed(() => (activeChannel.value?.active_thread ?? '').toString().trim());

const visibleItems = computed(() => {
    if (!activeChannel.value) {
        return [];
    }

    if (activeThreadId.value === '') {
        return channelPosts.value;
    }

    return threadMessages.value;
});

const floatingChatMessages = computed(() => {
    return visibleItems.value.slice(-10);
});

const floatingChatTitle = computed(() => {
    if (!activeChannel.value) {
        return 'Signal';
    }

    return `Channel ${activeChannel.value.id}`;
});

const floatingChatSubtitle = computed(() => {
    if (!activeChannel.value) {
        return 'Offline';
    }

    return activeThreadId.value !== '' ? 'Thread active' : 'Channel active';
});

const loadChannelPosts = async () => {
    if (!activeChannel.value || activeThreadId.value !== '') {
        channelPostsError.value = '';
        return;
    }

    channelPosts.value = Array.isArray(activeChannel.value.channel_feed) ? activeChannel.value.channel_feed : [];
    isLoadingChannelPosts.value = true;
    channelPostsError.value = '';

    try {
        const payload = await fetchSignalChannelPosts(activeChannel.value.id, runtime.value);
        channelPosts.value = Array.isArray(payload?.data) ? payload.data : [];
    } catch (error) {
        channelPostsError.value = error.response?.data?.message ?? `Unable to load channel posts (${error.response?.status ?? 'network'}).`;
    } finally {
        isLoadingChannelPosts.value = false;
    }
};

const loadThreadMessages = async () => {
    if (!activeChannel.value || activeThreadId.value === '') {
        threadMessages.value = [];
        threadLoadError.value = '';
        return;
    }

    isLoadingThreadMessages.value = true;
    threadLoadError.value = '';

    try {
        const payload = await fetchSignalThreadMessages(activeThreadId.value, runtime.value);
        threadMessages.value = Array.isArray(payload?.data) ? payload.data : [];
    } catch (error) {
        threadLoadError.value = error.response?.data?.message ?? `Unable to load thread messages (${error.response?.status ?? 'network'}).`;
        threadMessages.value = [];
    } finally {
        isLoadingThreadMessages.value = false;
    }
};

const leaveThreadEvents = () => {
    if (subscribedThreadId.value === '') {
        return;
    }

    if (window.Echo && typeof window.Echo.leave === 'function') {
        window.Echo.leave(`threads.${subscribedThreadId.value}`);
    }

    subscribedThreadId.value = '';
};

const subscribeToThreadEvents = (threadId) => {
    if (!threadId || !window.Echo || typeof window.Echo.private !== 'function') {
        return;
    }

    leaveThreadEvents();

    subscribedThreadId.value = threadId;

    window.Echo.private(`threads.${threadId}`)
        .listen('.agent.reply.started', () => {
            agentStatusMessage.value = 'Agent is thinking...';
        })
        .listen('.agent.reply.completed', (event) => {
            agentStatusMessage.value = '';

            const assistantMessage = event?.assistant_message ?? null;

            if (assistantMessage && typeof assistantMessage.id === 'number') {
                const existingIndex = threadMessages.value.findIndex((message) => message.id === assistantMessage.id);

                if (existingIndex === -1) {
                    threadMessages.value.push(assistantMessage);
                } else {
                    threadMessages.value.splice(existingIndex, 1, assistantMessage);
                }
            } else {
                loadThreadMessages();
            }

            router.reload({ only: ['channel'] });
        })
        .listen('.agent.reply.failed', (event) => {
            agentStatusMessage.value = '';
            promptErrorMessage.value = event?.message ?? 'AI response failed. Please retry.';
        });
};

watch(
    () => [activeChannel.value?.id ?? null, activeThreadId.value],
    () => {
        loadChannelPosts();
        loadThreadMessages();

        if (activeThreadId.value !== '') {
            subscribeToThreadEvents(activeThreadId.value);
        } else {
            leaveThreadEvents();
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    leaveThreadEvents();
});

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

const submitFloatingPrompt = async (content) => {
    promptForm.content = content;
    await submitPrompt();
};
</script>

<template>
    <Head :title="activeChannel ? `Chat #${activeChannel.id}` : 'Signal Chat'" />

    <SignalLayout
        :channels="props.channels"
        :active-channel-id="activeChannel?.id ?? null"
        :active-thread-id="activeChannel?.active_thread ?? null"
    >
        <section class="signal-thread" v-if="activeChannel">
            <header class="signal-thread__header">
                <div>
                    <p class="signal-thread__kicker">Channel</p>
                    <h2 class="signal-thread__title">Channel {{ activeChannel.id }}</h2>
                    <p class="signal-thread__meta">Conversation view with channel updates and thread messages.</p>
                </div>
            </header>

            <section class="signal-thread__messages">
                <article
                    v-for="message in visibleItems"
                    :key="`${message.kind ?? 'message'}-${message.scope ?? 'channel'}-${message.id}`"
                    class="signal-message"
                >
                    <p class="signal-message__author">
                        {{ message.scope === 'thread' ? 'Thread' : message.scope === 'channel' ? 'Channel' : 'Conversation' }}
                    </p>
                    <div class="signal-message__content" v-html="renderMessageContent(message.content)" />
                    <ul v-if="message.attachments?.length" class="signal-thread__attachments">
                        <li v-for="attachment in message.attachments" :key="attachment.path">
                            {{ attachment.name }} ({{ attachment.mime }})
                        </li>
                    </ul>
                    <p class="signal-message__time">{{ formatTimestamp(message.created_at) }}</p>
                </article>

                <article v-if="!visibleItems.length" class="signal-empty">
                    <h3>No posts yet</h3>
                    <p v-if="activeChannel.active_thread && threadLoadError" class="signal-error">{{ threadLoadError }}</p>
                    <p v-else-if="!activeChannel.active_thread && channelPostsError" class="signal-error">{{ channelPostsError }}</p>
                    <p v-else-if="isLoadingThreadMessages && activeChannel.active_thread">Loading thread messages...</p>
                    <p v-else-if="isLoadingChannelPosts && !activeChannel.active_thread">Loading channel posts...</p>
                    <p v-else-if="activeChannel.active_thread">No messages in this thread yet.</p>
                    <p v-else-if="!channelPostsError">No channel posts yet.</p>
                </article>
            </section>

            <form class="signal-form" @submit.prevent="submitPrompt">
                <label for="prompt" class="signal-label">Message</label>
                <textarea
                    id="prompt"
                    v-model="promptForm.content"
                    class="signal-input"
                    rows="4"
                    placeholder="Write a message..."
                />
                <p v-if="promptErrors.content" class="signal-error">{{ promptErrors.content[0] }}</p>
                <p v-if="promptErrorMessage" class="signal-error">{{ promptErrorMessage }}</p>
                <p v-if="agentStatusMessage" class="signal-thread__meta">{{ agentStatusMessage }}</p>
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

        <FloatingChatWindow
            v-if="activeChannel"
            v-model="isFloatingChatOpen"
            :title="floatingChatTitle"
            :subtitle="floatingChatSubtitle"
            :messages="floatingChatMessages"
            :sending="isPrompting"
            @send="submitFloatingPrompt"
        />
    </SignalLayout>
</template>
