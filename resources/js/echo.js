import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
Pusher.logToConsole = false;

const reverbHost = import.meta.env.VITE_REVERB_HOST;

if (reverbHost) {
    const reverbScheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http';
    const reverbPort   = import.meta.env.VITE_REVERB_PORT   ?? 8080;
    const useTLS       = reverbScheme === 'https';

    window.Echo = new Echo({
        broadcaster:       'reverb',
        key:               import.meta.env.VITE_REVERB_APP_KEY,
        wsHost:            reverbHost,
        wsPort:            reverbPort,
        wssPort:           reverbPort,
        forceTLS:          useTLS,
        enabledTransports: useTLS ? ['wss'] : ['ws'],
        disableStats:      true,
    });
}

window.dispatchEvent(new CustomEvent('echo:ready'));
