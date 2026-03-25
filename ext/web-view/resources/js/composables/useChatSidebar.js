import { onMounted, ref, watch } from 'vue';
import { chatDataService } from '../services/chatDataService';

export const useChatSidebar = (runtime, initialSpaces) => {
    const sidebarSpaces = ref(Array.isArray(initialSpaces?.value) ? initialSpaces.value : []);
    const loadingMoreThreadsByChat = ref({});

    watch(
        initialSpaces,
        (value) => {
            sidebarSpaces.value = Array.isArray(value) ? value : [];
        },
        { deep: true },
    );

    const refreshSidebarConversations = async () => {
        try {
            const payloadResponse = await chatDataService.listConversations(runtime.value);
            const payload = payloadResponse?.data;
            if (Array.isArray(payload)) {
                sidebarSpaces.value = payload;
            }
        } catch {
            // Keep existing sidebar state when request fails.
        }
    };

    const canLoadMoreThreads = (chat) => {
        const cursor = (chat?.threads_meta?.next_cursor ?? '').toString().trim();

        return cursor !== '';
    };

    const isLoadingMoreThreads = (chatId) => loadingMoreThreadsByChat.value[chatId] === true;

    const mergeThreads = (existingThreads, incomingThreads) => {
        const existing = Array.isArray(existingThreads) ? existingThreads : [];
        const incoming = Array.isArray(incomingThreads) ? incomingThreads : [];
        const seen = new Set();
        const merged = [];

        [...existing, ...incoming].forEach((thread) => {
            const id = (thread?.id ?? '').toString().trim();
            if (id === '' || seen.has(id)) {
                return;
            }

            seen.add(id);
            merged.push(thread);
        });

        return merged;
    };

    const loadMoreThreads = async (chat) => {
        const chatId = (chat?.id ?? '').toString().trim();
        const cursor = (chat?.threads_meta?.next_cursor ?? '').toString().trim();

        if (chatId === '' || cursor === '' || isLoadingMoreThreads(chatId)) {
            return;
        }

        loadingMoreThreadsByChat.value = {
            ...loadingMoreThreadsByChat.value,
            [chatId]: true,
        };

        try {
            const payload = await chatDataService.listConversationThreads(chatId, runtime.value, { cursor });
            const nextThreads = Array.isArray(payload?.data) ? payload.data : [];
            const nextMeta = payload?.meta ?? {};

            sidebarSpaces.value = sidebarSpaces.value.map((item) => {
                if (item?.id !== chatId) {
                    return item;
                }

                return {
                    ...item,
                    threads: mergeThreads(item.threads, nextThreads),
                    threads_meta: {
                        next_cursor: nextMeta.next_cursor ?? null,
                        prev_cursor: nextMeta.prev_cursor ?? null,
                        per_page: nextMeta.per_page ?? item?.threads_meta?.per_page ?? 5,
                    },
                };
            });
        } catch {
            // Keep existing thread list when request fails.
        } finally {
            loadingMoreThreadsByChat.value = {
                ...loadingMoreThreadsByChat.value,
                [chatId]: false,
            };
        }
    };

    onMounted(() => {
        refreshSidebarConversations();
    });

    return {
        sidebarSpaces,
        refreshSidebarConversations,
        canLoadMoreThreads,
        isLoadingMoreThreads,
        loadMoreThreads,
    };
};
