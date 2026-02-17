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

const formatTimestamp = (value) => new Date(value).toLocaleString();
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
                    <p class="signal-thread__meta">Open channel, one chatbox, and thread orchestration behind the scenes.</p>
                </div>
            </header>

            <section class="signal-thread__messages">
                <article
                    v-for="message in (activeChannel.channel_feed ?? activeChannel.thread_messages)"
                    :key="`${message.kind ?? 'message'}-${message.scope ?? 'channel'}-${message.id}`"
                    class="signal-message"
                    :class="{ 'signal-message--mine': message.scope === 'request' }"
                >
                    <p class="signal-message__author">
                        {{ message.scope === 'thread' ? 'Thread' : message.scope === 'channel' ? 'Channel' : 'Main' }}
                    </p>
                    <p>{{ message.content }}</p>
                    <ul v-if="message.attachments?.length" class="signal-thread__attachments">
                        <li v-for="attachment in message.attachments" :key="attachment.path">
                            {{ attachment.name }} ({{ attachment.mime }})
                        </li>
                    </ul>
                    <p class="signal-message__time">{{ formatTimestamp(message.created_at) }}</p>
                </article>

                <article v-if="!(activeChannel.channel_feed ?? activeChannel.thread_messages)?.length" class="signal-empty">
                    <h3>No posts yet</h3>
                    <p>Send a message and the channel feed will populate in chronological order.</p>
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
