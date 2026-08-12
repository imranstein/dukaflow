import Alpine from 'alpinejs';
import { repApp } from './rep/app';

window.Alpine = Alpine;
Alpine.data('repApp', () => repApp(window.DUKAFLOW_REP?.id ?? null));
Alpine.start();

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/rep' }).catch(() => {
            // Offline capture still works without it — the shell just
            // won't survive a hard reload while offline.
        });
    });
}
