<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Add, list, and delete your account passkeys.
            </p>
        </div>

        <div
            class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"
            id="passkeys-manager"
            data-index-url="{{ route('api.passkeys.index') }}"
            data-register-options-url="{{ route('api.passkeys.register-options') }}"
            data-store-url="{{ route('api.passkeys.store') }}"
            data-destroy-template="{{ route('api.passkeys.destroy', ['passkey' => '__PASSKEY__']) }}"
        >
            <div class="passkeys">
                <h4 class="passkeys__title">Add passkey</h4>
                <form class="passkeys__form" data-passkey-form>
                    <input
                        type="text"
                        class="input"
                        maxlength="255"
                        placeholder="Passkey name (optional)"
                        data-passkey-name
                    >
                    <button type="submit" class="button" data-passkey-submit>Add passkey</button>
                </form>
                <p class="error" data-passkey-error hidden></p>

                <ul class="passkeys__list" data-passkey-list>
                    @foreach (auth()->user()?->passkeys()->latest('id')->get() ?? [] as $passkey)
                        <li class="passkeys__item" data-passkey-id="{{ $passkey->id }}">
                            <div>
                                <p class="passkeys__name">{{ $passkey->name }}</p>
                                <p class="passkeys__meta">
                                    @if ($passkey->last_used_at)
                                        Last used {{ $passkey->last_used_at->diffForHumans() }}
                                    @else
                                        Never used
                                    @endif
                                </p>
                            </div>
                            <button type="button" class="link" data-passkey-delete="{{ $passkey->id }}">Delete</button>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    @vite('ext/web-view/resources/js/passkeys.js')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const root = document.getElementById('passkeys-manager');

            if (!root) {
                return;
            }

            const state = {
                endpoints: {
                    index: root.dataset.indexUrl ?? '',
                    registerOptions: root.dataset.registerOptionsUrl ?? '',
                    store: root.dataset.storeUrl ?? '',
                    destroyTemplate: root.dataset.destroyTemplate ?? '',
                },
                form: root.querySelector('[data-passkey-form]'),
                nameInput: root.querySelector('[data-passkey-name]'),
                submitButton: root.querySelector('[data-passkey-submit]'),
                error: root.querySelector('[data-passkey-error]'),
                list: root.querySelector('[data-passkey-list]'),
            };

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

            const headers = (json = true) => ({
                Accept: 'application/json',
                ...(json ? { 'Content-Type': 'application/json' } : {}),
                ...(csrfToken !== '' ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            });

            const showError = (message) => {
                if (!state.error) {
                    return;
                }

                state.error.hidden = !message;
                state.error.textContent = message;
            };

            const formatPasskeyMeta = (passkey) => {
                const lastUsedAt = (passkey?.last_used_at ?? '').toString().trim();

                return lastUsedAt !== '' ? `Last used ${new Date(lastUsedAt).toLocaleString()}` : 'Never used';
            };

            const renderPasskeys = (passkeys) => {
                if (!state.list) {
                    return;
                }

                state.list.innerHTML = '';

                passkeys.forEach((passkey) => {
                    const item = document.createElement('li');
                    item.className = 'passkeys__item';
                    item.dataset.passkeyId = passkey.id;
                    item.innerHTML = `
                        <div>
                            <p class="passkeys__name"></p>
                            <p class="passkeys__meta"></p>
                        </div>
                        <button type="button" class="link">Delete</button>
                    `;

                    item.querySelector('.passkeys__name').textContent = passkey.name ?? 'Passkey';
                    item.querySelector('.passkeys__meta').textContent = formatPasskeyMeta(passkey);
                    item.querySelector('button').addEventListener('click', () => deletePasskey(passkey.id));
                    state.list.appendChild(item);
                });
            };

            const loadPasskeys = async () => {
                if (!state.endpoints.index) {
                    return;
                }

                const response = await fetch(state.endpoints.index, {
                    credentials: 'same-origin',
                    headers: headers(false),
                });

                if (!response.ok) {
                    throw new Error(`Unable to load passkeys (${response.status}).`);
                }

                const payload = await response.json();

                renderPasskeys(Array.isArray(payload?.data) ? payload.data : []);
            };

            const deletePasskey = async (passkeyId) => {
                showError('');

                const response = await fetch(
                    state.endpoints.destroyTemplate.replace('__PASSKEY__', String(passkeyId)),
                    {
                        method: 'DELETE',
                        credentials: 'same-origin',
                        headers: headers(false),
                    },
                );

                if (!response.ok) {
                    throw new Error(`Unable to delete passkey (${response.status}).`);
                }

                await loadPasskeys();
            };

            root.querySelectorAll('[data-passkey-delete]').forEach((button) => {
                button.addEventListener('click', () => deletePasskey(button.dataset.passkeyDelete));
            });

            state.form?.addEventListener('submit', async (event) => {
                event.preventDefault();
                showError('');

                try {
                    if (typeof window.startRegistration !== 'function') {
                        throw new Error('Passkey registration is not available in this browser.');
                    }

                    const optionsResponse = await fetch(state.endpoints.registerOptions, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: headers(),
                        body: JSON.stringify({}),
                    });

                    if (!optionsResponse.ok) {
                        throw new Error(`Unable to start passkey registration (${optionsResponse.status}).`);
                    }

                    const optionsPayload = await optionsResponse.json();
                    const ceremonyId = (optionsPayload?.data?.ceremony_id ?? '').toString().trim();
                    const options = optionsPayload?.data?.options ?? null;

                    if (ceremonyId === '' || !options) {
                        throw new Error('Unable to start passkey registration.');
                    }

                    state.submitButton?.setAttribute('disabled', 'disabled');

                    const registration = await window.startRegistration({ optionsJSON: options });
                    const response = await fetch(state.endpoints.store, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: headers(),
                        body: JSON.stringify({
                            name: state.nameInput?.value?.trim() || `Passkey ${new Date().toLocaleDateString()}`,
                            ceremony_id: ceremonyId,
                            passkey: JSON.stringify(registration),
                        }),
                    });

                    if (!response.ok) {
                        const payload = await response.json().catch(() => ({}));
                        const message = payload?.errors?.passkey?.[0]
                            ?? payload?.errors?.name?.[0]
                            ?? payload?.errors?.ceremony_id?.[0]
                            ?? payload?.message
                            ?? 'Unable to create passkey.';
                        throw new Error(message);
                    }

                    if (state.nameInput) {
                        state.nameInput.value = '';
                    }

                    await loadPasskeys();
                } catch (error) {
                    showError(error?.message ?? 'Unable to create passkey.');
                } finally {
                    state.submitButton?.removeAttribute('disabled');
                }
            });

            loadPasskeys().catch((error) => {
                showError(error?.message ?? 'Unable to load passkeys.');
            });
        });
    </script>
</x-filament-panels::page>
