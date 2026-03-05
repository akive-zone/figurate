import { router } from '@inertiajs/vue3';

export const inertiaNavigationService = {
    visitPreservingState(url, options = {}) {
        return router.visit(url, {
            preserveScroll: true,
            preserveState: true,
            ...options,
        });
    },

    post(url, data, options = {}) {
        return router.post(url, data, options);
    },
};

