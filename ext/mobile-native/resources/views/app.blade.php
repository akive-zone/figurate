<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover"
    >
    <meta
        http-equiv="Content-Security-Policy"
        content="upgrade-insecure-requests"
    >
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @inertiaHead
</head>
<body class="nativephp-safe-area root root--native">
    <native:top-bar>
        <native:top-bar-title value="Figurate" />
    </native:top-bar>

    <native:bottom-nav>
        <native:bottom-nav-item id="chat.channels" icon="home" label="Channels" url="{{ route('chat.index', [], false) }}" />
        <native:bottom-nav-item id="chat.new" icon="plus" label="New Chat" url="{{ route('chat.create', [], false) }}" />
    </native:bottom-nav>

    @inertia
</body>
</html>
