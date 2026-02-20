import axios from 'axios';

const deviceIdStorageKey = 'signal.device_id';
const apiTokenStorageKey = 'signal.api_token';

const readStorage = (key) => {
    if (typeof window === 'undefined') {
        return '';
    }

    return window.localStorage.getItem(key) ?? '';
};

const writeStorage = (key, value) => {
    if (typeof window === 'undefined' || !value) {
        return;
    }

    window.localStorage.setItem(key, value);
};

export const persistSignalBootstrapHeaders = (response) => {
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

export const signalAuthHeaders = () => {
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

const signalApiBaseUrl = (runtime = {}) => {
    if (runtime.is_native !== true) {
        return '';
    }

    return (import.meta.env.VITE_SERVER_BASE_URL ?? '').toString().trim().replace(/\/$/, '');
};

export const signalApiUrl = (path, runtime = {}) => {
    const baseUrl = signalApiBaseUrl(runtime);

    if (!baseUrl) {
        return path;
    }

    return `${baseUrl}${path}`;
};

const resolveSignalRoute = (runtime = {}, routeKey, fallbackPath) => {
    const configuredRoute = runtime?.signal_routes?.[routeKey];

    if (typeof configuredRoute === 'string' && configuredRoute.trim() !== '') {
        return configuredRoute;
    }

    return fallbackPath;
};

export const sendSignalChatMessage = async (payload, runtime = {}, options = {}) => {
    const chatsStoreUrl = resolveSignalRoute(runtime, 'chats_store', '/api/chats');
    const headers = {
        ...signalAuthHeaders(),
    };
    const idempotencyKey = (options.idempotencyKey ?? '').toString().trim();

    if (idempotencyKey !== '') {
        headers['X-Idempotency-Key'] = idempotencyKey;
    }

    try {
        const response = await axios.post(signalApiUrl(chatsStoreUrl, runtime), payload, {
            headers,
        });
        persistSignalBootstrapHeaders(response);

        return response;
    } catch (error) {
        persistSignalBootstrapHeaders(error.response);
        throw error;
    }
};

export const fetchSignalChats = async (runtime = {}, query = {}) => {
    const chatsIndexUrl = resolveSignalRoute(runtime, 'chats', '/api/chats');

    try {
        const response = await axios.get(signalApiUrl(chatsIndexUrl, runtime), {
            params: query,
            headers: signalAuthHeaders(),
        });
        persistSignalBootstrapHeaders(response);

        return response.data;
    } catch (error) {
        persistSignalBootstrapHeaders(error.response);
        throw error;
    }
};

export const fetchSignalChatThreads = async (chatId, runtime = {}, query = {}) => {
    const template = (runtime?.signal_routes?.chats_threads_template ?? '').toString().trim();
    const path = template !== ''
        ? template.replace('__CHAT__', chatId)
        : `/api/chats/${chatId}/threads`;

    try {
        const response = await axios.get(signalApiUrl(path, runtime), {
            params: query,
            headers: signalAuthHeaders(),
        });
        persistSignalBootstrapHeaders(response);

        return response.data;
    } catch (error) {
        persistSignalBootstrapHeaders(error.response);
        throw error;
    }
};

export const fetchSignalThreadMessages = async (chatId, runtime = {}) => {
    const template = (runtime?.signal_routes?.chats_show_template ?? '').toString().trim();
    const path = template !== ''
        ? template.replace('__CHAT__', chatId)
        : `/api/chats/${chatId}`;

    try {
        const response = await axios.get(signalApiUrl(path, runtime), {
            headers: signalAuthHeaders(),
        });
        persistSignalBootstrapHeaders(response);

        return response.data;
    } catch (error) {
        persistSignalBootstrapHeaders(error.response);
        throw error;
    }
};

export const fetchSignalChannelPosts = async (channelId, runtime = {}) => {
    const template = (runtime?.signal_routes?.channel_posts_template ?? '').toString().trim();
    const path = template !== ''
        ? template.replace('__CHANNEL__', channelId)
        : `/api/channels/${channelId}/posts`;

    try {
        const response = await axios.get(signalApiUrl(path, runtime), {
            headers: signalAuthHeaders(),
        });
        persistSignalBootstrapHeaders(response);

        return response.data;
    } catch (error) {
        persistSignalBootstrapHeaders(error.response);
        throw error;
    }
};
