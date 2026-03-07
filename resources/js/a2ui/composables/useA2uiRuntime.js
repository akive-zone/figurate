import { computed, ref, watch } from 'vue';

const trimmed = (value) => (typeof value === 'string' ? value.trim() : '');

const mergeDataModel = (base, patch) => {
    if (!patch || typeof patch !== 'object') {
        return base;
    }

    if (!base || typeof base !== 'object') {
        return { ...patch };
    }

    return {
        ...base,
        ...patch,
    };
};

export const useA2uiRuntime = (payloadRef) => {
    const state = ref('idle');
    const activeSurface = ref(null);
    const dataModel = ref({});
    const lastSurfaceId = ref(null);

    const applyPayload = (payload) => {
        if (!payload || typeof payload !== 'object') {
            return;
        }

        const beginRendering = payload.beginRendering && typeof payload.beginRendering === 'object'
            ? payload.beginRendering
            : null;
        const surfaceUpdate = payload.surfaceUpdate && typeof payload.surfaceUpdate === 'object'
            ? payload.surfaceUpdate
            : null;
        const deleteSurface = trimmed(payload.deleteSurface);
        const dataModelUpdate = payload.dataModelUpdate;

        if (beginRendering) {
            const surfaceId = trimmed(beginRendering.id || beginRendering.surfaceId);
            if (surfaceId !== '') {
                beginRendering.id = surfaceId;
                lastSurfaceId.value = surfaceId;
            }

            activeSurface.value = beginRendering;
            state.value = 'rendered';
        }

        if (surfaceUpdate) {
            const updateSurfaceId = trimmed(surfaceUpdate.id || surfaceUpdate.surfaceId);
            const currentSurfaceId = trimmed(activeSurface.value?.id || activeSurface.value?.surfaceId);
            const canApply = updateSurfaceId === '' || currentSurfaceId === '' || updateSurfaceId === currentSurfaceId;

            if (canApply) {
                const nextSurface = {
                    ...(activeSurface.value && typeof activeSurface.value === 'object' ? activeSurface.value : {}),
                    ...surfaceUpdate,
                };

                if (updateSurfaceId !== '') {
                    nextSurface.id = updateSurfaceId;
                    lastSurfaceId.value = updateSurfaceId;
                }

                activeSurface.value = nextSurface;
                state.value = 'updated';
            }
        }

        if (typeof dataModelUpdate === 'string') {
            dataModel.value = {
                ...dataModel.value,
                value: dataModelUpdate,
            };
        } else if (dataModelUpdate && typeof dataModelUpdate === 'object') {
            dataModel.value = mergeDataModel(dataModel.value, dataModelUpdate);
        }

        if (deleteSurface !== '') {
            const currentSurfaceId = trimmed(activeSurface.value?.id || activeSurface.value?.surfaceId);

            if (currentSurfaceId === '' || currentSurfaceId === deleteSurface) {
                activeSurface.value = null;
                lastSurfaceId.value = deleteSurface;
                state.value = 'deleted';
            }
        }

        if (!beginRendering && !surfaceUpdate && !deleteSurface) {
            if (payload && typeof payload === 'object') {
                const asSurface = payload;
                const surfaceId = trimmed(asSurface.id || asSurface.surfaceId);

                activeSurface.value = asSurface;

                if (surfaceId !== '') {
                    lastSurfaceId.value = surfaceId;
                }

                state.value = 'rendered';
            }
        }
    };

    watch(
        payloadRef,
        (payload) => {
            applyPayload(payload);
        },
        { immediate: true, deep: true }
    );

    const runtimeSnapshot = computed(() => ({
        state: state.value,
        surfaceId: lastSurfaceId.value,
        dataModel: dataModel.value,
    }));

    return {
        state,
        dataModel,
        activeSurface,
        runtimeSnapshot,
        applyPayload,
    };
};
