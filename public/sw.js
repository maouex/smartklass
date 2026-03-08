// SmartKlass Service Worker — minimal (requis pour l'installation PWA)
const CACHE = 'smartklass-v1';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));

// Pas de cache agressif — l'app doit toujours charger les données fraîches
self.addEventListener('fetch', e => {
  // Laisser passer toutes les requêtes normalement
  e.respondWith(fetch(e.request).catch(() => caches.match(e.request)));
});
