<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

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
</script>

<template>
    <Head title="404 Not Found" />

    <main class="signal-not-found">
        <section class="signal-not-found__card">
            <p class="signal-not-found__kicker">404</p>
            <h1 class="signal-not-found__title">Channel Not Found</h1>
            <p class="signal-not-found__copy">
                The channel link is invalid or the conversation no longer exists.
            </p>
            <div class="signal-not-found__actions">
                <Link :href="signalIndexUrl" class="signal-button">Back to Channels</Link>
            </div>
        </section>
    </main>
</template>
