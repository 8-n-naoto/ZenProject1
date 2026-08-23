{{-- 画面共通の細かな挙動（外部ライブラリなし） --}}
<script>
(function () {
    // 二重送信の防止：送信中はボタンを無効化して表示を切り替える
    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!(form instanceof HTMLFormElement) || form.dataset.submitting === '1') {
            return;
        }

        // confirm() でキャンセルされた場合は何もしない
        if (event.defaultPrevented) {
            return;
        }

        form.dataset.submitting = '1';

        var buttons = form.querySelectorAll('button[type="submit"], button:not([type])');
        buttons.forEach(function (button) {
            button.disabled = true;
            if (!button.dataset.originalText) {
                button.dataset.originalText = button.innerHTML;
            }
            button.innerHTML = '処理中…';
        });

        // 戻る操作などで固まらないよう、一定時間後に復帰させる
        window.setTimeout(function () {
            form.dataset.submitting = '';
            buttons.forEach(function (button) {
                button.disabled = false;
                if (button.dataset.originalText) {
                    button.innerHTML = button.dataset.originalText;
                }
            });
        }, 8000);
    });

    // フラッシュメッセージを一定時間で閉じる／手動でも閉じられる
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-dismiss]');
        if (button) {
            var flash = button.closest('[data-flash]');
            if (flash) {
                flash.remove();
            }
        }
    });

    window.setTimeout(function () {
        document.querySelectorAll('[data-flash]').forEach(function (flash) {
            flash.style.transition = 'opacity .4s';
            flash.style.opacity = '0';
            window.setTimeout(function () { flash.remove(); }, 400);
        });
    }, 8000);

    // ブラウザバックで戻ったときに無効化されたボタンを戻す
    window.addEventListener('pageshow', function (event) {
        if (!event.persisted) {
            return;
        }
        document.querySelectorAll('form[data-submitting="1"]').forEach(function (form) {
            form.dataset.submitting = '';
            form.querySelectorAll('button[disabled]').forEach(function (button) {
                button.disabled = false;
                if (button.dataset.originalText) {
                    button.innerHTML = button.dataset.originalText;
                }
            });
        });
    });
})();
</script>
