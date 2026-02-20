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
});

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
const signalShowThreadTemplate = computed(() => {
    const configured = (signalRoutes.value.show_thread_template ?? '').toString().trim();
    if (configured !== '') {
        return configured;
    }

    return '/channels/__CHANNEL__/threads/__THREAD__';
});
const signalThreadUrl = (channelId, threadId) => signalShowThreadTemplate.value
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

        const response = await sendSignalChatMessage({
            content: {
                body: message,
                attachments: [],
            },
        }, runtime.value);
        const channelId = response.data?.channel;
        const threadId = response.data?.thread;

        if (channelId) {
            router.visit(threadId ? signalThreadUrl(channelId, threadId) : signalChannelUrl(channelId));
            return;
        }

        router.visit(signalIndexUrl.value);
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
    <Head title="Start Signal Chat" />

    <SignalLayout :channels="props.channels">
        <section class="signal-thread">
            <header class="signal-thread__header">
                <div>
                    <p class="signal-thread__kicker">New Chat</p>
                    <h2 class="signal-thread__title">Start chatting</h2>
                    <p class="signal-thread__meta">Send one message and we will open the channel instantly.</p>
                </div>
            </header>

            <form class="signal-form" @submit.prevent="submit">
                <textarea
                    id="message"
                    v-model="form.message"
                    class="signal-input"
                    rows="8"
                    maxlength="5000"
                    placeholder="Ask anything..."
                />
                <p v-if="errors.message" class="signal-error">{{ errors.message[0] }}</p>
                <p v-if="errors['content.body']" class="signal-error">{{ errors['content.body'][0] }}</p>
                <p v-if="formError" class="signal-error">{{ formError }}</p>

                <div class="signal-form__actions">
                    <Link :href="signalIndexUrl" class="signal-link">Cancel</Link>
                    <button class="signal-button" :disabled="isSubmitting">Send</button>
                </div>
            </form>
        </section>
    </SignalLayout>
</template>
