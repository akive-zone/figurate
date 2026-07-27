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
        : `/api/spaces/${conversationId}/nodes`;

    try {
        const response = await axios.get(chatApiUrl(path, runtime), {
            params: {
                ...query,
                type: 'thread',
            },
            headers: chatAuthHeaders(),
        });
        persistChatBootstrapHeaders(response);

        const payload = response.data ?? {};

        return {
            ...payload,
            data: Array.isArray(payload.data)
                ? payload.data
                    .filter((node) => node?.type === 'thread')
                    .map((node) => ({
                        id: node.id,
                        ...(node.attributes ?? {}),
                        created_at: node.created_at ?? null,
                    }))
                : [],
        };
    } catch (error) {
        persistChatBootstrapHeaders(error.response);
        throw error;
    }
};

export const createConversationThread = async (conversationId, payload, runtime = {}) => {
    const configuredPath = (runtime?.routes?.node_store ?? '').toString().trim();
    const path = configuredPath !== '' ? configuredPath : '/api/nodes';
    const attributes = {
        title: payload?.title,
        purpose: payload?.purpose,
        phase: payload?.phase,
        status: payload?.status,
    };

    try {
        const response = await axios.post(chatApiUrl(path, runtime), {
            type: 'thread',
            parent: {
                type: 'space',
                id: conversationId,
            },
            attributes: Object.fromEntries(
                Object.entries(attributes).filter(([, value]) => value !== undefined),
            ),
        }, {
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

export const fetchFormTurns = async (invocationId, runtime = {}) => {
    const template = (runtime?.routes?.form_turns_template ?? '').toString().trim();
    const normalizedInvocationId = (invocationId ?? '').toString().trim();
    const path = template !== ''
        ? template.replace('__INVOCATION__', normalizedInvocationId)
        : `/api/form/${normalizedInvocationId}/turns`;

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

export const fetchPost = async (postId, runtime = {}) => {
    const normalizedPostId = (postId ?? '').toString().trim();

    try {
        const response = await axios.get(chatApiUrl(`/api/posts/${normalizedPostId}`, runtime), {
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
        : `/api/spaces/${conversationId}/nodes`;

    try {
        const response = await axios.get(chatApiUrl(path, runtime), {
            params: {
                type: 'post',
            },
            headers: chatAuthHeaders(),
        });
        persistChatBootstrapHeaders(response);

        const payload = response.data ?? {};

        return {
            ...payload,
            data: Array.isArray(payload.data)
                ? payload.data
                    .filter((node) => node?.type === 'post')
                    .map((node) => ({
                        id: node.id,
                        type: node.attributes?.post_type,
                        kind: node.attributes?.post_type,
                        status: node.attributes?.status,
                        text: node.attributes?.text,
                        payload: node.attributes?.payload ?? {},
                        meta: node.attributes?.meta ?? {},
                        occurred_at: node.attributes?.occurred_at ?? null,
                        created_at: node.created_at ?? null,
                    }))
                : [],
        };
    } catch (error) {
        persistChatBootstrapHeaders(error.response);
        throw error;
    }
};
