/*
 * 会場の電波が弱い状況でも、静的ファイルの読み込みと
 * オフライン時の案内表示ができるようにするための Service Worker。
 *
 * ログイン後のページはユーザーごとの内容を含むため、意図的にキャッシュしない。
 */
var CACHE = 'kyodo-static-83b6fcfbe6f4';
var ASSETS = [
    '/css/app.css',
    '/css/theme.css',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/offline.html',
    '/manifest.webmanifest'
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE).then(function (cache) {
            return cache.addAll(ASSETS);
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.filter(function (key) {
                return key !== CACHE;
            }).map(function (key) {
                return caches.delete(key);
            }));
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function (event) {
    var request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    var url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // 画面遷移：常にネットワークを優先し、失敗したらオフライン案内を返す
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(function () {
                return caches.match('/offline.html').then(function (cached) {
                    return cached || new Response(
                        '<!doctype html><meta charset="utf-8"><title>オフライン</title>'
                        + '<p style="font-family:sans-serif;padding:2rem;text-align:center">'
                        + 'オフラインのため表示できません。電波の良い場所でお試しください。</p>',
                        { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                    );
                });
            })
        );

        return;
    }

    // アップロードされた画像（/storage/…）は利用者ごとの内容なのでキャッシュしない
    if (url.pathname.indexOf('/storage/') === 0) {
        return;
    }

    // 同梱の静的ファイル：キャッシュ優先
    if (/\.(css|js|png|jpg|jpeg|webp|svg|webmanifest)$/.test(url.pathname)) {
        event.respondWith(
            caches.match(request).then(function (cached) {
                return cached || fetch(request).then(function (response) {
                    if (response && response.status === 200 && response.type === 'basic') {
                        var copy = response.clone();
                        caches.open(CACHE).then(function (cache) {
                            cache.put(request, copy);
                        });
                    }

                    return response;
                });
            })
        );
    }
});
