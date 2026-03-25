import { DEFAULT_A2UI_CLIENT_CONFIG } from '../constants';

export const buildA2uiActionRequest = ({
    space,
    thread,
    action,
    clientConfig = DEFAULT_A2UI_CLIENT_CONFIG,
}) => {
    return {
        space,
        thread,
        content: {
            text: null,
            attachments: [],
            actions: [action],
            errors: [],
        },
        extra: {
            a2ui: {
                config: {
                    a2uiClientDataModel: clientConfig?.a2uiClientDataModel ?? DEFAULT_A2UI_CLIENT_CONFIG.a2uiClientDataModel,
                    a2uiClientCapabilities: clientConfig?.a2uiClientCapabilities ?? DEFAULT_A2UI_CLIENT_CONFIG.a2uiClientCapabilities,
                },
            },
        },
    };
};
