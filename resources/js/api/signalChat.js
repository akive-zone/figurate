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

export const sendSignalChatMessage = async (payload, runtime = {}) => {
    try {
        const response = await axios.post(signalApiUrl('/api/chat', runtime), payload, {
            headers: signalAuthHeaders(),
        });
        persistSignalBootstrapHeaders(response);

        return response;
    } catch (error) {
        persistSignalBootstrapHeaders(error.response);
        throw error;
    }
};
