const CACHE_NAME = 'gube-stream-v1';
// Lista de arquivos básicos para armazenar em cache offline
const ASSETS_TO_CACHE = [
  'index.php',
  'manifest.json',
  'logo-app.svg'
];

// Instala o Service Worker e armazena os arquivos estruturais no cache
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE);
    })
  );
  self.skipWaiting();
});

// Ativa e limpa caches antigos se houver atualização de versão
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cache) => {
          if (cache !== CACHE_NAME) {
            return caches.delete(cache);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Estratégia de Fetch: Tenta buscar na rede; se falhar ou estiver offline, busca no Cache
self.addEventListener('fetch', (event) => {
  // Ignora requisições de streams de vídeo (m3u8, ts, mp4) para não estourar o limite de cache
  if (event.request.url.includes('.m3u8') || event.request.url.includes('.ts')) {
    return;
  }

  event.respondWith(
    fetch(event.request).catch(() => {
      return caches.match(event.request);
    })
  );
});