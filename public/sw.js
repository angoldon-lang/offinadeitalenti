/* Service worker minimo: app shell in cache, rete per i dati.
   L'obiettivo non e' rendere offline tutta l'app, ma non lasciare mai
   l'utente davanti a uno schermo bianco mentre compila le ore. */
const CACHE = 'odt-v1';
const SHELL = [
  '/assets/css/app.css?v=1',
  '/assets/js/app.js?v=1',
  '/assets/js/timesheet.js?v=1',
  '/manifest.webmanifest'
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;                    // le scritture passano dalla coda applicativa

  const url = new URL(req.url);
  if (url.origin !== location.origin) return;
  if (url.pathname.startsWith('/documenti/') || url.pathname.includes('/scarica')) return;  // mai in cache

  // Statici: cache first. Pagine: rete, con la copia in cache come rete di sicurezza.
  if (url.pathname.startsWith('/assets/')) {
    e.respondWith(caches.match(req).then((hit) => hit || fetch(req)));
    return;
  }

  e.respondWith(
    fetch(req)
      .then((res) => {
        const copy = res.clone();
        caches.open(CACHE).then((c) => c.put(req, copy));
        return res;
      })
      .catch(() => caches.match(req).then((hit) => hit || caches.match('/')))
  );
});
