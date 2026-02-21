import '../css/app.css';
import './echo';
import { createInertiaApp } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';

const persistDeviceIdentity = (pageProps = {}) => {
    if (typeof window === 'undefined') {
        return;
    }

    const authUser = pageProps?.auth?.user ?? null;

    if (!authUser || authUser.type !== 'device') {
        return;
    }

    const deviceId = (authUser.device_identifier ?? '').toString().trim();
    if (deviceId !== '') {
        window.localStorage.setItem('device_id', deviceId);
    }
};

createInertiaApp({
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        persistDeviceIdentity(props?.initialPage?.props ?? {});

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#0f766e',
    },
});

window.router = router;
router.on('navigate', (event) => {
    persistDeviceIdentity(event.detail.page.props ?? {});
});
