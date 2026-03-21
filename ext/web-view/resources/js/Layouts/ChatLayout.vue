<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useChatSidebar } from '../composables/useChatSidebar';
import { chatAuthHeaders } from '../api/chat';

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

const emit = defineEmits(['open-thread']);

const page = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);
const authRoutes = computed(() => page.props.auth?.routes ?? {});
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const runtime = computed(() => page.props.runtime ?? {});
const isNativeRuntime = computed(() => runtime.value.is_native === true);
const chatRoutes = computed(() => runtime.value.routes ?? {});
const chatIndexUrl = computed(() => {
    const configured = (chatRoutes.value.index ?? '').toString().trim();
    if (configured !== '') {
        return configured;
    }

    const fallback = (runtime.value.index_path ?? '').toString().trim();

    return fallback !== '' ? fallback : '/';
});
const chatCreateChannelUrl = computed(() => {
    const configured = (chatRoutes.value.create ?? '').toString().trim();
    if (configured !== '') {
        return configured;
    }

    return '/channels';
});
const chatShowTemplate = computed(() => {
    const configured = (chatRoutes.value.show_template ?? '').toString().trim();
    if (configured !== '') {
        return configured;
    }

    return '/channels/__CHANNEL__';
});
const chatShowThreadTemplate = computed(() => {
    const configured = (chatRoutes.value.show_thread_template ?? '').toString().trim();
    if (configured !== '') {
        return configured;
    }

    return '/channels/__CHANNEL__/threads/__THREAD__';
});
const chatChannelUrl = (channelId) => chatShowTemplate.value.replace('__CHANNEL__', channelId);
const chatChannelThreadUrl = (channelId, threadId) => {
    const channelUrl = chatChannelUrl(channelId);

    if (!threadId) {
        return channelUrl;
    }

    return chatShowThreadTemplate.value
        .replace('__CHANNEL__', channelId)
        .replace('__THREAD__', threadId);
};
const chatChannelId = (chat) => (chat?.channel?.id ?? '').toString();
const chatThreads = (chat) => (Array.isArray(chat?.threads) ? chat.threads : []);
const channelTitle = (chat) => (chat?.name ?? '').toString();
const chatLatestMessageBody = (chat) => {
    const latest = chat?.channel?.latest_message;

    if (typeof latest?.content === 'object' && latest?.content !== null) {
        return (latest.content.text ?? 'No messages yet').toString();
    }

    return (latest?.text ?? 'No messages yet').toString();
};
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
const passkeyAuthenticationOptionsUrl = computed(() => {
    const value = (authRoutes.value.passkeys_authentication_options ?? '').toString().trim();

    return value !== '' ? value : '/passkeys/authentication-options';
});
const passkeyLoginUrl = computed(() => {
    const value = (authRoutes.value.passkeys_login ?? '').toString().trim();

    return value !== '' ? value : '/passkeys/authenticate';
});
const passkeyLoginError = ref('');
const passkeyManageGenerateOptionsUrl = computed(() => {
    const value = (authRoutes.value.passkeys_manage_generate_options ?? '').toString().trim();

    return value !== '' ? value : '/api/auth/passkeys/options/register';
});
const passkeyManageStoreUrl = computed(() => {
    const value = (authRoutes.value.passkeys_manage_store ?? '').toString().trim();

    return value !== '' ? value : '/api/auth/passkeys';
});
const passkeyCreateError = ref('');
const isCreatingPasskey = ref(false);
const newPasskeyName = ref('');
const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
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

const handleThreadClick = (chat, thread) => {
    emit('open-thread', {
        channelId: chatChannelId(chat),
        threadId: thread.id,
        thread: thread
    });
};

const {
    sidebarChannels,
    canLoadMoreThreads,
    isLoadingMoreThreads,
    loadMoreThreads,
} = useChatSidebar(runtime, computed(() => props.channels ?? []));

const continueWithPasskey = async () => {
    passkeyLoginError.value = '';

    try {
        if (typeof window.startAuthentication !== 'function') {
            throw new Error('Passkey authentication is not available in this browser.');
        }

        if (typeof window.browserSupportsWebAuthn === 'function' && !window.browserSupportsWebAuthn()) {
            throw new Error('This browser does not support passkeys.');
        }

        const optionsResponse = await fetch(passkeyAuthenticationOptionsUrl.value, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
            },
        });

        if (!optionsResponse.ok) {
            throw new Error(`Unable to start passkey login (${optionsResponse.status}).`);
        }

        const options = await optionsResponse.json();
        const authenticationResponse = await window.startAuthentication({ optionsJSON: options });

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = passkeyLoginUrl.value;
        form.style.display = 'none';

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = csrfToken;

        const responseInput = document.createElement('input');
        responseInput.type = 'hidden';
        responseInput.name = 'start_authentication_response';
        responseInput.value = JSON.stringify(authenticationResponse);

        form.appendChild(csrfInput);
        form.appendChild(responseInput);
        document.body.appendChild(form);
        form.submit();
    } catch (error) {
        passkeyLoginError.value = error?.message ?? 'Unable to login with passkey.';
    }
};

const createPasskey = async () => {
    passkeyCreateError.value = '';

    try {
        if (typeof window.startRegistration !== 'function') {
            throw new Error('Passkey setup is not available in this browser.');
        }

        if (typeof window.browserSupportsWebAuthn === 'function' && !window.browserSupportsWebAuthn()) {
            throw new Error('This browser does not support passkeys.');
        }

        isCreatingPasskey.value = true;

        const optionsResponse = await fetch(passkeyManageGenerateOptionsUrl.value, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                ...chatAuthHeaders(),
            },
            body: JSON.stringify({}),
        });

        if (!optionsResponse.ok) {
            throw new Error(`Unable to start passkey setup (${optionsResponse.status}).`);
        }

        const optionsPayload = await optionsResponse.json();
        const ceremonyId = (optionsPayload?.data?.ceremony_id ?? '').toString().trim();
        const options = optionsPayload?.data?.options ?? null;

        if (!ceremonyId || !options) {
            throw new Error('Unable to start passkey setup.');
        }

        const registrationResponse = await window.startRegistration({ optionsJSON: options });
        const fallbackName = `Passkey ${new Date().toLocaleDateString()}`;
        const storeResponse = await fetch(passkeyManageStoreUrl.value, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                ...chatAuthHeaders(),
            },
            body: JSON.stringify({
                name: (newPasskeyName.value ?? '').toString().trim() || fallbackName,
                ceremony_id: ceremonyId,
                passkey: JSON.stringify(registrationResponse),
            }),
        });

        if (!storeResponse.ok) {
            const payload = await storeResponse.json().catch(() => ({}));
            const message = payload?.errors?.passkey?.[0]
                ?? payload?.errors?.name?.[0]
                ?? payload?.errors?.ceremony_id?.[0]
                ?? payload?.message
                ?? 'Unable to create passkey.';
            throw new Error(message);
        }

        newPasskeyName.value = '';
        window.location.reload();
    } catch (error) {
        passkeyCreateError.value = error?.message ?? 'Unable to create passkey.';
    } finally {
        isCreatingPasskey.value = false;
    }
};

</script>

<template>
    <div class="shell workspace">
        <aside class="sidebar">
            <div class="sidebar__head">
                <p class="sidebar__kicker">Chat</p>
                <h1 class="sidebar__title">Chats</h1>
            </div>

            <div class="sidebar__actions">
                <Link :href="chatCreateChannelUrl" class="button button--full">New Chat</Link>
            </div>

            <nav class="channel-nav">
                <div v-for="chat in sidebarChannels" :key="chat.id" class="channel-group">
                    <Link
                        :href="chatChannelUrl(chatChannelId(chat))"
                        class="channel-link"
                        :class="{ 'channel-link--active': props.activeChannelId === chatChannelId(chat) }"
                    >
                        <p class="channel-link__title">{{ channelTitle(chat) }}</p>
                        <p class="channel-link__meta">{{ chatLatestMessageBody(chat) }}</p>
                    </Link>
                    <div
                        v-if="props.activeChannelId === chatChannelId(chat) && chatThreads(chat).length > 0"
                        class="thread-tree"
                    >
                        <button
                            v-for="thread in chatThreads(chat)"
                            :key="thread.id"
                            type="button"
                            class="thread-tree__item"
                            :class="{ 'thread-tree__item--active': props.activeThreadId === thread.id }"
                            @click="handleThreadClick(chat, thread)"
                        >
                            {{ thread.title ?? 'Thread' }}
                        </button>
                        <button
                            v-if="canLoadMoreThreads(chat)"
                            type="button"
                            class="link"
                            :disabled="isLoadingMoreThreads(chat.id)"
                            @click="loadMoreThreads(chat)"
                        >
                            {{ isLoadingMoreThreads(chat.id) ? 'Loading...' : 'Load more threads' }}
                        </button>
                    </div>
                </div>
                <p v-if="sidebarChannels.length === 0" class="sidebar__empty">No chats yet</p>
            </nav>

            <footer class="footer" v-if="authUser">
                <span>{{ displayAccountName }}</span>
            </footer>
        </aside>

        <main class="main panel">
            <header class="header">
                <div class="header__actions">
                    <Link :href="chatIndexUrl" class="link">All Chats</Link>
                    <button type="button" class="user-chip" @click="showAccountModal = true">
                        {{ accountButtonLabel }}
                    </button>
                </div>
            </header>

            <section v-if="flashSuccess" class="alert alert--success">{{ flashSuccess }}</section>
            <section v-if="flashError" class="alert alert--error">{{ flashError }}</section>

            <div class="main__content">
                <slot />
            </div>
        </main>

        <div v-if="showAccountModal" class="modal-overlay" @click.self="showAccountModal = false">
            <section class="modal">
                <header class="modal__header">
                    <h3 class="modal__title">Account</h3>
                    <button type="button" class="link" @click="showAccountModal = false">Close</button>
                </header>

                <p class="modal__meta" v-if="authUser">
                    {{ showDeviceLoginPrompt ? 'Sign in to keep your chats across devices.' : `Signed in as: ${displayAccountName}` }}
                </p>

                <div class="modal__actions" v-if="showDeviceLoginPrompt">
                    <a :href="googleLoginUrl" class="button button--full">Continue with Google</a>
                    <a :href="appleLoginUrl" class="link link--full">Continue with Apple</a>
                    <button type="button" class="link link--full" @click="continueWithPasskey">
                        Continue with Passkey
                    </button>
                    <p v-if="passkeyLoginError" class="error">{{ passkeyLoginError }}</p>
                </div>

                <p class="modal__meta" v-else>
                    You are already signed in.
                </p>

                <section class="passkeys">
                    <h4 class="passkeys__title">Add passkey</h4>
                    <p class="modal__meta">Create a passkey for this device user account.</p>

                    <form class="passkeys__form" @submit.prevent="createPasskey">
                        <input
                            v-model="newPasskeyName"
                            type="text"
                            class="input"
                            maxlength="255"
                            placeholder="Passkey name (optional)"
                        >
                        <button type="submit" class="button" :disabled="isCreatingPasskey">
                            {{ isCreatingPasskey ? 'Creating...' : 'Add passkey' }}
                        </button>
                    </form>

                    <p v-if="passkeyCreateError" class="error">{{ passkeyCreateError }}</p>
                </section>
            </section>
        </div>
    </div>
</template>
