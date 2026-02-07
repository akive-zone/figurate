<script setup>
import SignalLayout from '../../../Layouts/SignalLayout.vue';
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

defineProps({
    profiles: {
        type: Array,
        required: true,
    },
});

const form = reactive({
    profile_id: '',
    title: '',
    description: '',
    initial_message: '',
});

const errors = ref({});
const isSubmitting = ref(false);

const submit = async () => {
    errors.value = {};
    isSubmitting.value = true;

    try {
        const response = await axios.post('/api/request', form);
        const channelId = response.data?.channel_id;
        const threadId = response.data?.thread_id;

        if (channelId) {
            const query = threadId ? `?thread=${threadId}` : '';
            router.visit(`/signal/chat/${channelId}${query}`);
            return;
        }

        router.visit('/signal');
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors ?? {};
        }
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <Head title="New Signal Request" />

    <SignalLayout>
        <section class="signal-thread">
            <header class="signal-thread__header">
                <div>
                    <p class="signal-thread__kicker">Request</p>
                    <h2 class="signal-thread__title">Start Channel + Request</h2>
                    <p class="signal-thread__meta">This opens a channel and starts fulfillment from request stage.</p>
                </div>
                <Link href="/signal" class="signal-link">Back</Link>
            </header>

            <form class="signal-form" @submit.prevent="submit">
                <label for="profile_id" class="signal-label">Choose Provider</label>
                <select id="profile_id" v-model="form.profile_id" class="signal-input">
                    <option disabled value="">Select a profile</option>
                    <option v-for="profile in profiles" :key="profile.id" :value="profile.id">
                        {{ profile.display_name }}{{ profile.location ? ` (${profile.location})` : '' }}
                    </option>
                </select>
                <p v-if="errors.profile_id" class="signal-error">{{ errors.profile_id[0] }}</p>

                <label for="title" class="signal-label">Request Title</label>
                <input id="title" v-model="form.title" class="signal-input" maxlength="160" />
                <p v-if="errors.title" class="signal-error">{{ errors.title[0] }}</p>

                <label for="description" class="signal-label">Request Details</label>
                <textarea id="description" v-model="form.description" class="signal-input" rows="5" />
                <p v-if="errors.description" class="signal-error">{{ errors.description[0] }}</p>

                <label for="initial_message" class="signal-label">First Chat Message (optional)</label>
                <textarea id="initial_message" v-model="form.initial_message" class="signal-input" rows="4" />
                <p v-if="errors.initial_message" class="signal-error">{{ errors.initial_message[0] }}</p>

                <button class="signal-button" :disabled="isSubmitting">Open Channel</button>
            </form>
        </section>
    </SignalLayout>
</template>
