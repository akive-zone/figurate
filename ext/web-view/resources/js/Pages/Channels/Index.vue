<script setup>
import ChatLayout from '../../Layouts/ChatLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { reactive, ref } from 'vue';
import { chatDataService } from '../../services/chatDataService';

defineProps({
    channels: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const runtime = computed(() => page.props.runtime ?? {});
const chatRoutes = computed(() => runtime.value.routes ?? {});
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
                message: ['Write a message to start the chat.'],
            };

            return;
        }

        const response = await chatDataService.sendMessage({
            content: {
                text: message,
                attachments: [],
            },
        }, runtime.value);
        const channelId = response.data?.channel;
        const threadId = response.data?.thread;

        if (channelId) {
            window.location.href = threadId ? chatThreadUrl(channelId, threadId) : chatChannelUrl(channelId);
            return;
        }

        window.location.href = chatCreateChannelUrl.value;
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
    <Head title="Chat Chat" />

    <ChatLayout :channels="channels">
        <section class="home home--composer">
            <h2 class="home__title">What&rsquo;s on the agenda today?</h2>
            <p class="home__subtitle">Start typing to open a new channel, or open one from the sidebar.</p>

            <form class="form form--home" @submit.prevent="submit">
                <textarea
                    id="message"
                    v-model="form.message"
                    class="input input--home"
                    rows="3"
                    maxlength="5000"
                    placeholder="Ask anything..."
                />
                <p v-if="errors.message" class="error">{{ errors.message[0] }}</p>
                <p v-if="errors['content.text']" class="error">{{ errors['content.text'][0] }}</p>
                <p v-if="formError" class="error">{{ formError }}</p>
                <div class="form__actions">
                    <Link :href="chatCreateChannelUrl" class="link">Open Full Composer</Link>
                    <button class="button" :disabled="isSubmitting">Send</button>
                </div>
            </form>
        </section>
    </ChatLayout>
</template>
