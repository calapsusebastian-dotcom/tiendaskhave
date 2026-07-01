import './echo.js';

// Helper global: suscribe a un canal Echo cuando esté disponible.
window.echoWhen = (fn) => {
    if (window.Echo) {
        fn(window.Echo);
    } else {
        window.addEventListener('echo:ready', () => fn(window.Echo), { once: true });
    }
};
