import { computed, reactive, unref } from 'vue';
import { A2UI_PROTOCOL } from '../constants';
import { createA2uiFieldRegistry, resolveA2uiFieldComponent } from '../registry/fieldRegistry';
import { useA2uiRuntime } from './useA2uiRuntime';

export const useA2uiSurface = (payload, options = {}) => {
    const protocol = computed(() => {
        const resolved = unref(options.protocol ?? A2UI_PROTOCOL);

        return (resolved ?? A2UI_PROTOCOL).toString().trim() || A2UI_PROTOCOL;
    });
    const defaultSubmitLabel = computed(() => {
        const resolved = unref(options.defaultSubmitLabel ?? 'Submit');

        return (resolved ?? 'Submit').toString();
    });

    const fieldRegistry = computed(() => {
        const overrides = unref(options.fieldRegistry ?? {});

        return createA2uiFieldRegistry(overrides);
    });
    const runtime = useA2uiRuntime(payload);
    const surface = computed(() => runtime.activeSurface.value ?? {});

    const fields = computed(() => {
        const direct = Array.isArray(surface.value?.fields) ? surface.value.fields : [];

        if (direct.length > 0) {
            return direct;
        }

        return Array.isArray(surface.value?.schema?.fields) ? surface.value.schema.fields : [];
    });

    const actions = computed(() => {
        const configured = Array.isArray(surface.value?.actions) ? surface.value.actions : [];

        if (configured.length > 0) {
            return configured;
        }

        return [{
            id: 'submit',
            type: 'submit',
            label: defaultSubmitLabel.value,
        }];
    });

    const form = reactive({});

    const fieldKey = (field, index) => {
        const candidates = [
            field?.key,
            field?.name,
            field?.id,
        ];

        for (const candidate of candidates) {
            if (typeof candidate === 'string' && candidate.trim() !== '') {
                return candidate.trim();
            }
        }

        return `field_${index + 1}`;
    };

    const labelFor = (field, index) => {
        const candidates = [
            field?.label,
            field?.title,
            field?.name,
        ];

        for (const candidate of candidates) {
            if (typeof candidate === 'string' && candidate.trim() !== '') {
                return candidate.trim();
            }
        }

        return `Field ${index + 1}`;
    };

    const resolveFieldComponent = (field) => {
        return resolveA2uiFieldComponent(fieldRegistry.value, field);
    };

    const buildActionPayload = (action) => {
        const actionName = (action?.name ?? action?.type ?? action?.id ?? 'submit').toString().trim() || 'submit';
        const actionId = (action?.id ?? actionName).toString().trim();
        const surfaceId = (surface.value?.id ?? payload.value?.surfaceId ?? '').toString().trim();
        const sourceComponentId = (action?.sourceComponentId ?? '').toString().trim();

        return {
            protocol: protocol.value,
            name: actionName,
            id: actionId !== '' ? actionId : null,
            surfaceId: surfaceId !== '' ? surfaceId : null,
            sourceComponentId: sourceComponentId !== '' ? sourceComponentId : null,
            timestamp: new Date().toISOString(),
            context: {
                surfaceTitle: (surface.value?.title ?? '').toString().trim() || null,
            },
            values: { ...form },
        };
    };

    return {
        surface,
        fields,
        actions,
        form,
        fieldKey,
        labelFor,
        resolveFieldComponent,
        buildActionPayload,
        runtime,
    };
};
