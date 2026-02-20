<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { fetchSignalChatThreads, fetchSignalChats } from '../api/signalChat';

const props = defineProps({
    channels: {
        type: Array,
        default: () => [],
    },
    activeChannelId: {
        type: String,
        default: null,
    },
    activeThreadId: {
        type: String,
        default: null,
    },
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);
const authRoutes = computed(() => page.props.auth?.routes ?? {});
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const runtime = computed(() => page.props.runtime ?? {});
const isNativeRuntime = computed(() => runtime.value.is_native === true);
const signalRoutes = computed(() => runtime.value.signal_routes ?? {});
const signalIndexUrl = computed(() => {
    const configured = (signalRoutes.value.index ?? '').toString().trim();
    if (configured !== '') {
        return configured;
    }

    const fallback = (runtime.value.signal_index_path ?? '').toString().trim();

    return fallback !== '' ? fallback : '/';
});
const signalCreateChannelUrl = computed(() => {
    const configured = (signalRoutes.value.create ?? '').toString().trim();
    if (configured !== '') {
        return configured;
    }

    return '/channels/new';
});
const signalShowTemplate = computed(() => {
    const configured = (signalRoutes.value.show_template ?? '').toString().trim();
    if (configured !== '') {
        return configured;
    }

    return '/channels/__CHANNEL__';
});
const signalShowThreadTemplate = computed(() => {
    const configured = (signalRoutes.value.show_thread_template ?? '').toString().trim();
    if (configured !== '') {
        return configured;
    }

    return '/channels/__CHANNEL__/threads/__THREAD__';
});
const signalChannelUrl = (channelId) => signalShowTemplate.value.replace('__CHANNEL__', channelId);
const signalChannelThreadUrl = (channelId, threadId) => {
    const channelUrl = signalChannelUrl(channelId);

    if (!threadId) {
        return channelUrl;
    }

    return signalShowThreadTemplate.value
        .replace('__CHANNEL__', channelId)
        .replace('__THREAD__', threadId);
};
const chatChannelId = (chat) => (chat?.channel?.id ?? '').toString();
const chatThreads = (chat) => (Array.isArray(chat?.threads) ? chat.threads : []);
const channelTitle = (chat) => (chat?.name ?? '').toString();
const chatLatestMessageBody = (chat) => (chat?.channel?.latest_message?.body ?? 'No messages yet').toString();
const showDeviceLoginPrompt = computed(() => !isNativeRuntime.value && authUser.value?.type === 'device');
const showAccountModal = ref(false);
const googleLoginUrl = computed(() => {
    const value = (authRoutes.value.google_redirect ?? '').toString().trim();

    return value !== '' ? value : '/auth/google/redirect';
});
const appleLoginUrl = computed(() => {
    const value = (authRoutes.value.apple_redirect ?? '').toString().trim();

    return value !== '' ? value : '/auth/apple/redirect';
});
const userInitials = computed(() => {
    const rawName = (authUser.value?.name ?? '').toString().trim();
    const name = showDeviceLoginPrompt.value ? 'Guest' : rawName;
    if (name === '') {
        return 'U';
    }

    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
});
const accountButtonLabel = computed(() => (showDeviceLoginPrompt.value ? 'Login' : userInitials.value));
const displayAccountName = computed(() => {
    if (showDeviceLoginPrompt.value) {
        return 'Guest';
    }

    return (authUser.value?.name ?? 'Account').toString();
});
const sidebarChannels = ref(props.channels ?? []);
const loadingMoreThreadsByChat = ref({});

watch(
    () => props.channels,
    (value) => {
        sidebarChannels.value = value ?? [];
    },
    { deep: true },
);

const refreshSidebarChats = async () => {
    try {
        const payloadResponse = await fetchSignalChats(runtime.value);
        const payload = payloadResponse?.data;
        if (Array.isArray(payload)) {
            sidebarChannels.value = payload;
        }
    } catch {
        // Keep page-provided fallback data when request fails.
    }
};

const canLoadMoreThreads = (chat) => {
    const cursor = (chat?.threads_meta?.next_cursor ?? '').toString().trim();

    return cursor !== '';
};

const isLoadingMoreThreads = (chatId) => loadingMoreThreadsByChat.value[chatId] === true;

const mergeThreads = (existingThreads, incomingThreads) => {
    const existing = Array.isArray(existingThreads) ? existingThreads : [];
    const incoming = Array.isArray(incomingThreads) ? incomingThreads : [];
    const seen = new Set();
    const merged = [];

    [...existing, ...incoming].forEach((thread) => {
        const id = (thread?.id ?? '').toString().trim();
        if (id === '' || seen.has(id)) {
            return;
        }

        seen.add(id);
        merged.push(thread);
    });

    return merged;
};

const loadMoreThreads = async (chat) => {
    const chatId = (chat?.id ?? '').toString().trim();
    const cursor = (chat?.threads_meta?.next_cursor ?? '').toString().trim();

    if (chatId === '' || cursor === '' || isLoadingMoreThreads(chatId)) {
        return;
    }

    loadingMoreThreadsByChat.value = {
        ...loadingMoreThreadsByChat.value,
        [chatId]: true,
    };

    try {
        const payload = await fetchSignalChatThreads(chatId, runtime.value, { cursor });
        const nextThreads = Array.isArray(payload?.data) ? payload.data : [];
        const nextMeta = payload?.meta ?? {};

        sidebarChannels.value = sidebarChannels.value.map((item) => {
            if (item?.id !== chatId) {
                return item;
            }

            return {
                ...item,
                threads: mergeThreads(item.threads, nextThreads),
                threads_meta: {
                    next_cursor: nextMeta.next_cursor ?? null,
                    prev_cursor: nextMeta.prev_cursor ?? null,
                    per_page: nextMeta.per_page ?? item?.threads_meta?.per_page ?? 5,
                },
            };
        });
    } catch {
        // Keep existing threads list when request fails.
    } finally {
        loadingMoreThreadsByChat.value = {
            ...loadingMoreThreadsByChat.value,
            [chatId]: false,
        };
    }
};

onMounted(() => {
    refreshSidebarChats();
});
</script>

<template>
    <div class="signal-shell signal-workspace">
        <aside class="signal-sidebar">
            <div class="signal-sidebar__head">
                <p class="signal-sidebar__kicker">Signal</p>
                <h1 class="signal-sidebar__title">Chats</h1>
            </div>

            <div class="signal-sidebar__actions">
                <Link :href="signalCreateChannelUrl" class="signal-button signal-button--full">New Chat</Link>
            </div>

            <nav class="signal-channel-nav">
                <div v-for="chat in sidebarChannels" :key="chat.id" class="signal-channel-group">
                    <Link
                        :href="signalChannelUrl(chatChannelId(chat))"
                        class="signal-channel-link"
                        :class="{ 'signal-channel-link--active': props.activeChannelId === chatChannelId(chat) }"
                    >
                        <p class="signal-channel-link__title">{{ channelTitle(chat) }}</p>
                        <p class="signal-channel-link__meta">{{ chatLatestMessageBody(chat) }}</p>
                    </Link>
                    <div
                        v-if="props.activeChannelId === chatChannelId(chat) && chatThreads(chat).length > 0"
                        class="signal-thread-tree"
                    >
                        <Link
                            v-for="thread in chatThreads(chat)"
                            :key="thread.id"
                            :href="signalChannelThreadUrl(chatChannelId(chat), thread.id)"
                            class="signal-thread-tree__item"
                            :class="{ 'signal-thread-tree__item--active': props.activeThreadId === thread.id }"
                        >
                            {{ thread.title ?? 'Thread' }}
                        </Link>
                        <button
                            v-if="canLoadMoreThreads(chat)"
                            type="button"
                            class="signal-link"
                            :disabled="isLoadingMoreThreads(chat.id)"
                            @click="loadMoreThreads(chat)"
                        >
                            {{ isLoadingMoreThreads(chat.id) ? 'Loading...' : 'Load more threads' }}
                        </button>
                    </div>
                </div>
                <p v-if="sidebarChannels.length === 0" class="signal-sidebar__empty">No chats yet</p>
            </nav>

            <footer class="signal-footer" v-if="authUser">
                <span>{{ displayAccountName }}</span>
            </footer>
        </aside>

        <main class="signal-main signal-panel">
            <header class="signal-header">
                <div class="signal-header__actions">
                    <Link :href="signalIndexUrl" class="signal-link">All Chats</Link>
                    <button type="button" class="signal-user-chip" @click="showAccountModal = true">
                        {{ accountButtonLabel }}
                    </button>
                </div>
            </header>

            <section v-if="flashSuccess" class="signal-alert signal-alert--success">{{ flashSuccess }}</section>
            <section v-if="flashError" class="signal-alert signal-alert--error">{{ flashError }}</section>

            <div class="signal-main__content">
                <slot />
            </div>
        </main>

        <div v-if="showAccountModal" class="signal-modal-overlay" @click.self="showAccountModal = false">
            <section class="signal-modal">
                <header class="signal-modal__header">
                    <h3 class="signal-modal__title">Account</h3>
                    <button type="button" class="signal-link" @click="showAccountModal = false">Close</button>
                </header>

                <p class="signal-modal__meta" v-if="authUser">
                    {{ showDeviceLoginPrompt ? 'Sign in to keep your chats across devices.' : `Signed in as: ${displayAccountName}` }}
                </p>

                <div class="signal-modal__actions" v-if="showDeviceLoginPrompt">
                    <a :href="googleLoginUrl" class="signal-button signal-button--full">Continue with Google</a>
                    <a :href="appleLoginUrl" class="signal-link signal-link--full">Continue with Apple</a>
                </div>

                <p class="signal-modal__meta" v-else>
                    You are already signed in.
                </p>
            </section>
        </div>
    </div>
</template>
