/** A small cache for the app shell, so the browser launches and the static
 *  assets (CSS, JS, logo) load even on a spotty connection. Authenticated
 *  pages are deliberately NOT cached (they'd leak data between users). */
var CACHE_NAME = 'pmas-shell-v2';
var OFFLINE_URL = '/offline.html';
var CACHEABLE_EXT = /\.(css|js|png|jpe?g|gif|svg|webp|woff2?|ico|avif)(\?.*)?$/i;

self.addEventListener('install', function (event) {
	event.waitUntil(
		caches.open(CACHE_NAME).then(function (cache) {
			return cache.add(OFFLINE_URL);
		})
	);
	self.skipWaiting();
});

self.addEventListener('activate', function (event) {
	event.waitUntil(
		caches.keys().then(function (keys) {
			return Promise.all(keys.filter(function (key) {
				return key !== CACHE_NAME;
			}).map(function (key) {
				return caches.delete(key);
			}));
		})
	);
	self.clients.claim();
});

self.addEventListener('fetch', function (event) {
	var request = event.request;
	var url = new URL(request.url);

	if (request.method !== 'GET' || url.origin !== self.location.origin) {
		return;
	}

	// Cache static assets, stale-while-revalidate.
	if (CACHEABLE_EXT.test(url.pathname)) {
		event.respondWith(
			caches.match(request).then(function (cached) {
				var network = fetch(request).then(function (response) {
					if (response && response.status === 200) {
						var clone = response.clone();
						caches.open(CACHE_NAME).then(function (cache) { cache.put(request, clone); });
					}
					return response;
				}).catch(function () {
					return cached;
				});
				return cached || network;
			})
		);
		return;
	}

	// Only navigations can fall back to the offline page.
	if (request.mode === 'navigate') {
		event.respondWith(
			fetch(request).catch(function () {
				return caches.match(OFFLINE_URL);
			})
		);
	}
});
