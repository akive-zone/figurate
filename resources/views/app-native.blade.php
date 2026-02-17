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
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="nativephp-safe-area signal-root signal-root--native">
    <native:top-bar>
        <native:top-bar-title value="Figurate" />
    </native:top-bar>

    <native:bottom-nav>
        <native:bottom-nav-item id="signal.channels" icon="home" label="Channels" url="{{ route('signal.index') }}" />
        <native:bottom-nav-item id="signal.new" icon="plus" label="New Chat" url="{{ route('signal.chat.create') }}" />
    </native:bottom-nav>

    @inertia
</body>
</html>
