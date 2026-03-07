<?php

return [
    'inbound' => [
        'enabled' => (bool) env('A2A_INBOUND_ENABLED', env('A2A_ENABLED', true)),
        'agent' => [
            'id' => env('A2A_AGENT_ID', 'figurate-server-agent'),
            'name' => env('A2A_AGENT_NAME', env('APP_NAME', 'Figurate Agent')),
            'description' => env('A2A_AGENT_DESCRIPTION', 'Agent2Agent endpoint for Figurate chat orchestration.'),
            'version' => env('A2A_AGENT_VERSION', '0.1.0'),
        ],
        'protocol' => [
            'version' => env('A2A_PROTOCOL_VERSION', 'latest'),
        ],
        'capabilities' => [
            'streaming' => true,
            'push_notifications' => (bool) env('A2A_PUSH_NOTIFICATIONS_ENABLED', true),
            'history' => true,
        ],
        'a2ui' => [
            'enabled' => (bool) env('A2A_A2UI_ENABLED', true),
            'uri' => env('A2A_A2UI_URI', 'https://a2ui.org/specification/v0.8-a2ui/'),
            'required' => (bool) env('A2A_A2UI_REQUIRED', false),
            'catalogs' => [
                'supported_ids' => [],
                'accepts_inline' => true,
                'items' => [
                    // 'industry.skills' => [
                    //     'id' => 'industry.skills',
                    //     'title' => 'Industry Skills',
                    //     'entries' => [
                    //         ['id' => 'plumbing', 'label' => 'Plumbing'],
                    //         ['id' => 'electrical', 'label' => 'Electrical'],
                    //     ],
                    // ],
                ],
            ],
        ],
        'auth' => [
            'method_abilities' => [
                'message/send' => 'a2a:message.send',
                'message/stream' => 'a2a:message.send',
                'tasks/get' => 'a2a:task.read',
                'tasks/list' => 'a2a:task.read',
                'tasks/cancel' => 'a2a:task.cancel',
                'tasks/resubscribe' => 'a2a:task.read',
                'tasks/pushNotificationConfig/set' => 'a2a:push.config.manage',
                'tasks/pushNotificationConfig/create' => 'a2a:push.config.manage',
                'tasks/pushNotificationConfig/get' => 'a2a:push.config.manage',
                'tasks/pushNotificationConfig/list' => 'a2a:push.config.manage',
                'tasks/pushNotificationConfig/delete' => 'a2a:push.config.manage',
            ],
        ],
        'push_notifications' => [
            'notification_method' => env('A2A_PUSH_NOTIFICATION_METHOD', 'SendTaskStreamingNotification'),
            'default_headers' => [],
            'default_state_filter' => [],
        ],
    ],
    'outbound' => [
        'enabled' => (bool) env('A2A_OUTBOUND_ENABLED', true),
        'default_timeout_seconds' => (int) env('A2A_OUTBOUND_TIMEOUT_SECONDS', 15),
        'push_notifications' => [
            'enabled' => (bool) env('A2A_OUTBOUND_PUSH_NOTIFICATIONS_ENABLED', true),
            'register_on_delegate' => (bool) env('A2A_OUTBOUND_PUSH_REGISTER_ON_DELEGATE', true),
            'callback_url' => env('A2A_OUTBOUND_PUSH_CALLBACK_URL'),
            'token' => env('A2A_OUTBOUND_PUSH_TOKEN'),
            'state_filter' => ['completed', 'failed', 'canceled'],
        ],
        'agents' => [
            // 'planner' => [
            //     'label' => 'Planning Agent',
            //     'endpoint' => env('A2A_REMOTE_PLANNER_ENDPOINT'),
            //     'auth_type' => 'bearer',
            //     'token' => env('A2A_REMOTE_PLANNER_TOKEN'),
            //     'headers' => [],
            //     'allowed_methods' => [
            //         'message/send',
            //         'SendMessage',
            //         'tasks/get',
            //         'GetTask',
            //         'tasks/list',
            //         'ListTasks',
            //         'tasks/cancel',
            //         'CancelTask',
            //         'tasks/resubscribe',
            //         'TaskResubscription',
            //         'CreateTaskPushNotificationConfig',
            //         'tasks/pushNotificationConfig/set',
            //         'tasks/pushNotificationConfig/get',
            //         'tasks/pushNotificationConfig/list',
            //         'tasks/pushNotificationConfig/delete',
            //         'message/stream',
            //         'SendStreamingMessage',
            //     ],
            // ],
        ],
    ],
];
