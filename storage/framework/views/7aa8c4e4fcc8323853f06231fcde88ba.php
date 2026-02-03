## NativePHP Mobile

- NativePHP Mobile is a Laravel package that enables developers to build native iOS and Android applications using PHP and native UI components.
- NativePHP Mobile runs a full PHP runtime directly on the device with SQLite — no web server required.
- NativePHP Mobile supports **two frontend approaches**: Livewire/Blade (PHP) or JavaScript frameworks (Vue, React, Inertia, etc.).
- NativePHP Mobile documentation is hosted at ___SINGLE_BACKTICK___https://nativephp.com/docs/mobile/2/**___SINGLE_BACKTICK___
- **Before implementing any features using NativePHP Mobile, use the ___SINGLE_BACKTICK___web-search___SINGLE_BACKTICK___ tool to get the latest docs for that specific feature. The docs listing is available in <available-docs>**

### Identifying the Development Environment

**IMPORTANT:** Before running commands or giving platform-specific advice, determine:

1. **Operating System** (check with system info or ask):
   - **macOS**: Can build and run for **both iOS and Android**
   - **Windows/Linux**: Can **only build for Android** — iOS requires macOS with Xcode
   - When on Windows/Linux, never suggest ___SINGLE_BACKTICK___php artisan native:run ios___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___native:install ios___SINGLE_BACKTICK___, or ___SINGLE_BACKTICK___native:open ios___SINGLE_BACKTICK___
   - **Note:** WSL (Windows Subsystem for Linux) is NOT supported — must run directly on Windows

2. **Frontend Stack** (examine the codebase):
   - **Livewire/Blade**: Look for ___SINGLE_BACKTICK___.blade.php___SINGLE_BACKTICK___ files with ___SINGLE_BACKTICK___wire:___SINGLE_BACKTICK___ directives, Livewire components in ___SINGLE_BACKTICK___app/Livewire/___SINGLE_BACKTICK___
   - **JavaScript (Vue/React/Inertia)**: Look for ___SINGLE_BACKTICK___.vue___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___.jsx___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___.tsx___SINGLE_BACKTICK___ files, ___SINGLE_BACKTICK___resources/js/___SINGLE_BACKTICK___ with framework code, ___SINGLE_BACKTICK___inertiajs___SINGLE_BACKTICK___ in ___SINGLE_BACKTICK___package.json___SINGLE_BACKTICK___

### Required Environment Variables

**CRITICAL:** Before running ___SINGLE_BACKTICK___php artisan native:install___SINGLE_BACKTICK___, ensure these are set in ___SINGLE_BACKTICK___.env___SINGLE_BACKTICK___:

___SINGLE_BACKTICK______SINGLE_BACKTICK______SINGLE_BACKTICK___dotenv
NATIVEPHP_APP_ID=com.yourcompany.yourapp
NATIVEPHP_APP_VERSION="DEBUG"
NATIVEPHP_APP_VERSION_CODE="1"
___SINGLE_BACKTICK______SINGLE_BACKTICK______SINGLE_BACKTICK___

- ___SINGLE_BACKTICK___NATIVEPHP_APP_ID___SINGLE_BACKTICK___: Reverse-domain app identifier (e.g., ___SINGLE_BACKTICK___com.acme.myapp___SINGLE_BACKTICK___) — **required**
- ___SINGLE_BACKTICK___NATIVEPHP_APP_VERSION___SINGLE_BACKTICK___: Use ___SINGLE_BACKTICK___"DEBUG"___SINGLE_BACKTICK___ for development, semantic version (e.g., ___SINGLE_BACKTICK___"1.0.0"___SINGLE_BACKTICK___) for production
- ___SINGLE_BACKTICK___NATIVEPHP_APP_VERSION_CODE___SINGLE_BACKTICK___: Integer build number for Play Store (increment with each release)

**Optional but recommended for iOS:**
___SINGLE_BACKTICK______SINGLE_BACKTICK______SINGLE_BACKTICK___dotenv
NATIVEPHP_DEVELOPMENT_TEAM=XXXXXXXXXX
___SINGLE_BACKTICK______SINGLE_BACKTICK______SINGLE_BACKTICK___
Find your Team ID in your [Apple Developer account](https://developer.apple.com/account) under 'Membership details'.

### PHP Usage (Livewire/Blade Projects)

Use PHP Facades in the ___SINGLE_BACKTICK___Native\Mobile\Facades___SINGLE_BACKTICK___ namespace:
- ___SINGLE_BACKTICK___Camera::getPhoto()___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___Dialog::toast()___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___Biometrics::prompt()___SINGLE_BACKTICK___, etc.
- All Facades: ___SINGLE_BACKTICK___Camera___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___Dialog___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___Biometrics___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___Network___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___SecureStorage___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___File___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___Share___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___Haptics___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___System___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___Device___SINGLE_BACKTICK___
- Note: ___SINGLE_BACKTICK___Browser___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___Scanner___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___Microphone___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___Geolocation___SINGLE_BACKTICK___, and ___SINGLE_BACKTICK___PushNotifications___SINGLE_BACKTICK___ are available as separate NativePHP plugins (nativephp/browser, nativephp/scanner, nativephp/microphone, nativephp/geolocation, nativephp/mobile-firebase).
- Listen for events with ___SINGLE_BACKTICK___#[OnNative(EventClass::class)]___SINGLE_BACKTICK___ attribute on Livewire component methods
- Use EDGE components in Blade templates for native UI (___SINGLE_BACKTICK___native:bottom-nav___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___native:top-bar___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___native:side-nav___SINGLE_BACKTICK___)

### JavaScript Usage (Vue/React/Inertia Projects)

Import from the NativePHP JavaScript bridge library:
___SINGLE_BACKTICK______SINGLE_BACKTICK______SINGLE_BACKTICK___javascript
import { camera, dialog, scanner, biometric, on, Events } from '#nativephp';
// or individual imports
import { getPhoto, alert, scanQR } from '#nativephp';
___SINGLE_BACKTICK______SINGLE_BACKTICK______SINGLE_BACKTICK___

The JS API mirrors the PHP Facades with fluent builders:
- ___SINGLE_BACKTICK___await camera.getPhoto()___SINGLE_BACKTICK___ / ___SINGLE_BACKTICK___await dialog.alert('Title', 'Message')___SINGLE_BACKTICK___ / ___SINGLE_BACKTICK___await scanner.scan()___SINGLE_BACKTICK___
- ___SINGLE_BACKTICK___await biometric.prompt().id('auth-check')___SINGLE_BACKTICK___ — fluent builder pattern
- ___SINGLE_BACKTICK___await scanner.scan().prompt('Scan ticket').formats(['qr', 'ean13'])___SINGLE_BACKTICK___

Listen for events with ___SINGLE_BACKTICK___on()___SINGLE_BACKTICK___ and ___SINGLE_BACKTICK___off()___SINGLE_BACKTICK___:
___SINGLE_BACKTICK______SINGLE_BACKTICK______SINGLE_BACKTICK___javascript
import { on, off, Events } from '#nativephp';
on(Events.Camera.PhotoTaken, (payload) => { /* handle photo */ });
// Cleanup in unmount: off(Events.Camera.PhotoTaken, handler);
___SINGLE_BACKTICK______SINGLE_BACKTICK______SINGLE_BACKTICK___

### Event Handling (Both Stacks)

Asynchronous operations dispatch events to both JavaScript and PHP simultaneously.

**Livewire/Blade:**
___SINGLE_BACKTICK______SINGLE_BACKTICK______SINGLE_BACKTICK___php
use Native\Mobile\Attributes\OnNative;
use Native\Mobile\Events\Camera\PhotoTaken;

#[OnNative(PhotoTaken::class)]
public function handlePhoto(string $path) { /* ... */ }
___SINGLE_BACKTICK______SINGLE_BACKTICK______SINGLE_BACKTICK___

**JavaScript (Vue/React):**
___SINGLE_BACKTICK______SINGLE_BACKTICK______SINGLE_BACKTICK___javascript
import { on, Events } from '#nativephp';
on(Events.Camera.PhotoTaken, ({ path }) => { /* ... */ });
___SINGLE_BACKTICK______SINGLE_BACKTICK______SINGLE_BACKTICK___

Custom events can extend built-in events and be passed via ___SINGLE_BACKTICK___->event(CustomEvent::class)___SINGLE_BACKTICK___ (PHP) or ___SINGLE_BACKTICK___.event('App\\Events\\Custom')___SINGLE_BACKTICK___ (JS).

### EDGE Components (Native UI)

- EDGE (Element Definition and Generation Engine) renders Blade components as truly native UI elements.
- Components use ___SINGLE_BACKTICK___native:___SINGLE_BACKTICK___ prefix: ___SINGLE_BACKTICK___native:bottom-nav___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___native:top-bar___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___native:side-nav___SINGLE_BACKTICK___.
- Child items require unique ___SINGLE_BACKTICK___id___SINGLE_BACKTICK___ attributes for lifecycle management.
- Add ___SINGLE_BACKTICK___nativephp-safe-area___SINGLE_BACKTICK___ class to body for proper handling of notches and navigation areas.
- **Note:** EDGE components are defined in Blade templates and work with both Livewire and Inertia apps (the layout is still Blade).

<available-docs>
## Getting Started
- [https://nativephp.com/docs/mobile/2/getting-started/introduction] Use these docs for comprehensive introduction to NativePHP Mobile, overview of how PHP runs natively on device, the embedded runtime architecture, and core philosophy behind the package
- [https://nativephp.com/docs/mobile/2/getting-started/quick-start] Use these docs for rapid setup guide to get your first mobile app running in minutes
- [https://nativephp.com/docs/mobile/2/getting-started/environment-setup] Use these docs for setting up your development environment including Xcode, Android Studio, simulators, and required dependencies
- [https://nativephp.com/docs/mobile/2/getting-started/installation] Use these docs for step-by-step installation via Composer, running ___SINGLE_BACKTICK___php artisan native:install___SINGLE_BACKTICK___, platform-specific setup, and ICU support options
- [https://nativephp.com/docs/mobile/2/getting-started/configuration] Use these docs for detailed configuration guide including NATIVEPHP_APP_ID, NATIVEPHP_APP_VERSION, permissions setup, and config/nativephp.php options
- [https://nativephp.com/docs/mobile/2/getting-started/development] Use these docs for development workflow including ___SINGLE_BACKTICK___php artisan native:run___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___native:watch___SINGLE_BACKTICK___ for hot reload, ___SINGLE_BACKTICK___native:tail___SINGLE_BACKTICK___ for logs, and debugging techniques
- [https://nativephp.com/docs/mobile/2/getting-started/deployment] Use these docs for packaging and deploying apps to App Store and Play Store using ___SINGLE_BACKTICK___php artisan native:package___SINGLE_BACKTICK___
- [https://nativephp.com/docs/mobile/2/getting-started/versioning] Use these docs for version management, semantic versioning, and ___SINGLE_BACKTICK___php artisan native:release___SINGLE_BACKTICK___ command
- [https://nativephp.com/docs/mobile/2/getting-started/changelog] Use these docs for version history and release notes
- [https://nativephp.com/docs/mobile/2/getting-started/roadmap] Use these docs for upcoming features and planned improvements
- [https://nativephp.com/docs/mobile/2/getting-started/support-policy] Use these docs for support policy and compatibility information

## The Basics
- [https://nativephp.com/docs/mobile/2/the-basics/overview] Use these docs for understanding how NativePHP Mobile works, the bridge between PHP and native code, and the overall architecture
- [https://nativephp.com/docs/mobile/2/the-basics/events] Use these docs for the complete event system guide including async vs sync operations, event handling in Livewire with ___SINGLE_BACKTICK___#[OnNative()]___SINGLE_BACKTICK___, JavaScript event handling with ___SINGLE_BACKTICK___Native.on()___SINGLE_BACKTICK___, custom events, and the dual dispatch pattern
- [https://nativephp.com/docs/mobile/2/the-basics/native-functions] Use these docs for understanding the ___SINGLE_BACKTICK___nativephp_call()___SINGLE_BACKTICK___ function, the bridge function registry, and how to extend native functionality
- [https://nativephp.com/docs/mobile/2/the-basics/native-components] Use these docs for overview of native UI components and how they integrate with your app
- [https://nativephp.com/docs/mobile/2/the-basics/web-view] Use these docs for understanding the web view rendering, JavaScript bridge, and how PHP content is displayed
- [https://nativephp.com/docs/mobile/2/the-basics/splash-screens] Use these docs for configuring splash screens on iOS and Android
- [https://nativephp.com/docs/mobile/2/the-basics/app-icon] Use these docs for setting up app icons for both platforms
- [https://nativephp.com/docs/mobile/2/the-basics/assets] Use these docs for managing static assets, images, and files in your mobile app

## EDGE Components (Native UI)
- [https://nativephp.com/docs/mobile/2/edge-components/introduction] Use these docs for understanding EDGE (Element Definition and Generation Engine), how Blade components become native UI, server-driven UI approach, and the JSON compilation process
- [https://nativephp.com/docs/mobile/2/edge-components/bottom-nav] Use these docs for implementing bottom navigation bars with ___SINGLE_BACKTICK___native:bottom-nav___SINGLE_BACKTICK___ and ___SINGLE_BACKTICK___native:bottom-nav-item___SINGLE_BACKTICK___, including icons, labels, URLs, and styling
- [https://nativephp.com/docs/mobile/2/edge-components/top-bar] Use these docs for implementing top app bars with ___SINGLE_BACKTICK___native:top-bar___SINGLE_BACKTICK___ and ___SINGLE_BACKTICK___native:top-bar-action___SINGLE_BACKTICK___, including titles, navigation icons, and action buttons
- [https://nativephp.com/docs/mobile/2/edge-components/side-nav] Use these docs for implementing slide-out navigation drawers with ___SINGLE_BACKTICK___native:side-nav___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___native:side-nav-item___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___native:side-nav-header___SINGLE_BACKTICK___, and ___SINGLE_BACKTICK___native:side-nav-group___SINGLE_BACKTICK___
- [https://nativephp.com/docs/mobile/2/edge-components/icons] Use these docs for available icon names and how to use icons in EDGE components

## APIs (Device Features)
- [https://nativephp.com/docs/mobile/2/apis/camera] Use these docs for camera operations including ___SINGLE_BACKTICK___Camera::getPhoto()___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___Camera::recordVideo()___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___Camera::pickImages()___SINGLE_BACKTICK___, PhotoTaken and VideoRecorded events
- [https://nativephp.com/docs/mobile/2/apis/microphone] Use these docs for audio recording with ___SINGLE_BACKTICK___Microphone::record()___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___->start()___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___->stop()___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___->pause()___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___->resume()___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___->getStatus()___SINGLE_BACKTICK___, and MicrophoneRecorded events
- [https://nativephp.com/docs/mobile/2/apis/scanner] Use these docs for QR code and barcode scanning with ___SINGLE_BACKTICK___Scanner::scan()___SINGLE_BACKTICK___, fluent configuration, CodeScanned events, and supported formats
- [https://nativephp.com/docs/mobile/2/apis/dialog] Use these docs for native dialogs with ___SINGLE_BACKTICK___Dialog::alert()___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___Dialog::toast()___SINGLE_BACKTICK___, button configuration, and ButtonPressed events
- [https://nativephp.com/docs/mobile/2/apis/biometrics] Use these docs for Face ID/Touch ID authentication with ___SINGLE_BACKTICK___Biometrics::prompt()___SINGLE_BACKTICK___, fluent API, and Completed events
- [https://nativephp.com/docs/mobile/2/apis/push-notifications] Use these docs for push notification enrollment with ___SINGLE_BACKTICK___PushNotifications::enroll()___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___->getToken()___SINGLE_BACKTICK___, and TokenGenerated events (requires nativephp/mobile-firebase plugin)
- [https://nativephp.com/docs/mobile/2/apis/geolocation] Use these docs for location services with ___SINGLE_BACKTICK___Geolocation::getCurrentPosition()___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___->checkPermissions()___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___->requestPermissions()___SINGLE_BACKTICK___, and LocationReceived events
- [https://nativephp.com/docs/mobile/2/apis/browser] Use these docs for opening URLs with ___SINGLE_BACKTICK___Browser::open()___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___Browser::inApp()___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___Browser::auth()___SINGLE_BACKTICK___ for OAuth flows (requires nativephp/browser plugin)
- [https://nativephp.com/docs/mobile/2/apis/secure-storage] Use these docs for secure credential storage with ___SINGLE_BACKTICK___SecureStorage::get()___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___->set()___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___->delete()___SINGLE_BACKTICK___ using device Keychain/KeyStore
- [https://nativephp.com/docs/mobile/2/apis/share] Use these docs for native share sheet with ___SINGLE_BACKTICK___Share::url()___SINGLE_BACKTICK___ and ___SINGLE_BACKTICK___Share::file()___SINGLE_BACKTICK___
- [https://nativephp.com/docs/mobile/2/apis/file] Use these docs for file operations with ___SINGLE_BACKTICK___File::move()___SINGLE_BACKTICK___ and ___SINGLE_BACKTICK___File::copy()___SINGLE_BACKTICK___
- [https://nativephp.com/docs/mobile/2/apis/network] Use these docs for network status checking with ___SINGLE_BACKTICK___Network::status()___SINGLE_BACKTICK___
- [https://nativephp.com/docs/mobile/2/apis/haptics] Use these docs for haptic feedback with ___SINGLE_BACKTICK___Haptics::vibrate()___SINGLE_BACKTICK___ (prefer ___SINGLE_BACKTICK___Device::vibrate()___SINGLE_BACKTICK___)
- [https://nativephp.com/docs/mobile/2/apis/device] Use these docs for device information with ___SINGLE_BACKTICK___Device::getId()___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___->getInfo()___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___->getBatteryInfo()___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___->vibrate()___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___->toggleFlashlight()___SINGLE_BACKTICK___
- [https://nativephp.com/docs/mobile/2/apis/system] Use these docs for platform detection with ___SINGLE_BACKTICK___System::isIos()___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___System::isAndroid()___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___System::isMobile()___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___System::flashlight()___SINGLE_BACKTICK___

## Concepts
- [https://nativephp.com/docs/mobile/2/concepts/databases] Use these docs for SQLite database usage, local data storage, and when to use local vs API storage
- [https://nativephp.com/docs/mobile/2/concepts/deep-links] Use these docs for configuring deep links, URL schemes, and universal links
- [https://nativephp.com/docs/mobile/2/concepts/push-notifications] Use these docs for comprehensive push notification setup including Firebase, APNs, and server-side integration
- [https://nativephp.com/docs/mobile/2/concepts/security] Use these docs for security best practices, secure storage, and protecting sensitive data
- [https://nativephp.com/docs/mobile/2/concepts/ci-cd] Use these docs for continuous integration and deployment pipelines for mobile apps
</available-docs><?php /**PATH /Users/webong/Workspace/Projects/Akive/figurate/storage/framework/views/b1cdc6900af4bad35fe322e8a086bd9f.blade.php ENDPATH**/ ?>