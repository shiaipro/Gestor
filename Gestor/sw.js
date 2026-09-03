self.addEventListener('install', (event) => {
    console.log('Service Worker: Instalado');
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    console.log('Service Worker: Ativado');
});

self.addEventListener('fetch', (event) => {
    // Basic fetch handler (required for PWA install prompt in most browsers)
    // We are just returning the fetch request (network only)
    event.respondWith(fetch(event.request));
});
