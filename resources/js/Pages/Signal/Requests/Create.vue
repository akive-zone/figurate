<script setup>
import SignalLayout from '../../../Layouts/SignalLayout.vue';
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const props = defineProps({
});

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
            router.visit(`/signal/channels/${channelId}${query}`);
            return;
        }

        router.visit('/signal');
    } catch (error) {
        persistBootstrapHeaders(error.response);

        if (error.response?.status === 422) {
            errors.value = error.response.data.errors ?? {};
        } else {
            formError.value = error.response?.data?.message ?? `Request failed (${error.response?.status ?? 'network'}).`;
        }
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <Head title="Start Signal Chat" />

    <SignalLayout>
        <section class="signal-thread">
            <header class="signal-thread__header">
                <div>
                    <p class="signal-thread__kicker">Chat</p>
                    <h2 class="signal-thread__title">Open Channel</h2>
                    <p class="signal-thread__meta">Send your first message and jump into the conversation.</p>
                </div>
                <Link href="/signal" class="signal-link">Back</Link>
            </header>

            <form class="signal-form" @submit.prevent="submit">
                <label for="message" class="signal-label">First Message</label>
                <textarea
                    id="message"
                    v-model="form.message"
                    class="signal-input"
                    rows="6"
                    maxlength="5000"
                    placeholder="Describe what you need and start the conversation..."
                />
                <p v-if="errors.message" class="signal-error">{{ errors.message[0] }}</p>
                <p v-if="errors.content" class="signal-error">{{ errors.content[0] }}</p>
                <p v-if="formError" class="signal-error">{{ formError }}</p>

                <button class="signal-button" :disabled="isSubmitting">Start Chat</button>
            </form>
        </section>
    </SignalLayout>
</template>
