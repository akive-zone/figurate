import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { chatApiUrl, chatAuthHeaders } from './api';

window.Pusher = Pusher;

const inferChatRuntime = () => {
    if (typeof document === 'undefined') {
        return {};
    }

    const appElement = document.getElementById('app');
    const payload = appElement?.dataset?.page;

    if (typeof payload !== 'string' || payload.trim() === '') {
        return {};
    }

    try {
        const parsed = JSON.parse(payload);

        return parsed?.props?.runtime ?? {};
    } catch {
        return {};
    }
};

const runtime = inferChatRuntime();
const authEndpoint = chatApiUrl('/api/broadcasting/auth', runtime);
const reverbKey = (import.meta.env.VITE_REVERB_APP_KEY ?? '').toString().trim();

if (reverbKey !== '') {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        authorizer: (channel) => ({
            authorize: (socketId, callback) => {
                axios
                    .post(authEndpoint, {
                        socket_id: socketId,
                        channel_name: channel.name,
                    }, {
                        headers: chatAuthHeaders(),
                    })
                    .then((response) => {
                        callback(false, response.data);
                    })
                    .catch((error) => {
                        callback(true, error);
                    });
            },
        }),
    });
}
