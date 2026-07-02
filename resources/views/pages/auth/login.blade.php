<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        @if ($teamInvitation)
            <x-team-invitation-alert :invitation="$teamInvitation" :action="__('Log in')" />
        @endif

        <form
            method="POST"
            action="{{ route('login.store') }}"
            class="flex flex-col gap-6"
            x-data="{ loading: false, authError: null }"
            @submit.prevent="
                loading = true;
                authError = null;
                fetch($el.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(new FormData($el))
                })
                .then(async r => {
                    const data = await r.json();
                    if (r.ok) {
                        Livewire.navigate(data.redirect);
                    } else {
                        loading = false;
                        const msgs = data.errors ? data.errors[Object.keys(data.errors)[0]] : null;
                        authError = Array.isArray(msgs) ? msgs[0] : (data.message || '{{ __('These credentials do not match our records.') }}');
                    }
                })
                .catch(() => { loading = false; authError = 'Error de conexión. Intente de nuevo.'; })
            "
        >
            @csrf

            <p x-show="authError" x-text="authError" x-cloak
               class="text-sm text-center text-red-600 dark:text-red-400 -mb-2"></p>

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <div>
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button"
                             x-bind:disabled="loading">
                    <span x-show="!loading">{{ __('Log in') }}</span>
                    <span x-show="loading" x-cloak>Iniciando sesión...</span>
                </flux:button>
            </div>
        </form>

    </div>
</x-layouts::auth>
