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

export const sendChatChatMessage = async (payload, runtime = {}, options = {}) => {
    const chatsStoreUrl = resolveChatRoute(runtime, 'chats_store', '/api/chats');
    const headers = {
        ...chatAuthHeaders(),
    };
    const idempotencyKey = (options.idempotencyKey ?? '').toString().trim();

    if (idempotencyKey !== '') {
        headers['X-Idempotency-Key'] = idempotencyKey;
    }

    try {
        const response = await axios.post(chatApiUrl(chatsStoreUrl, runtime), payload, {
            headers,
        });
        persistChatBootstrapHeaders(response);

        return response;
    } catch (error) {
        persistChatBootstrapHeaders(error.response);
        throw error;
    }
};

export const fetchChatChats = async (runtime = {}, query = {}) => {
    const chatsIndexUrl = resolveChatRoute(runtime, 'chats', '/api/chats');

    try {
        const response = await axios.get(chatApiUrl(chatsIndexUrl, runtime), {
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

export const fetchChatChatThreads = async (chatId, runtime = {}, query = {}) => {
    const template = (runtime?.routes?.chats_threads_template ?? '').toString().trim();
    const path = template !== ''
        ? template.replace('__CHAT__', chatId)
        : `/api/chats/${chatId}/threads`;

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

export const fetchChatThreadMessages = async (chatId, runtime = {}) => {
    const template = (runtime?.routes?.chats_show_template ?? '').toString().trim();
    const path = template !== ''
        ? template.replace('__CHAT__', chatId)
        : `/api/chats/${chatId}`;

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

export const fetchChatMessageTurns = async (chatId, messageId, runtime = {}) => {
    const template = (runtime?.routes?.chats_message_turns_template ?? '').toString().trim();
    const normalizedMessageId = (messageId ?? '').toString().trim();
    const path = template !== ''
        ? template.replace('__CHAT__', chatId).replace('__MESSAGE__', normalizedMessageId)
        : `/api/chats/${chatId}/messages/${normalizedMessageId}/turns`;

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

export const fetchChatChannelPosts = async (channelId, runtime = {}) => {
    const template = (runtime?.routes?.chat_posts_template ?? '').toString().trim();
    const path = template !== ''
        ? template.replace('__CHAT__', channelId)
        : `/api/chats/${channelId}/posts`;

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
