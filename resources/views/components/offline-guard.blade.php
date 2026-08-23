{{--
    会場は回線が混雑して繋がらないことがある。そのための保険。

    - 入力中の内容を端末に一時保存し、戻ってきたときに復元する
    - オフラインのまま送信しようとしたら止めて、入力を守る
    - 圏外になったことを画面上部で知らせる

    保存先は sessionStorage（タブを閉じると消える）。
    共用端末に他人の入力内容が残らないようにするため、localStorage は使わない。
--}}
<div id="offline-banner"
     class="fixed left-0 right-0 top-14 z-50 hidden bg-amber-500 px-3 py-2 text-center text-xs font-semibold text-white"
     role="status" aria-live="polite">
    電波が届いていません。入力内容は端末に保存しています。
</div>

<script>
(function () {
    var banner = document.getElementById('offline-banner');

    function refreshBanner() {
        if (!banner) { return; }
        banner.classList.toggle('hidden', navigator.onLine !== false);
    }

    window.addEventListener('online', refreshBanner);
    window.addEventListener('offline', refreshBanner);
    refreshBanner();

    function store() {
        try { return window.sessionStorage; } catch (e) { return null; }
    }

    // 入力を保存するフォーム（data-offline-guard を付けたもの）
    var forms = Array.prototype.slice.call(document.querySelectorAll('form[data-offline-guard]'));

    forms.forEach(function (form) {
        var key = 'draft:' + (form.getAttribute('data-offline-guard') || form.getAttribute('action') || '');
        var storage = store();
        if (!storage) { return; }

        var fields = Array.prototype.slice.call(
            form.querySelectorAll('input[name], select[name], textarea[name]')
        ).filter(function (field) {
            return field.type !== 'hidden' && field.type !== 'file' && field.name !== '_token';
        });

        if (fields.length === 0) { return; }

        // 復元
        try {
            var saved = JSON.parse(storage.getItem(key) || 'null');

            if (saved && typeof saved === 'object') {
                var restored = false;

                fields.forEach(function (field) {
                    if (!Object.prototype.hasOwnProperty.call(saved, field.name)) { return; }
                    if (field.type === 'checkbox' || field.type === 'radio') {
                        field.checked = !!saved[field.name];
                    } else if (field.value !== saved[field.name]) {
                        field.value = saved[field.name];
                        restored = true;
                    }
                });

                if (restored) {
                    form.dispatchEvent(new Event('offline-guard:restored', { bubbles: true }));

                    var notice = document.createElement('p');
                    notice.className = 'mt-2 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-900';
                    notice.textContent = '前回入力していた内容を復元しました。';
                    form.insertBefore(notice, form.firstChild);
                }
            }
        } catch (e) { /* 壊れた保存内容は無視する */ }

        function save() {
            var data = {};
            fields.forEach(function (field) {
                data[field.name] = (field.type === 'checkbox' || field.type === 'radio')
                    ? field.checked
                    : field.value;
            });
            try { storage.setItem(key, JSON.stringify(data)); } catch (e) { /* 容量超過は無視 */ }
        }

        form.addEventListener('input', save);
        form.addEventListener('change', save);

        form.addEventListener('submit', function (event) {
            if (navigator.onLine === false) {
                event.preventDefault();
                save();
                refreshBanner();
                window.alert('電波が届いていないため、まだ送信できません。\n入力内容は保存したので、電波が戻ってからもう一度お試しください。');

                return;
            }

            // 送信できたら下書きは不要
            try { storage.removeItem(key); } catch (e) { /* 何もしない */ }
        });
    });

    // オフラインでも送信できないボタンを、押した時点で知らせる
    document.addEventListener('submit', function (event) {
        if (navigator.onLine !== false) { return; }
        if (event.target.hasAttribute('data-offline-guard')) { return; }
        if (event.target.hasAttribute('data-offline-allow')) { return; }

        event.preventDefault();
        refreshBanner();
        window.alert('電波が届いていないため、まだ送信できません。電波が戻ってからもう一度お試しください。');
    }, true);
})();
</script>
