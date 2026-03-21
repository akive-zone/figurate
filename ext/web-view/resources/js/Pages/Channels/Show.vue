<script setup>
import ChatLayout from '../../Layouts/ChatLayout.vue';
import AgentWorkspacePanel from './AgentWorkspacePanel.vue';
import ChannelTimelinePanel from './ChannelTimelinePanel.vue';
import HumanChatPanel from './HumanChatPanel.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { chatDataService } from '../../services/chatDataService';
import { inertiaNavigationService } from '../../services/inertiaNavigationService';
import { useThreadEcho } from '../../composables/useThreadEcho';
import { buildA2uiActionRequest } from '@web-view/a2ui';

const props = defineProps({
    channels: {
        type: Array,
        default: () => [],
    },
    channelId: {
        type: String,
        default: '',
    },
    threadId: {
        type: String,
        default: null,
    },
    channel: {
        type: Object,
        default: null,
    },
});

const activeChannel = ref(null);
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
const workspaceMessages = ref({});
const workspaceTurns = ref({});
const isLoadingThreads = ref({});
const threadLoadError = ref('');
const agentStatusMessage = ref('');
const latestSubmittedPromptMessageId = ref(null);
const isFloatingChatOpen = ref(false);
const isSlidingChatOpen = ref(false);
const openAgentThreadIds = ref(new Set());
const activeAgentThreadId = ref(null);
const isEmbedPanelOpen = ref(false);
const embedPanelRef = ref(null);
const embedPanelSource = ref('');
const embedPanelPreviousPath = ref('');
const embedPanelKicker = ref('Panel');
const embedPanelTitle = ref('');
const embedPanelSuccessFromPath = ref('');
const embedPanelSuccessPathPrefix = ref('');
const embedPanelReloadOnSuccess = ref(false);

const deriveSuggestedOpenThreads = (threads = []) => {
    const list = Array.isArray(threads) ? threads : [];
    const latestAgentThread = list.find((thread) => ['agent', 'mixed'].includes((thread?.nature ?? '').toString().toLowerCase()));
    const latestHumanThread = list.find((thread) => ['human', 'mixed'].includes((thread?.nature ?? '').toString().toLowerCase()));
    const suggested = [latestAgentThread?.id, latestHumanThread?.id].filter((id, index, all) => id && all.indexOf(id) === index);

    if (suggested.length === 0 && list.length > 0) {
        suggested.push(list[0].id);
    }

    return suggested;
};

const loadActiveChannel = async () => {
    const channelId = (props.channelId ?? props.channel?.id ?? '').toString().trim();

    if (channelId === '') {
        activeChannel.value = null;
        return;
    }

    try {
        const chatsPayload = await chatDataService.listChats(runtime.value);
        const chats = Array.isArray(chatsPayload?.data) ? chatsPayload.data : [];
        const matched = chats.find((chat) => (chat?.id ?? '').toString() === channelId);

        if (!matched) {
            activeChannel.value = null;
            return;
        }

        const threadsPayload = await chatDataService.listChatThreads(channelId, runtime.value);
        const threads = Array.isArray(threadsPayload?.data) ? threadsPayload.data : (Array.isArray(matched?.threads) ? matched.threads : []);
        const requestedThreadId = (props.threadId ?? '').toString().trim();
        const defaultThreadId = (matched?.channel?.active_thread_id ?? '').toString().trim();
        const resolvedThreadId = requestedThreadId !== ''
            ? requestedThreadId
            : (defaultThreadId !== '' ? defaultThreadId : ((threads[0]?.id ?? '').toString().trim() || null));

        activeChannel.value = {
            id: channelId,
            status: (matched?.channel?.status ?? 'open').toString(),
            threads,
            active_thread: resolvedThreadId,
            suggested_open_threads: deriveSuggestedOpenThreads(threads),
            channel_feed: [],
            thread_messages: [],
        };
    } catch {
        activeChannel.value = null;
    }
};

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

const submitPrompt = async (targetThreadId = null) => {
    promptErrors.value = {};
    promptErrorMessage.value = '';
    isPrompting.value = true;
    const clientMessageId = ensureClientMessageId(promptForm.content);
    const threadId = targetThreadId || activeThreadId.value;

    try {
        const response = await chatDataService.sendMessage({
            channel: activeChannel.value.id,
            thread: threadId ?? null,
            content: {
                text: promptForm.content,
                attachments: [],
            },
        }, runtime.value, {
            idempotencyKey: clientMessageId,
        });
        const submittedMessageId = Number(response?.data?.message_id ?? 0);
        if (Number.isFinite(submittedMessageId) && submittedMessageId > 0) {
            latestSubmittedPromptMessageId.value = submittedMessageId;
            await loadTurnsForMessage(submittedMessageId, threadId);
        }
        agentStatusMessage.value = 'Agent is thinking...';
        promptForm.content = '';
        promptForm.clientMessageId = '';
        promptForm.draftForClientId = '';
        if (threadId) {
            await loadThreadMessages(threadId);
        }
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
const suggestedOpenThreads = computed(() => Array.isArray(activeChannel.value?.suggested_open_threads) ? activeChannel.value.suggested_open_threads : []);
const allThreads = computed(() => Array.isArray(activeChannel.value?.threads) ? activeChannel.value.threads : []);

const openThreadTabs = computed(() => {
    const uniqueIds = new Set([activeThreadId.value, ...suggestedOpenThreads.value, ...Array.from(openAgentThreadIds.value)].filter(Boolean));

    return allThreads.value.filter(t => uniqueIds.has(t.id));
});

const dockedAgentThreads = computed(() => {
    return allThreads.value.filter(t => openAgentThreadIds.value.has(t.id));
});

const latestAgentThread = computed(() => {
    if (activeAgentThreadId.value && openAgentThreadIds.value.has(activeAgentThreadId.value)) {
        return allThreads.value.find(t => t.id === activeAgentThreadId.value);
    }
    const active = allThreads.value.find(t => t.id === activeThreadId.value && (t.nature === 'agent' || t.nature === 'mixed'));
    if (active) return active;
    return allThreads.value.find(t => t.nature === 'agent' || t.nature === 'mixed');
});

const latestHumanThread = computed(() => {
    const active = allThreads.value.find(t => t.id === activeThreadId.value && t.nature === 'human');
    if (active) return active;
    return allThreads.value.find(t => t.nature === 'human');
});

const getThreadMessages = (threadId) => workspaceMessages.value[threadId] ?? [];
const contentText = (message) => {
    if (typeof message?.content === 'string') {
        return message.content;
    }

    return (message?.content?.text ?? '').toString();
};

const normalizeThreadMessage = (message) => {
    const nestedContent = message?.content && typeof message.content === 'object' ? message.content : null;
    const nestedAttachments = Array.isArray(nestedContent?.attachments) ? nestedContent.attachments : null;
    const nestedExtra = message?.extra && typeof message.extra === 'object' ? message.extra : null;
    const topLevelAttachments = Array.isArray(message?.attachments) ? message.attachments : [];

    return {
        ...message,
        content: contentText(message),
        attachments: nestedAttachments ?? topLevelAttachments,
        extra: nestedExtra ?? null,
    };
};

const messageSource = (message) => (message?.source ?? '').toString().trim();
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
    const threadId = latestHumanThread.value?.id;
    if (!threadId) return [];

    const messages = getThreadMessages(threadId);

    return messages
        .filter((message) => isHumanConversationMessage(message))
        .slice(-10);
});

const shouldShowHumanChatPanel = computed(() => {
    return latestHumanThread.value !== undefined;
});

const slidingChatMessages = computed(() => {
    const threadId = latestAgentThread.value?.id;
    if (!threadId) return [];
    const prompts = getThreadMessages(threadId)
        .filter((message) => messageSource(message) === 'agent_prompt')
        .slice(-25);
    const turns = Array.isArray(workspaceTurns.value[threadId]) ? workspaceTurns.value[threadId] : [];
    const turnsByPrompt = turns.reduce((carry, turn) => {
        const promptId = Number(turn?.prompt_message_id ?? 0);
        if (!Number.isFinite(promptId) || promptId <= 0) {
            return carry;
        }

        if (!Array.isArray(carry[promptId])) {
            carry[promptId] = [];
        }

        carry[promptId].push({
            id: turn.id,
            status: (turn.status ?? '').toString(),
            actor_key: turn.actor_key ?? null,
            invocation_id: turn.invocation_id ?? null,
            prompt_text: '',
            assistant_text: (turn.assistant_text ?? '').toString(),
            assistant_content: turn.assistant_content ?? null,
            assistant_extra: turn.assistant_extra ?? null,
            error_message: (turn?.telemetry?.meta?.error_message ?? '').toString(),
            usage: turn.usage ?? {},
            completed_at: turn.completed_at ?? null,
        });

        return carry;
    }, {});

    return prompts.map((prompt) => {
        const promptText = contentText(prompt);
        const scopedTurns = Array.isArray(turnsByPrompt[prompt.id]) ? turnsByPrompt[prompt.id] : [];

        return {
            kind: 'turn_prompt',
            id: prompt.id,
            content: promptText,
            created_at: prompt.created_at ?? null,
            turns: scopedTurns.map((turn) => ({
                ...turn,
                prompt_text: promptText,
            })),
        };
    });
});

const loadChannelPosts = async () => {
    if (!activeChannel.value) {
        channelPostsError.value = '';
        return;
    }

    channelPosts.value = Array.isArray(activeChannel.value.channel_feed)
        ? activeChannel.value.channel_feed.map((item) => (item?.kind === 'message' ? normalizeThreadMessage(item) : item))
        : [];
    isLoadingChannelPosts.value = true;
    channelPostsError.value = '';

    try {
        const payload = await chatDataService.listChannelPosts(activeChannel.value.id, runtime.value);
        channelPosts.value = Array.isArray(payload?.data)
            ? payload.data.map((item) => (item?.kind === 'message' ? normalizeThreadMessage(item) : item))
            : [];
    } catch (error) {
        channelPostsError.value = error.response?.data?.message ?? `Unable to load channel posts (${error.response?.status ?? 'network'}).`;
    } finally {
        isLoadingChannelPosts.value = false;
    }
};

const loadThreadMessages = async (threadId) => {
    if (!activeChannel.value || !threadId) {
        return;
    }

    isLoadingThreads.value[threadId] = true;
    threadLoadError.value = '';

    try {
        const payload = await chatDataService.listThreadMessages(threadId, runtime.value);
        workspaceMessages.value[threadId] = Array.isArray(payload?.data)
            ? payload.data.map((item) => normalizeThreadMessage(item))
            : [];
        workspaceTurns.value[threadId] = Array.isArray(payload?.turns) ? payload.turns : [];
    } catch (error) {
        threadLoadError.value = error.response?.data?.message ?? `Unable to load thread messages (${error.response?.status ?? 'network'}).`;
    } finally {
        isLoadingThreads.value[threadId] = false;
    }
};

const loadWorkspace = async () => {
    const threadsToLoad = new Set([activeThreadId.value, ...suggestedOpenThreads.value, ...Array.from(openAgentThreadIds.value)].filter(Boolean));

    for (const threadId of Array.from(threadsToLoad)) {
        loadThreadMessages(threadId);
    }
};

const chatShowThreadTemplate = computed(() => {
    const configured = (chatRoutes.value.show_thread_template ?? '').toString().trim();
    if (configured !== '') {
        return configured;
    }

    return '/channels/__CHANNEL__/threads/__THREAD__';
});

const chatChannelThreadUrl = (channelId, threadId) => {
    return chatShowThreadTemplate.value
        .replace('__CHANNEL__', channelId)
        .replace('__THREAD__', threadId);
};

const handleOpenThread = ({ threadId, thread }) => {
    const nature = (thread?.nature ?? '').toString().toLowerCase();

    if (nature === 'agent' || nature === 'mixed') {
        openAgentThreadIds.value.add(threadId);
        activeAgentThreadId.value = threadId;
        isSlidingChatOpen.value = true;
    } else {
        isFloatingChatOpen.value = true;
    }

    if (threadId !== activeThreadId.value) {
        inertiaNavigationService.visitPreservingState(chatChannelThreadUrl(activeChannel.value.id, threadId));
    }

    activeChannel.value = {
        ...activeChannel.value,
        active_thread: threadId,
    };
    loadThreadMessages(threadId);
};

const handleCloseAgentThread = (threadId) => {
    openAgentThreadIds.value.delete(threadId);
    if (activeAgentThreadId.value === threadId) {
        activeAgentThreadId.value = Array.from(openAgentThreadIds.value).pop() || null;
    }
};

const handleSwitchAgentThread = (threadId) => {
    activeAgentThreadId.value = threadId;
    loadThreadMessages(threadId);
};

const handleCreateThread = async () => {
    const title = prompt('Enter a title for this new workstream:');
    if (!title) return;

    try {
        const response = await chatDataService.createThread(activeChannel.value.id, {
            title: title,
            purpose: 'execution',
            nature: 'human'
        }, runtime.value);

        if (response?.data) {
            await loadActiveChannel();
            handleOpenThread({ threadId: response.data.id, thread: response.data });
            inertiaNavigationService.visitPreservingState(chatChannelThreadUrl(activeChannel.value.id, response.data.id));
        }
    } catch (error) {
        alert('Failed to create workstream.');
    }
};

const mergeThreadTurns = (threadId, incomingTurns) => {
    const existing = Array.isArray(workspaceTurns.value[threadId]) ? workspaceTurns.value[threadId] : [];
    const incoming = Array.isArray(incomingTurns) ? incomingTurns : [];
    const byId = new Map();

    existing.forEach((turn) => {
        const key = (turn?.id ?? '').toString();
        if (key !== '') {
            byId.set(key, turn);
        }
    });

    incoming.forEach((turn) => {
        const key = (turn?.id ?? '').toString();
        if (key !== '') {
            byId.set(key, turn);
        }
    });

    workspaceTurns.value[threadId] = Array.from(byId.values()).sort((left, right) => {
        const leftPromptId = Number(left?.prompt_message_id ?? 0);
        const rightPromptId = Number(right?.prompt_message_id ?? 0);

        if (leftPromptId !== rightPromptId) {
            return leftPromptId - rightPromptId;
        }

        const leftActor = (left?.actor_key ?? '').toString();
        const rightActor = (right?.actor_key ?? '').toString();

        return leftActor.localeCompare(rightActor);
    });
};

const loadTurnsForMessage = async (messageId, threadId = null) => {
    const normalizedMessageId = Number(messageId ?? 0);
    const targetThreadId = (threadId ?? activeAgentThreadId.value ?? activeThreadId.value ?? '').toString();

    if (targetThreadId === '' || !Number.isFinite(normalizedMessageId) || normalizedMessageId <= 0) {
        return;
    }

    try {
        const payload = await chatDataService.listMessageTurns(targetThreadId, normalizedMessageId, runtime.value);
        mergeThreadTurns(targetThreadId, payload?.data ?? []);
    } catch {
        // Keep current turn state if scoped refresh fails.
    }
};

useThreadEcho({
    threadId: activeThreadId,
    enabled: computed(() => activeChannel.value !== null),
    onReplyStarted: () => {
        agentStatusMessage.value = 'Agent is thinking...';
    },
    onReplyCompleted: () => {
        agentStatusMessage.value = '';
        if (latestSubmittedPromptMessageId.value) {
            loadTurnsForMessage(latestSubmittedPromptMessageId.value, activeAgentThreadId.value);
        }
        loadWorkspace();
        loadActiveChannel();
    },
    onReplyFailed: (event) => {
        agentStatusMessage.value = '';
        promptErrorMessage.value = event?.message ?? 'AI response failed. Please retry.';
    },
    onStreamStart: () => {
        agentStatusMessage.value = 'Agent is thinking...';
    },
    onTextDelta: (_event, payload) => {
        const delta = (payload?.delta ?? '').toString().trim();

        if (delta !== '') {
            agentStatusMessage.value = 'Agent is typing...';
        }
    },
    onStreamEnd: () => {
        agentStatusMessage.value = '';
        if (latestSubmittedPromptMessageId.value) {
            loadTurnsForMessage(latestSubmittedPromptMessageId.value, activeAgentThreadId.value);
        }
        loadWorkspace();
        loadActiveChannel();
    },
    onError: (_event, payload) => {
        const message = (payload?.message ?? '').toString().trim();

        agentStatusMessage.value = '';
        promptErrorMessage.value = message !== '' ? message : 'AI response failed. Please retry.';
    },
});

watch(
    () => [props.channelId, props.channel?.id ?? null, props.threadId],
    () => {
        loadActiveChannel();
    },
    { immediate: true },
);

watch(
    () => activeChannel.value?.id ?? null,
    (newId, oldId) => {
        if (newId !== oldId) {
            workspaceMessages.value = {};
            workspaceTurns.value = {};
            isLoadingThreads.value = {};
        }
    }
);

watch(
    () => [activeChannel.value?.id ?? null, activeThreadId.value],
    () => {
        latestSubmittedPromptMessageId.value = null;
        loadChannelPosts();
        loadWorkspace();
    },
    { immediate: true },
);

const submitFloatingPrompt = async (content) => {
    promptForm.content = content;
    await submitPrompt(latestHumanThread.value?.id);
};

const submitSlidingPrompt = async (content) => {
    promptForm.content = content;
    await submitPrompt(latestAgentThread.value?.id);
};

const submitA2uiAction = async (actionPayload) => {
    if (!activeChannel.value) {
        return;
    }

    const threadId = latestAgentThread.value?.id ?? activeThreadId.value;
    if (!threadId) {
        return;
    }

    promptErrors.value = {};
    promptErrorMessage.value = '';
    isPrompting.value = true;

    try {
        const response = await chatDataService.sendMessage(buildA2uiActionRequest({
            channel: activeChannel.value.id,
            thread: threadId,
            action: actionPayload,
        }), runtime.value, {
            idempotencyKey: makeClientMessageId(),
        });
        const submittedMessageId = Number(response?.data?.message_id ?? 0);
        if (Number.isFinite(submittedMessageId) && submittedMessageId > 0) {
            latestSubmittedPromptMessageId.value = submittedMessageId;
            await loadTurnsForMessage(submittedMessageId, threadId);
        }
        agentStatusMessage.value = 'Agent is thinking...';
        await loadThreadMessages(threadId);
    } catch (error) {
        if (error.response?.status === 422) {
            promptErrors.value = error.response.data.errors ?? {};
        } else {
            promptErrorMessage.value = error.response?.data?.message ?? `Action submit failed (${error.response?.status ?? 'network'}).`;
        }
    } finally {
        isPrompting.value = false;
    }
};

const retryFailedTurn = async (turn) => {
    const prompt = (turn?.prompt_text ?? '').toString().trim();

    if (prompt === '') {
        return;
    }

    promptForm.content = prompt;
    promptForm.clientMessageId = '';
    promptForm.draftForClientId = '';
    await submitPrompt(latestAgentThread.value?.id);
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
                loadActiveChannel();
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
        :active-channel-id="activeChannel?.id ?? null"
        :active-thread-id="activeChannel?.active_thread ?? null"
        @open-thread="handleOpenThread"
    >
        <section v-if="activeChannel">
            <nav v-if="openThreadTabs.length > 0" class="workspace-tabs">
                <button
                    v-for="thread in openThreadTabs"
                    :key="thread.id"
                    type="button"
                    class="workspace-tab"
                    :class="{ 'workspace-tab--active': activeThreadId === thread.id }"
                    @click="handleOpenThread({ threadId: thread.id, thread: thread })"
                >
                    <span v-if="thread.nature === 'agent' || thread.nature === 'mixed'" class="workspace-tab__icon">✦</span>
                    <span v-else class="workspace-tab__icon">💬</span>
                    <span class="workspace-tab__title">{{ thread.title ?? 'Thread' }}</span>
                </button>
            </nav>

            <ChannelTimelinePanel
                :channel-id="activeChannel.id"
                :messages="channelFeedItems"
                :threads="allThreads"
                :is-loading="isLoadingChannelPosts"
                :error-message="channelPostsError"
                @manage-mcp="openContextServerPanel"
                @open-thread="handleOpenThread"
                @create-thread="handleCreateThread"
            />
            <p v-if="activeChannel.active_thread && threadLoadError" class="error">{{ threadLoadError }}</p>
            <p v-if="promptErrors['content.text']" class="error">{{ promptErrors['content.text'][0] }}</p>
            <p v-if="promptErrorMessage" class="error">{{ promptErrorMessage }}</p>
            <p v-if="agentStatusMessage" class="thread__meta">{{ agentStatusMessage }}</p>
        </section>

        <section v-else class="empty">
            <h3>Unable to load channel</h3>
            <p>Check your API connection and try again.</p>
            <Link :href="chatIndexUrl" class="link">Back</Link>
        </section>

        <HumanChatPanel
            v-if="activeChannel && shouldShowHumanChatPanel"
            v-model="isFloatingChatOpen"
            :active-thread-id="latestHumanThread?.id"
            :messages="floatingChatMessages"
            :sending="isPrompting"
            @send="submitFloatingPrompt"
        />

        <AgentWorkspacePanel
            v-if="activeChannel"
            v-model="isSlidingChatOpen"
            :active-thread-id="activeAgentThreadId"
            :threads="dockedAgentThreads"
            :messages="slidingChatMessages"
            :sending="isPrompting"
            @send="submitSlidingPrompt"
            @switch-thread="handleSwitchAgentThread"
            @close-thread="handleCloseAgentThread"
            @retry-turn="retryFailedTurn"
            @submit-a2ui-action="submitA2uiAction"
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
