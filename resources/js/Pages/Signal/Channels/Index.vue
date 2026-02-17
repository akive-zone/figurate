<script setup>
import SignalLayout from '../../../Layouts/SignalLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { reactive, ref } from 'vue';
import { sendSignalChatMessage } from '../../../api/signalChat';

defineProps({
    channels: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const runtime = computed(() => page.props.runtime ?? {});
const signalRoutes = computed(() => runtime.value.signal_routes ?? {});
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

        const response = await sendSignalChatMessage({
            content: message,
        }, runtime.value);
        const channelId = response.data?.channel;
        const threadId = response.data?.thread;

        if (channelId) {
            const query = threadId ? `?thread=${threadId}` : '';
            window.location.href = `${signalChannelUrl(channelId)}${query}`;
            return;
        }

        window.location.href = signalCreateChannelUrl.value;
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
    <Head title="Signal Chat" />

    <SignalLayout :channels="channels">
        <section class="signal-home signal-home--composer">
            <h2 class="signal-home__title">What&rsquo;s on the agenda today?</h2>
            <p class="signal-home__subtitle">Start typing to open a new channel, or open one from the sidebar.</p>

            <form class="signal-form signal-form--home" @submit.prevent="submit">
                <textarea
                    id="message"
                    v-model="form.message"
                    class="signal-input signal-input--home"
                    rows="3"
                    maxlength="5000"
                    placeholder="Ask anything..."
                />
                <p v-if="errors.message" class="signal-error">{{ errors.message[0] }}</p>
                <p v-if="errors.content" class="signal-error">{{ errors.content[0] }}</p>
                <p v-if="formError" class="signal-error">{{ formError }}</p>
                <div class="signal-form__actions">
                    <Link :href="signalCreateChannelUrl" class="signal-link">Open Full Composer</Link>
                    <button class="signal-button" :disabled="isSubmitting">Send</button>
                </div>
            </form>
        </section>
    </SignalLayout>
</template>
