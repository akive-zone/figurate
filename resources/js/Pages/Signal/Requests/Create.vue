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
    flow_type: 'ubuy',
    profile_id: '',
    title: '',
    description: '',
    initial_message: '',
    contents: [],
});

const errors = ref({});
const isSubmitting = ref(false);
const flowOptions = [
    {
        value: 'ubuy',
        label: 'Direct Match',
        hint: 'You already know the worker you want.',
    },
    {
        value: 'upwork',
        label: 'Open Bids',
        hint: 'Multiple workers can bid, you choose one.',
    },
    {
        value: 'uber',
        label: 'Auto Assign',
        hint: 'System auto-picks the best available worker.',
    },
];

const onContentsSelected = (event) => {
    form.contents = Array.from(event.target.files ?? []);
};

const submit = async () => {
    errors.value = {};
    isSubmitting.value = true;

    try {
        const payload = new FormData();
        payload.append('flow_type', form.flow_type);
        payload.append('title', form.title);
        payload.append('description', form.description);

        if (form.profile_id) {
            payload.append('profile_id', String(form.profile_id));
        }

        if (form.initial_message) {
            payload.append('initial_message', form.initial_message);
        }

        form.contents.forEach((file) => {
            payload.append('contents[]', file);
        });

        const response = await axios.post('/api/request', payload, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });
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
                <label for="flow_type" class="signal-label">Routing Mode</label>
                <select id="flow_type" v-model="form.flow_type" class="signal-input">
                    <option v-for="option in flowOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
                <p class="signal-thread__meta">
                    {{ flowOptions.find((option) => option.value === form.flow_type)?.hint }}
                </p>
                <p v-if="errors.flow_type" class="signal-error">{{ errors.flow_type[0] }}</p>

                <label for="profile_id" class="signal-label">Choose Provider</label>
                <select id="profile_id" v-model="form.profile_id" class="signal-input" :disabled="form.flow_type !== 'ubuy'">
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

                <label for="contents" class="signal-label">Reference Files (optional)</label>
                <input
                    id="contents"
                    type="file"
                    class="signal-input"
                    multiple
                    accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.txt"
                    @change="onContentsSelected"
                />
                <p class="signal-thread__meta">Up to 8 files, 10MB each. Images and basic documents are supported.</p>
                <p v-if="errors.contents" class="signal-error">{{ errors.contents[0] }}</p>
                <p v-if="errors['contents.0']" class="signal-error">{{ errors['contents.0'][0] }}</p>

                <button class="signal-button" :disabled="isSubmitting">Open Channel</button>
            </form>
        </section>
    </SignalLayout>
</template>
