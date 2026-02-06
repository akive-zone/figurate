<script setup>
import SignalLayout from '../../../Layouts/SignalLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    profiles: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    profile_id: '',
    title: '',
    description: '',
    initial_message: '',
});

const submit = () => {
    form.post('/signal/requests');
};
</script>

<template>
    <Head title="New Signal Request" />

    <SignalLayout>
        <section class="signal-thread">
            <header class="signal-thread__header">
                <div>
                    <p class="signal-thread__kicker">Request</p>
                    <h2 class="signal-thread__title">Start Conversation + Request</h2>
                    <p class="signal-thread__meta">This opens chat and starts fulfillment from request stage.</p>
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
                <p v-if="form.errors.profile_id" class="signal-error">{{ form.errors.profile_id }}</p>

                <label for="title" class="signal-label">Request Title</label>
                <input id="title" v-model="form.title" class="signal-input" maxlength="160" />
                <p v-if="form.errors.title" class="signal-error">{{ form.errors.title }}</p>

                <label for="description" class="signal-label">Request Details</label>
                <textarea id="description" v-model="form.description" class="signal-input" rows="5" />
                <p v-if="form.errors.description" class="signal-error">{{ form.errors.description }}</p>

                <label for="initial_message" class="signal-label">First Chat Message (optional)</label>
                <textarea id="initial_message" v-model="form.initial_message" class="signal-input" rows="4" />
                <p v-if="form.errors.initial_message" class="signal-error">{{ form.errors.initial_message }}</p>

                <button class="signal-button" :disabled="form.processing">Open Conversation</button>
            </form>
        </section>
    </SignalLayout>
</template>
