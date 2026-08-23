# 更新手順（本番反映）

サーバー構築と初回の動作確認は完了済み。この文書は **2回目以降の更新** だけを扱う。

前提の構成（初回構築時に決めたもの）:

```
~/{ドメイン}/
├── app/          ← Laravel 本体（非公開）
└── public_html/  ← 公開ディレクトリ。app/public の中身を反映する
```

---

## 手順

### 1. データベースをバックアップする

サーバーパネル → MySQLバックアップ。**マイグレーションが含まれる回は必須。**

### 2. メンテナンスに入る

```bash
cd ~/{ドメイン}/app
php artisan down
```

### 3. ソースを取得する

```bash
git pull
```

### 4. 依存パッケージを更新する

`composer.lock` に変更があった回のみ必要。判断がつかなければ毎回実行してよい。

```bash
composer install --no-dev --optimize-autoloader
```

`--no-dev` を必ず付ける。開発用パッケージを本番に入れない。

### 5. データベースを更新する

```bash
php artisan migrate --force
```

- `--force` は本番で必須（確認プロンプトを飛ばす）
- **`migrate:fresh` は絶対に使わない**（全データが消える）

### 6. 静的ファイルを作り直す

```bash
php tools/build-css.php
```

CSSを再生成し、あわせて Service Worker のキャッシュ名を中身から更新する。

> **この手順を飛ばすと、画面の見た目が古いまま直らない。**
> Service Worker は静的ファイルをキャッシュ優先で返すため、
> キャッシュ名が変わらないと利用者のブラウザが古いCSSやオフライン案内を掴み続ける。

### 7. キャッシュを作り直す

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`.env` を書き換えた回は、特に `config:cache` のやり直しを忘れない。やらないと古い設定のまま動く。

### 8. 公開ディレクトリに反映する

```bash
cd ~/{ドメイン}
cp -r app/public/css app/public/icons public_html/
cp app/public/sw.js app/public/manifest.webmanifest app/public/offline.html public_html/
```

> `public_html/index.php` は**上書きしない**。
> 初回構築でパスを書き換えてあるため、`app/public/index.php` で上書きすると壊れる。

### 9. 公開を再開する

```bash
cd ~/{ドメイン}/app
php artisan up
```

---

## 反映後の確認

スマホの実機で見るのが確実。

- [ ] トップページが開く
- [ ] ログインできる
- [ ] **見た目が崩れていない**（崩れていたら手順6と8を見直す）
- [ ] 画像が表示される
- [ ] 今回変更した機能が動く
- [ ] `storage/logs/laravel.log` に新しいエラーが出ていない

古い画面が残る場合は、スーパーリロード（スマホなら一度タブを閉じて開き直す）を試す。
それでも直らなければ手順6が飛んでいる。

---

## 切り戻し

```bash
cd ~/{ドメイン}/app
php artisan down

git log --oneline -5          # 戻したいコミットを確認
git reset --hard {コミットID}

composer install --no-dev --optimize-autoloader
php tools/build-css.php
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache

cd ~/{ドメイン}
cp -r app/public/css app/public/icons public_html/
cp app/public/sw.js app/public/manifest.webmanifest app/public/offline.html public_html/

cd app && php artisan up
```

**マイグレーションを流した後の切り戻しはコードだけでは戻らない。**
DBのバックアップからの復元が必要になるため、手順1を省かないこと。

---

## うまくいかないとき

| 症状 | 見るところ |
| --- | --- |
| 真っ白／500 | `storage/logs/laravel.log`。`storage` と `bootstrap/cache` の書き込み権限（755） |
| 見た目が崩れた | 手順6を実行したか。手順8で `css` と `sw.js` を反映したか |
| 設定を変えたのに反映されない | `php artisan config:cache` のやり直し |
| 画像が出ない | `public_html/storage` のシンボリックリンクが生きているか |
| ルートが見つからない | `php artisan route:cache` のやり直し |
| マイグレーションが途中で失敗 | ログを確認し、DBを復元してから原因を直す。中途半端な状態で `up` しない |
| メンテナンス表示のまま | `php artisan up` を実行したか |

---

## 参考: 定期実行（変更不要）

初回構築で設定済み。イベント開催日の自動進行と未登録リマインドに使っている。

```
0 6 * * * cd ~/{ドメイン}/app && /usr/bin/php8.3 artisan events:advance-scheduled >> storage/logs/cron.log 2>&1
```

1日に複数回まわしても、同じ人に同じリマインドは送らない。

---

## 実施メモ

| 日付 | 内容 | 結果 |
| --- | --- | --- |
|  |  |  |
