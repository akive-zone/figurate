<script setup>
import SignalLayout from '../../../Layouts/SignalLayout.vue';
import axios from 'axios';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

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

const form = reactive({
    message: '',
});

const errors = ref({});
const formError = ref('');
const isSubmitting = ref(false);

const serverBaseUrl = (import.meta.env.VITE_SERVER_BASE_URL ?? '').toString().trim().replace(/\/$/, '');

const resolveApiUrl = (path) => {
    if (!serverBaseUrl) {
        return path;
    }

    return `${serverBaseUrl}${path}`;
};

const deviceIdStorageKey = 'signal.device_id';
const apiTokenStorageKey = 'signal.api_token';

const readStorage = (key) => {
    if (typeof window === 'undefined') {
        return '';
    }

    return window.localStorage.getItem(key) ?? '';
};

const writeStorage = (key, value) => {
    if (typeof window === 'undefined' || !value) {
        return;
    }

    window.localStorage.setItem(key, value);
};

const persistBootstrapHeaders = (response) => {
    if (!response || !response.headers) {
        return;
    }

    const deviceId = response.headers['x-device-id'];
    const apiToken = response.headers['x-api-token'];

    if (typeof deviceId === 'string' && deviceId !== '') {
        writeStorage(deviceIdStorageKey, deviceId);
    }

    if (typeof apiToken === 'string' && apiToken !== '') {
        writeStorage(apiTokenStorageKey, apiToken);
    }
};

const authHeaders = () => {
    const deviceId = readStorage(deviceIdStorageKey);
    const token = readStorage(apiTokenStorageKey);
    const headers = {};

    if (deviceId) {
        headers['X-Device-Id'] = deviceId;
    }

    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    return headers;
};

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

        const response = await axios.post(resolveApiUrl('/api/chat'), {
            content: message,
        }, {
            headers: {
                ...authHeaders(),
            },
        });
        persistBootstrapHeaders(response);
        const channelId = response.data?.channel;
        const threadId = response.data?.thread;

        if (channelId) {
            const query = threadId ? `?thread=${threadId}` : '';
            router.visit(`${signalChannelUrl(channelId)}${query}`);
            return;
        }

        router.visit(signalIndexUrl.value);
    } catch (error) {
        persistBootstrapHeaders(error.response);

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
                <p v-if="errors.content" class="signal-error">{{ errors.content[0] }}</p>
                <p v-if="formError" class="signal-error">{{ formError }}</p>

                <div class="signal-form__actions">
                    <Link :href="signalIndexUrl" class="signal-link">Cancel</Link>
                    <button class="signal-button" :disabled="isSubmitting">Send</button>
                </div>
            </form>
        </section>
    </SignalLayout>
</template>
