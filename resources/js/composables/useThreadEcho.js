import { computed, onBeforeUnmount, ref, unref, watch } from 'vue';

const parsePayload = (event) => {
    if (!event || typeof event !== 'object') {
        return {};
    }

    if (typeof event.data === 'string' && event.data.trim() !== '') {
        try {
            return JSON.parse(event.data);
        } catch {
            return event;
        }
    }

    return event;
};

export const useThreadEcho = ({
    threadId,
    enabled = true,
    onReplyStarted,
    onReplyCompleted,
    onReplyFailed,
    onStreamStart,
    onTextDelta,
    onStreamEnd,
    onError,
} = {}) => {
    const subscribedThreadId = ref('');
    const subscriptionEpoch = ref(0);
    const isEnabled = computed(() => Boolean(unref(enabled)));

    const leave = () => {
        if (subscribedThreadId.value !== '' && window.Echo && typeof window.Echo.leave === 'function') {
            window.Echo.leave(`threads.${subscribedThreadId.value}`);
        }

        subscribedThreadId.value = '';
        subscriptionEpoch.value += 1;
    };

    const canDispatch = (threadSnapshot, epochSnapshot) => {
        return (
            subscribedThreadId.value !== ''
            && subscribedThreadId.value === threadSnapshot
            && subscriptionEpoch.value === epochSnapshot
            && (unref(threadId) ?? '').toString().trim() === threadSnapshot
        );
    };

    const subscribe = (threadSnapshot) => {
        if (
            threadSnapshot === ''
            || !window.Echo
            || typeof window.Echo.private !== 'function'
        ) {
            return;
        }

        leave();

        subscribedThreadId.value = threadSnapshot;
        subscriptionEpoch.value += 1;
        const epochSnapshot = subscriptionEpoch.value;
        const channel = window.Echo.private(`threads.${threadSnapshot}`);
        const listenFor = (names, callback) => {
            names.forEach((name) => {
                channel.listen(name, (event) => {
                    if (!canDispatch(threadSnapshot, epochSnapshot)) {
                        return;
                    }

                    callback?.(event, parsePayload(event));
                });
            });
        };

        listenFor(['.agent.reply.started'], onReplyStarted);
        listenFor(['.agent.reply.completed'], onReplyCompleted);
        listenFor(['.agent.reply.failed'], onReplyFailed);
        listenFor(['.stream_start', 'stream_start'], onStreamStart);
        listenFor(['.text_delta', 'text_delta'], onTextDelta);
        listenFor(['.stream_end', 'stream_end'], onStreamEnd);
        listenFor(['.error', 'error'], onError);
    };

    watch(
        [() => (unref(threadId) ?? '').toString().trim(), isEnabled],
        ([nextThreadId, nextEnabled]) => {
            if (!nextEnabled || nextThreadId === '') {
                leave();
                return;
            }

            subscribe(nextThreadId);
        },
        { immediate: true },
    );

    onBeforeUnmount(() => {
        leave();
    });

    return {
        subscribedThreadId,
        leave,
    };
};
