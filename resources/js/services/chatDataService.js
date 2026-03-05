import {
    createChatThread,
    fetchChatChannelPosts,
    fetchChatChatThreads,
    fetchChatChats,
    fetchChatMessageTurns,
    fetchChatThreadMessages,
    sendChatChatMessage,
} from '../api';

export const chatDataService = {
    async listChats(runtime, query = {}) {
        return fetchChatChats(runtime, query);
    },

    async listChatThreads(chatId, runtime, query = {}) {
        return fetchChatChatThreads(chatId, runtime, query);
    },

    async listThreadMessages(chatId, runtime) {
        return fetchChatThreadMessages(chatId, runtime);
    },

    async listChannelPosts(chatId, runtime) {
        return fetchChatChannelPosts(chatId, runtime);
    },

    async listMessageTurns(chatId, messageId, runtime) {
        return fetchChatMessageTurns(chatId, messageId, runtime);
    },

    async sendMessage(payload, runtime, options = {}) {
        return sendChatChatMessage(payload, runtime, options);
    },

    async createThread(chatId, payload, runtime) {
        return createChatThread(chatId, payload, runtime);
    },
};

