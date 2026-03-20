<?php

return [
    'outbound' => [
        'enabled' => (bool) env('ACP_OUTBOUND_ENABLED', false),
        'default_timeout_seconds' => (int) env('ACP_OUTBOUND_TIMEOUT_SECONDS', 20),
        'gateway' => [
            'endpoint' => env('ACP_GATEWAY_ENDPOINT', 'http://127.0.0.1:4319/rpc'),
            'token' => env('ACP_GATEWAY_TOKEN'),
        ],
        'client' => [
            'id' => env('ACP_OUTBOUND_CLIENT_ID', 'figurate'),
            'name' => env('ACP_OUTBOUND_CLIENT_NAME', env('APP_NAME', 'Figurate')),
            'version' => env('ACP_OUTBOUND_CLIENT_VERSION', '0.1.0'),
            'capabilities' => [],
        ],
        'agents' => [
            'opencode' => [
                'label' => 'OpenCode ACP via Gateway',
                'endpoint' => env('ACP_GATEWAY_ENDPOINT', 'http://127.0.0.1:4319/rpc'),
                'transport' => 'acp-gateway-http',
                'gateway_agent' => env('ACP_OUTBOUND_OPENCODE_AGENT', 'opencode'),
                'auth_type' => env('ACP_GATEWAY_TOKEN') ? 'bearer' : 'none',
                'token' => env('ACP_GATEWAY_TOKEN'),
                'headers' => [],
                'allowed_methods' => [
                    'initialize',
                    'authenticate',
                    'session/new',
                    'session/load',
                    'session/prompt',
                    'session/cancel',
                ],
                'initialize_payload' => [
                    'protocolVersion' => 1,
                ],
                'authenticate_payload' => [],
                'session' => [
                    'reuse' => 'thread',
                    'create_method' => 'session/new',
                    'load_method' => 'session/load',
                    'prompt_method' => 'session/prompt',
                    'id_argument' => 'sessionId',
                    'prompt_argument' => 'prompt',
                    'prompt_mode' => 'content_blocks',
                    'load_after_prompt' => true,
                    'create_params' => [
                        'cwd' => base_path(),
                        'mcpServers' => [],
                    ],
                    'load_params' => [
                        'cwd' => base_path(),
                        'mcpServers' => [],
                    ],
                    'prompt_params' => [],
                ],
            ],
            // 'gemini-cli' => [
            //     'label' => 'Gemini CLI ACP Bridge',
            //     'endpoint' => env('ACP_REMOTE_GEMINI_ENDPOINT'),
            //     'transport' => 'jsonrpc-http',
            //     'auth_type' => 'bearer',
            //     'token' => env('ACP_REMOTE_GEMINI_TOKEN'),
            //     'headers' => [],
            //     'allowed_methods' => [
            //         'initialize',
            //         'authenticate',
            //         'session/new',
            //         'session/load',
            //         'session/prompt',
            //     ],
            //     'initialize_payload' => [],
            //     'authenticate_payload' => [],
            //     'session' => [
            //         'reuse' => 'thread',
            //         'create_method' => 'session/new',
            //         'load_method' => 'session/load',
            //         'prompt_method' => 'session/prompt',
            //         'id_argument' => 'session_id',
            //         'prompt_argument' => 'prompt',
            //         'load_after_prompt' => true,
            //         'create_params' => [],
            //         'load_params' => [],
            //         'prompt_params' => [],
            //     ],
            // ],
        ],
    ],
];
