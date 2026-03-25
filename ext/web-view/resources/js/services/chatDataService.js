import {
    createConversationThread,
    fetchConversationMessageTurns,
    fetchConversationMessages,
    fetchConversationPosts,
    fetchConversations,
    fetchConversationThreads,
    sendConversationMessage,
} from '@web-view/api';

export const chatDataService = {
    async listConversations(runtime, query = {}) {
        return fetchConversations(runtime, query);
    },

    async listConversationThreads(conversationId, runtime, query = {}) {
        return fetchConversationThreads(conversationId, runtime, query);
    },

    async listConversationMessages(conversationId, runtime) {
        return fetchConversationMessages(conversationId, runtime);
    },

    async listConversationPosts(conversationId, runtime) {
        return fetchConversationPosts(conversationId, runtime);
    },

    async listConversationMessageTurns(conversationId, messageId, runtime) {
        return fetchConversationMessageTurns(conversationId, messageId, runtime);
    },

    async sendMessage(payload, runtime, options = {}) {
        return sendConversationMessage(payload, runtime, options);
    },

    async createThread(conversationId, payload, runtime) {
        return createConversationThread(conversationId, payload, runtime);
    },
};
