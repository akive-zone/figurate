<script setup>
import { computed } from 'vue';

const props = defineProps({
    turns: {
        type: Array,
        default: () => [],
    },
});
const emit = defineEmits(['retry']);

const activeTurns = computed(() => {
    const turns = Array.isArray(props.turns) ? props.turns : [];

    return turns
        .filter((turn) => {
            const status = (turn?.status ?? '').toString().trim();

            return status !== 'completed';
        })
        .slice(-8);
});

const formatActor = (actorKey) => {
    const value = (actorKey ?? '').toString().trim();

    if (value === '') {
        return 'Handler';
    }

    return value.replaceAll('_', ' ');
};

const formatStatus = (status) => {
    const value = (status ?? '').toString().trim();

    if (value === 'failed') {
        return 'Failed';
    }

    return 'Pending';
};

const formatInvocation = (invocationId) => {
    const value = (invocationId ?? '').toString().trim();

    if (value === '') {
        return 'no-invocation';
    }

    return value.length > 16 ? `${value.slice(0, 16)}...` : value;
};

const formatPrompt = (promptText) => {
    const value = (promptText ?? '').toString().trim();

    if (value === '') {
        return 'Awaiting prompt context...';
    }

    return value.length > 180 ? `${value.slice(0, 180)}...` : value;
};

const canRetry = (turn) => {
    const status = (turn?.status ?? '').toString().trim();
    const prompt = (turn?.prompt_text ?? '').toString().trim();

    return status === 'failed' && prompt !== '';
};

const retry = (turn) => {
    if (!canRetry(turn)) {
        return;
    }

    emit('retry', turn);
};
</script>

<template>
    <section v-if="activeTurns.length > 0" class="pending-turns" aria-label="Pending agent turns">
        <header class="pending-turns__header">
            <h3 class="pending-turns__title">Pending Turns</h3>
            <small>{{ activeTurns.length }}</small>
        </header>

        <div class="pending-turns__grid">
            <article
                v-for="turn in activeTurns"
                :key="turn.id ?? `${turn.prompt_post_id}-${turn.actor_key}`"
                class="pending-turn"
                :class="{ 'pending-turn--failed': turn.status === 'failed' }"
            >
                <p class="pending-turn__meta">
                    <span>{{ formatActor(turn.actor_key) }}</span>
                    <span>{{ formatStatus(turn.status) }}</span>
                </p>
                <p class="pending-turn__prompt">{{ formatPrompt(turn.prompt_text) }}</p>
                <p class="pending-turn__id">{{ formatInvocation(turn.invocation_id) }}</p>
                <button
                    v-if="canRetry(turn)"
                    type="button"
                    class="pending-turn__retry"
                    @click="retry(turn)"
                >
                    Retry
                </button>
            </article>
        </div>
    </section>
</template>
