<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    channels: {
        type: Array,
        default: () => [],
    },
    activeChannelId: {
        type: String,
        default: null,
    },
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
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
const signalChannelUrl = (channelId) => signalShowTemplate.value.replace('__CHANNEL__', channelId);
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
                <Link
                    v-for="channel in props.channels"
                    :key="channel.id"
                    :href="signalChannelUrl(channel.id)"
                    class="signal-channel-link"
                    :class="{ 'signal-channel-link--active': props.activeChannelId === channel.id }"
                >
                    <p class="signal-channel-link__title">{{ channel.request?.title ?? 'Untitled chat' }}</p>
                    <p class="signal-channel-link__meta">{{ channel.latest_message?.body ?? 'No messages yet' }}</p>
                </Link>
                <p v-if="props.channels.length === 0" class="signal-sidebar__empty">No chats yet</p>
            </nav>

            <footer class="signal-footer" v-if="authUser">
                <span>{{ authUser.name }}</span>
                <span>{{ authUser.type }}</span>
            </footer>
        </aside>

        <main class="signal-main signal-panel">
            <header class="signal-header">
                <div class="signal-header__actions">
                    <Link :href="signalIndexUrl" class="signal-link">All Chats</Link>
                </div>
            </header>

            <section v-if="flashSuccess" class="signal-alert signal-alert--success">{{ flashSuccess }}</section>
            <section v-if="flashError" class="signal-alert signal-alert--error">{{ flashError }}</section>

            <div class="signal-main__content">
                <slot />
            </div>
        </main>
    </div>
</template>
