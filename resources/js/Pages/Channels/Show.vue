<script setup>
import ChatLayout from '../../Layouts/ChatLayout.vue';
import AgentWorkspacePanel from './AgentWorkspacePanel.vue';
import ChannelTimelinePanel from './ChannelTimelinePanel.vue';
import HumanChatPanel from './HumanChatPanel.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { fetchChatChannelPosts, fetchChatThreadMessages, sendChatChatMessage } from '../../api';

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
const chatRoutes = computed(() => runtime.value.routes ?? {});
const chatIndexUrl = computed(() => {
    const configured = (chatRoutes.value.index ?? '').toString().trim();
    if (configured !== '') {
        return configured;
    }

    const fallback = (runtime.value.index_path ?? '').toString().trim();

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
const isSlidingChatOpen = ref(false);
const isEmbedPanelOpen = ref(false);
const embedPanelRef = ref(null);
const embedPanelSource = ref('');
const embedPanelPreviousPath = ref('');
const embedPanelKicker = ref('Panel');
const embedPanelTitle = ref('');
const embedPanelSuccessFromPath = ref('');
const embedPanelSuccessPathPrefix = ref('');
const embedPanelReloadOnSuccess = ref(false);

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
        await sendChatChatMessage({
            channel: activeChannel.value.id,
            thread: activeChannel.value.active_thread ?? null,
            body: promptForm.content,
            attachments: [],
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

const activeThreadId = computed(() => (activeChannel.value?.active_thread ?? '').toString().trim());

const messageSource = (message) => (message?.source ?? '').toString().trim();
const isAgentConversationMessage = (message) => {
    const source = messageSource(message);

    return source === 'agent_prompt' || source === 'agent_response';
};
const isHumanConversationMessage = (message) => {
    return messageSource(message) === 'peer_message';
};

const channelFeedItems = computed(() => {
    if (!activeChannel.value) {
        return [];
    }

    return channelPosts.value;
});

const floatingChatMessages = computed(() => {
    const messages = Array.isArray(threadMessages.value) ? threadMessages.value : [];

    return messages
        .filter((message) => isHumanConversationMessage(message))
        .slice(-10);
});

const slidingChatMessages = computed(() => {
    const messages = Array.isArray(threadMessages.value) ? threadMessages.value : [];

    return messages
        .filter((message) => isAgentConversationMessage(message))
        .slice(-25);
});

const loadChannelPosts = async () => {
    if (!activeChannel.value) {
        channelPostsError.value = '';
        return;
    }

    channelPosts.value = Array.isArray(activeChannel.value.channel_feed) ? activeChannel.value.channel_feed : [];
    isLoadingChannelPosts.value = true;
    channelPostsError.value = '';

    try {
        const payload = await fetchChatChannelPosts(activeChannel.value.id, runtime.value);
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
        const payload = await fetchChatThreadMessages(activeThreadId.value, runtime.value);
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
            loadThreadMessages();
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

const submitFloatingPrompt = async (content) => {
    promptForm.content = content;
    await submitPrompt();
};

const submitSlidingPrompt = async (content) => {
    promptForm.content = content;
    await submitPrompt();
};

const buildContextServerCreateUrl = () => {
    const channelId = (activeChannel.value?.id ?? '').toString().trim();

    if (channelId === '') {
        return '/p/context-servers/create';
    }

    const params = new URLSearchParams({
        context_type: 'channel',
        context_id: channelId,
    });

    return `/p/context-servers/create?${params.toString()}`;
};

const openEmbedPanel = ({
    source,
    kicker = 'Panel',
    title = 'Open panel',
    successFromPath = '',
    successPathPrefix = '',
    reloadOnSuccess = false,
}) => {
    embedPanelPreviousPath.value = '';
    embedPanelSource.value = source;
    embedPanelKicker.value = kicker;
    embedPanelTitle.value = title;
    embedPanelSuccessFromPath.value = successFromPath;
    embedPanelSuccessPathPrefix.value = successPathPrefix;
    embedPanelReloadOnSuccess.value = reloadOnSuccess;
    isEmbedPanelOpen.value = true;
};

const closeEmbedPanel = () => {
    isEmbedPanelOpen.value = false;
};

const handleEmbedPanelLoad = () => {
    try {
        const path = (embedPanelRef.value?.contentWindow?.location?.pathname ?? '').toString();
        const completed =
            embedPanelPreviousPath.value.includes(embedPanelSuccessFromPath.value)
            && path.includes(embedPanelSuccessPathPrefix.value)
            && !path.includes(embedPanelSuccessFromPath.value);

        if (completed) {
            isEmbedPanelOpen.value = false;

            if (embedPanelReloadOnSuccess.value) {
                router.reload({ only: ['channel'] });
            }
        }

        embedPanelPreviousPath.value = path;
    } catch {
        // Ignore frame access exceptions when location cannot be read.
    }
};

const openContextServerPanel = () => {
    openEmbedPanel({
        source: buildContextServerCreateUrl(),
        kicker: 'Context Server',
        title: 'Manage MCP for this channel',
        successFromPath: '/p/context-servers/create',
        successPathPrefix: '/p/context-servers',
        reloadOnSuccess: true,
    });
};
</script>

<template>
    <Head :title="activeChannel ? `Chat #${activeChannel.id}` : 'Chat Chat'" />

    <ChatLayout
        :channels="props.channels"
        :active-channel-id="activeChannel?.id ?? null"
        :active-thread-id="activeChannel?.active_thread ?? null"
    >
        <section v-if="activeChannel">
            <ChannelTimelinePanel
                :channel-id="activeChannel.id"
                :messages="channelFeedItems"
                :is-loading="isLoadingChannelPosts"
                :error-message="channelPostsError"
                @manage-mcp="openContextServerPanel"
            />
            <p v-if="activeChannel.active_thread && threadLoadError" class="error">{{ threadLoadError }}</p>
            <p v-if="promptErrors.body" class="error">{{ promptErrors.body[0] }}</p>
            <p v-if="promptErrorMessage" class="error">{{ promptErrorMessage }}</p>
            <p v-if="agentStatusMessage" class="thread__meta">{{ agentStatusMessage }}</p>
        </section>

        <section v-else class="empty">
            <h3>Unable to load channel</h3>
            <p>Check your API connection and try again.</p>
            <Link :href="chatIndexUrl" class="link">Back</Link>
        </section>

        <HumanChatPanel
            v-if="activeChannel"
            v-model="isFloatingChatOpen"
            :active-thread-id="activeThreadId"
            :messages="floatingChatMessages"
            :sending="isPrompting"
            @send="submitFloatingPrompt"
        />

        <AgentWorkspacePanel
            v-if="activeChannel"
            v-model="isSlidingChatOpen"
            :messages="slidingChatMessages"
            :sending="isPrompting"
            @send="submitSlidingPrompt"
        />

        <teleport to="body">
            <div v-if="isEmbedPanelOpen" class="embed-panel-overlay" @click.self="closeEmbedPanel">
                <aside class="embed-panel">
                    <header class="embed-panel__header">
                        <div>
                            <p class="thread__kicker">{{ embedPanelKicker }}</p>
                            <h3 class="embed-panel__title">{{ embedPanelTitle }}</h3>
                        </div>
                        <button type="button" class="user-chip" @click="closeEmbedPanel">Close</button>
                    </header>

                    <iframe
                        ref="embedPanelRef"
                        :src="embedPanelSource"
                        class="embed-panel__frame"
                        title="Embedded manager"
                        @load="handleEmbedPanelLoad"
                    />
                </aside>
            </div>
        </teleport>
    </ChatLayout>
</template>
