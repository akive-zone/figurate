import axios from 'axios';
import { readStorage, writeStorage } from './base';

const deviceIdStorageKey = 'device_id';
const apiTokenStorageKey = 'api_token';

export const persistChatBootstrapHeaders = (response) => {
    if (!response || !response.headers) {
        return;
    }

    const deviceId = response.headers['x-device-id'];
    const apiToken = response.headers['x-api-token'];

    if (typeof deviceId === 'string' && deviceId !== '') {
        writeStorage(deviceIdStorageKey, deviceId);
    }

    if (typeof apiToken === 'string' && apiToken !== '') {
        writeStorage(apiTokenStorageKey, apiToken);
    }
};

export const chatAuthHeaders = () => {
    const deviceId = readStorage(deviceIdStorageKey);
    const token = readStorage(apiTokenStorageKey);
    const headers = {};

    if (deviceId) {
        headers['X-Device-Id'] = deviceId;
    }

    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    return headers;
};

const chatApiBaseUrl = (runtime = {}) => {
    if (runtime.is_native !== true) {
        return '';
    }

    return (import.meta.env.VITE_SERVER_BASE_URL ?? '').toString().trim().replace(/\/$/, '');
};

export const chatApiUrl = (path, runtime = {}) => {
    const baseUrl = chatApiBaseUrl(runtime);

    if (!baseUrl) {
        return path;
    }

    return `${baseUrl}${path}`;
};

const resolveChatRoute = (runtime = {}, routeKey, fallbackPath) => {
    const configuredRoute = runtime?.routes?.[routeKey];

    if (typeof configuredRoute === 'string' && configuredRoute.trim() !== '') {
        return configuredRoute;
    }

    return fallbackPath;
};

export const sendConversationMessage = async (payload, runtime = {}, options = {}) => {
    const storeUrl = resolveChatRoute(runtime, 'conversation_store', '/api/form');
    const headers = {
        ...chatAuthHeaders(),
    };
    const idempotencyKey = (options.idempotencyKey ?? '').toString().trim();

    if (idempotencyKey !== '') {
        headers['X-Idempotency-Key'] = idempotencyKey;
    }

    try {
        const response = await axios.post(chatApiUrl(storeUrl, runtime), payload, {
            headers,
        });
        persistChatBootstrapHeaders(response);

        return response;
    } catch (error) {
        persistChatBootstrapHeaders(error.response);
        throw error;
    }
};

export const fetchConversations = async (runtime = {}, query = {}) => {
    const indexUrl = resolveChatRoute(runtime, 'conversations', '/api/spaces');

    try {
        const response = await axios.get(chatApiUrl(indexUrl, runtime), {
            params: query,
            headers: chatAuthHeaders(),
        });
        persistChatBootstrapHeaders(response);

        return response.data;
    } catch (error) {
        persistChatBootstrapHeaders(error.response);
        throw error;
    }
};

export const fetchConversationThreads = async (conversationId, runtime = {}, query = {}) => {
    const template = (runtime?.routes?.conversation_threads_template ?? '').toString().trim();
    const path = template !== ''
        ? template.replace('__CONVERSATION__', conversationId)
        : `/api/spaces/${conversationId}/threads`;

    try {
        const response = await axios.get(chatApiUrl(path, runtime), {
            params: query,
            headers: chatAuthHeaders(),
        });
        persistChatBootstrapHeaders(response);

        return response.data;
    } catch (error) {
        persistChatBootstrapHeaders(error.response);
        throw error;
    }
};

export const createConversationThread = async (conversationId, payload, runtime = {}) => {
    const template = (runtime?.routes?.conversation_threads_template ?? '').toString().trim();
    const path = template !== ''
        ? template.replace('__CONVERSATION__', conversationId)
        : `/api/spaces/${conversationId}/threads`;

    try {
        const response = await axios.post(chatApiUrl(path, runtime), payload, {
            headers: chatAuthHeaders(),
        });
        persistChatBootstrapHeaders(response);

        return response.data;
    } catch (error) {
        persistChatBootstrapHeaders(error.response);
        throw error;
    }
};

export const fetchConversationMessages = async (conversationId, runtime = {}) => {
    const template = (runtime?.routes?.conversation_show_template ?? '').toString().trim();
    const path = template !== ''
        ? template.replace('__CONVERSATION__', conversationId)
        : `/api/threads/${conversationId}`;

    try {
        const response = await axios.get(chatApiUrl(path, runtime), {
            headers: chatAuthHeaders(),
        });
        persistChatBootstrapHeaders(response);

        return response.data;
    } catch (error) {
        persistChatBootstrapHeaders(error.response);
        throw error;
    }
};

export const fetchConversationMessageTurns = async (conversationId, messageId, runtime = {}) => {
    const template = (runtime?.routes?.conversation_message_turns_template ?? '').toString().trim();
    const normalizedMessageId = (messageId ?? '').toString().trim();
    const path = template !== ''
        ? template.replace('__CONVERSATION__', conversationId).replace('__MESSAGE__', normalizedMessageId)
        : `/api/threads/${conversationId}/posts/${normalizedMessageId}/turns`;

    try {
        const response = await axios.get(chatApiUrl(path, runtime), {
            headers: chatAuthHeaders(),
        });
        persistChatBootstrapHeaders(response);

        return response.data;
    } catch (error) {
        persistChatBootstrapHeaders(error.response);
        throw error;
    }
};

export const fetchConversationPosts = async (conversationId, runtime = {}) => {
    const template = (runtime?.routes?.conversation_posts_template ?? '').toString().trim();
    const path = template !== ''
        ? template.replace('__CONVERSATION__', conversationId)
        : `/api/spaces/${conversationId}/posts`;

    try {
        const response = await axios.get(chatApiUrl(path, runtime), {
            headers: chatAuthHeaders(),
        });
        persistChatBootstrapHeaders(response);

        return response.data;
    } catch (error) {
        persistChatBootstrapHeaders(error.response);
        throw error;
    }
};
