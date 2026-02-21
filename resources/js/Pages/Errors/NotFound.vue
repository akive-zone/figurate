<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

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
</script>

<template>
    <Head title="404 Not Found" />

    <main class="not-found">
        <section class="not-found__card">
            <p class="not-found__kicker">404</p>
            <h1 class="not-found__title">Channel Not Found</h1>
            <p class="not-found__copy">
                The channel link is invalid or the conversation no longer exists.
            </p>
            <div class="not-found__actions">
                <Link :href="chatIndexUrl" class="button">Back to Channels</Link>
            </div>
        </section>
    </main>
</template>
