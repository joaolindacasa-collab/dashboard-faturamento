// Service worker mínimo: habilita a instalação como PWA sem cachear páginas
// autenticadas (é um painel financeiro ao vivo — não faz sentido servir dados
// velhos offline). A estratégia é network-only: só repassa a requisição.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', (event) => {
    event.respondWith(
        fetch(event.request).catch(() => new Response('', { status: 504, statusText: 'Offline' }))
    );
});
