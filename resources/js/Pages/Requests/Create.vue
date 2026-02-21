<script setup>
import ChatLayout from '../../../Layouts/ChatLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { sendChatChatMessage } from '../../../api';

const props = defineProps({
    channels: {
        type: Array,
        default: () => [],
    },
});

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
const chatShowTemplate = computed(() => {
    const configured = (chatRoutes.value.show_template ?? '').toString().trim();
    if (configured !== '') {
        return configured;
    }

    return '/channels/__CHANNEL__';
});
const chatChannelUrl = (channelId) => chatShowTemplate.value.replace('__CHANNEL__', channelId);
const chatShowThreadTemplate = computed(() => {
    const configured = (chatRoutes.value.show_thread_template ?? '').toString().trim();
    if (configured !== '') {
        return configured;
    }

    return '/channels/__CHANNEL__/threads/__THREAD__';
});
const chatThreadUrl = (channelId, threadId) => chatShowThreadTemplate.value
    .replace('__CHANNEL__', channelId)
    .replace('__THREAD__', threadId);

const form = reactive({
    message: '',
});

const errors = ref({});
const formError = ref('');
const isSubmitting = ref(false);

const submit = async () => {
    errors.value = {};
    formError.value = '';
    isSubmitting.value = true;

    try {
        const message = form.message.trim();

        if (!message) {
            errors.value = {
                message: ['Write a message to open a channel.'],
            };

            return;
        }

        const response = await sendChatChatMessage({
            body: message,
            attachments: [],
        }, runtime.value);
        const channelId = response.data?.channel;
        const threadId = response.data?.thread;

        if (channelId) {
            router.visit(threadId ? chatThreadUrl(channelId, threadId) : chatChannelUrl(channelId));
            return;
        }

        router.visit(chatIndexUrl.value);
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors ?? {};
        } else {
            formError.value = error.response?.data?.message ?? `Message failed (${error.response?.status ?? 'network'}).`;
        }
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <Head title="Start Chat Chat" />

    <ChatLayout :channels="props.channels">
        <section class="thread">
            <header class="thread__header">
                <div>
                    <p class="thread__kicker">New Chat</p>
                    <h2 class="thread__title">Start chatting</h2>
                    <p class="thread__meta">Send one message and we will open the channel instantly.</p>
                </div>
            </header>

            <form class="form" @submit.prevent="submit">
                <textarea
                    id="message"
                    v-model="form.message"
                    class="input"
                    rows="8"
                    maxlength="5000"
                    placeholder="Ask anything..."
                />
                <p v-if="errors.message" class="error">{{ errors.message[0] }}</p>
                <p v-if="errors.body" class="error">{{ errors.body[0] }}</p>
                <p v-if="formError" class="error">{{ formError }}</p>

                <div class="form__actions">
                    <Link :href="chatIndexUrl" class="link">Cancel</Link>
                    <button class="button" :disabled="isSubmitting">Send</button>
                </div>
            </form>
        </section>
    </ChatLayout>
</template>
