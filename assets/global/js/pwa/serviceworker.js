var staticCacheName = "pwa-v20260426-teenpatti-layout";

self.addEventListener("install", function (event) {
    self.skipWaiting();
    event.waitUntil(caches.open(staticCacheName));
});

self.addEventListener("activate", function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.map(function (key) {
                if (key !== staticCacheName) {
                    return caches.delete(key);
                }
                return Promise.resolve();
            }));
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener("fetch", function (event) {
    if (event.request.method !== "GET") {
        return;
    }

    event.respondWith(
        fetch(event.request).then(function (response) {
            var copy = response.clone();
            caches.open(staticCacheName).then(function (cache) {
                cache.put(event.request, copy);
            });
            return response;
        }).catch(function () {
            return caches.match(event.request);
        })
    );
});
