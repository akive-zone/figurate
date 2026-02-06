<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
</script>

<template>
    <div class="signal-shell min-h-screen">
        <header class="signal-header">
            <div class="signal-header__brand">
                <p class="signal-header__kicker">Signal</p>
                <h1 class="signal-header__title">Conversations</h1>
            </div>
            <div class="signal-header__actions">
                <Link href="/" class="signal-link">Launcher</Link>
                <Link href="/signal/requests/new" class="signal-button">New Request</Link>
            </div>
        </header>

        <section v-if="flashSuccess" class="signal-alert signal-alert--success">{{ flashSuccess }}</section>
        <section v-if="flashError" class="signal-alert signal-alert--error">{{ flashError }}</section>

        <main class="signal-main">
            <slot />
        </main>

        <footer class="signal-footer" v-if="authUser">
            <span>{{ authUser.name }}</span>
            <span>{{ authUser.type }}</span>
        </footer>
    </div>
</template>
