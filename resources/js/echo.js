import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
Pusher.logToConsole = false;

const reverbScheme  = import.meta.env.VITE_REVERB_SCHEME ?? 'http';
const reverbPort    = import.meta.env.VITE_REVERB_PORT   ?? 8080;
const useTLS        = reverbScheme === 'https';

window.Echo = new Echo({
    broadcaster:        'reverb',
    key:                import.meta.env.VITE_REVERB_APP_KEY,
    wsHost:             import.meta.env.VITE_REVERB_HOST,
    wsPort:             reverbPort,
    wssPort:            reverbPort,
    forceTLS:           useTLS,
    enabledTransports:  useTLS ? ['wss'] : ['ws'],
    disableStats:       true,
});

window.dispatchEvent(new CustomEvent('echo:ready'));
