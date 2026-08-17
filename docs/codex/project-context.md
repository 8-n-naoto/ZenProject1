# プロジェクトコンテキスト

最終確認: 2026-08-17（Phase 0 初期調査）

## 確定済み業務仕様

- 現時点で、プロジェクト固有の要件定義は未提供。
- 実装済みの画面から、メモの一覧・詳細・作成・編集・削除を扱うアプリケーションであることは確認できる。ただし、これはコードからの観測であり、業務仕様の確定を意味しない。

## 現在の実装

### 技術構成

- Laravel: `laravel/framework ^12.0`（`composer.json` の要求バージョン）。
- PHP: `^8.2`（要求バージョン）。ローカル実行環境では `php` が PATH 未設定のため、実行中バージョンは未確認。
- Composer: Laravel 12 標準構成に Tinker を追加。開発依存は PHPUnit 11、Pint、Sail、Pail、Faker、Collision。
- フロントエンド: Vite 6、`laravel-vite-plugin`、Tailwind CSS 4、Axios。エントリは `resources/css/app.css` と `resources/js/app.js`。
- ビュー: Blade。現状のメモ画面は `resources/views/note/` に個別 HTML として実装されている。
- DB: `config/database.php` のデフォルトは SQLite。SQLite / MySQL / MariaDB / PostgreSQL / SQL Server の接続定義がある。`.env` は存在するが、具体的な設定値は記録しない。
- キュー・キャッシュ・セッション用の標準 Migration が含まれる。

### 認証・認可

- `User` モデルと Laravel 標準の session guard / Eloquent user provider 設定は存在する。
- ログイン、登録、認証ミドルウェアの適用、Policy、権限制御はいずれも未実装。

### ルーティングとコントローラ

- Web ルートのみ。API ルートは未作成。
- `TopController` がメモ CRUD を集約している。

| HTTP | URI | ルート名 | 処理 |
| --- | --- | --- | --- |
| GET | `/` | `top` | メモ一覧 |
| GET | `/memo/{memo}/show` | `show` | メモ詳細 |
| GET | `/memo/store` | `store` | 作成画面 |
| POST | `/memo/store` | `create` | メモ作成 |
| GET | `/memo/{memo}/edit` | `edit` | 編集画面 |
| PATCH | `/memo/{memo}/edit` | `update` | メモ更新 |
| DELETE | `/memo/{memo}/destroy` | `destroy` | メモ削除 |

- `{memo}` は暗黙のルートモデルバインディングを使用する。
- `index()` は `Memo::all()` で一覧を取得する。
- `create()`、`update()`、`destroy()` はリダイレクトではなく Blade ビューを直接返す。

### モデル・DB

- `App\Models\Memo`: `HasFactory` を使用。マスアサイン可能属性は `memo`。他モデルとのリレーションなし。
- `App\Models\User`: Laravel 標準の認証可能モデル。メモとのリレーションなし。
- `memos` テーブル: `id`、`created_at`、`updated_at`、`memo`（text）。
- 標準テーブル: `users`、`password_reset_tokens`、`sessions`、`cache`、`cache_locks`、`jobs`、`job_batches`、`failed_jobs`。

### Service / Action / FormRequest / Policy

- Service、Action、FormRequest、Policy は未作成。
- 入力検証は未実装で、`Request` から `memo` をそのまま利用している。

### Seeder / Factory

- `DatabaseSeeder` は固定のテストユーザー 1 件を作成する。
- `MemoSeeder` は `MemoFactory` によりメモ 5 件を作成するが、`DatabaseSeeder` からは呼ばれていない。
- `MemoFactory` の `memo` は Faker の文で生成する。

### テスト

- PHPUnit 構成。
- Feature テストは `/` の 200 応答だけを確認する `ExampleTest`。
- Unit テストは真偽値だけを確認する `ExampleTest`。
- CRUD、入力検証、認可、エラーケースのテストは未実装。

## 再利用可能な実装

- `Memo` モデル、メモ Migration・Factory・Seeder、暗黙バインディングを含む CRUD ルートおよび Blade 画面。
- Vite / Tailwind のビルド設定。

## 技術的・設計上の注意事項

- 直接関係する DB 構造、モデルリレーション、認証・認可、ルート、状態遷移は、実装前に必ず現在のコードと再照合する。
- 現行 CRUD に FormRequest とバリデーションがないため、空値・長さ・不正入力を制御していない。
- `update()` / `destroy()` が例外を包括的に捕捉して失敗状態をビューへ渡すが、詳細な失敗情報・HTTP ステータス・ログ方針は未整備。
- 更新成功後に渡している `$memo` は、保存後に再取得していない。将来の変更時は画面表示の整合性に注意する。
- 画面内の日本語テキストはターミナル表示時に文字化けして見える箇所がある。編集前に実ファイルの文字コードと表示を確認する。
- `public/note/top.blade.php` に `resources` 側と別の Blade ファイルがある。Laravel の通常のビュー配置ではないため、利用意図を確認してから変更・削除する。
- リスト取得に `Memo::all()` を使用しており、件数増加時には順序指定とページネーションを検討する必要がある。
- PHP 実行環境が PATH にないため、この環境では `php artisan` と Composer script による検証を実行できない。PHP を利用可能にした後にテストを実行する。

## 未実装事項

- 認証画面・認可・メモ所有者の概念。
- API。
- メモ CRUD の入力検証、エラー表示、リダイレクト／フラッシュメッセージ設計。
- CRUD の自動テスト。
- 業務仕様、状態遷移、役割と権限の定義。

## Codex による提案（未確定）

- CRUD を拡張する場合は、まず要件に沿った FormRequest と Feature テストを追加し、成功後の画面遷移を PRG パターンへ統一する。
- ユーザー単位のメモを求める仕様なら、`memos.user_id`、`Memo::user()`、`User::memos()`、Policy を追加する。これは現時点では未確定提案。

## Phase 進捗

- Phase 0: 完了。初期構成を調査し、本ファイルを作成。
- Phase 1 以降: 未着手。開始時は本ファイルを読んだうえで、対象に直接関係するコードだけを再確認する。

## 未解決事項

- メモ機能の正式な業務要件、ユーザーごとの所有権、入力制約、表示仕様。
- 実行用 PHP の場所および実際の DB 接続先・Migration 適用状況（機密値は本ファイルへ記録しない）。

## Phase 1 更新（2026-08-17）

### 実装済み

- メモ CRUD を FormRequest ベースの入力検証へ移行した。`StoreMemoRequest` と `UpdateMemoRequest` は `memo` を必須の文字列・最大 1,000 文字として検証する。
- `TopController` は `Memo::create()` / `update()` / `delete()` を用い、作成・更新・削除後にリダイレクトする PRG フローへ統一した。成功メッセージはセッションフラッシュ `status` を使用する。
- メモ一覧は新しいメモから表示する。空の状態、作成・編集時の入力エラー、詳細・一覧への戻り導線を Blade に追加した。
- メモ CRUD の Feature テストを `tests/Feature/MemoTest.php` に追加した。対象は一覧表示、作成、作成時の必須検証、更新、削除。

### 現在の技術的制約

- 作業環境では `php` が PATH に存在しないため、Phase 1 のテストは未実行。`php artisan test` を実行可能な環境で `MemoTest` を確認する必要がある。

### Phase 進捗（最新）

- Phase 0: 完了。
- Phase 1: メモ CRUD の基本実装完了。認証・所有権・API は正式仕様待ちのため未着手。

## Phase 1 DB基盤（実装済み・仮想環境での未検証）

### 確定済みDB設計

- コミケ共同購入の DB 基盤として、グループ・イベント・カタログ・購入・支払い・精算・承認・通知・履歴を追加した。
- `events` は必ず `groups` に所属し、作成者を `created_by` に保持する。グループの所有者カラムは作成しない。
- `event_members` は `group_members` と別の、イベント単位の参加者固定管理テーブルとして保持する。
- `purchase_results.purchase_assignee_user_id` は、購入結果を登録した購入担当者を表す。複数担当者時の選定ロジックは未実装。
- `purchase_results.planned_quantity` は作成時点の予定数量スナップショットであり、元の購入希望を後で変更しても更新しない。
- 金額はすべて `unsignedBigInteger` の円単位。価格変更専用テーブルおよび過不足数量専用テーブルは作成しない。

### 追加Migration

- `2026_08_17_000000_add_community_fields_to_users_table.php`: `users.user_id`（nullable + unique）と `deleted_at` を追加。
- `2026_08_17_000001_create_group_management_tables.php`: `groups`、`group_members`、`invitations`。
- `2026_08_17_000002_create_event_and_catalog_tables.php`: `events`、`event_days`、`event_members`、`circles`、`event_circles`、`products`、`event_products`。
- `2026_08_17_000003_create_purchase_tables.php`: 個人・共同購入、担当者、購入結果、不足、過剰引取。
- `2026_08_17_000004_create_financial_and_workflow_tables.php`: 支払い、精算、承認、通知、変更履歴。

### 制約・インデックス

- 主な UNIQUE: `users.user_id`、`group_members(group_id,user_id)`、`event_days(event_id,event_date)`、`event_members(event_id,user_id)`、`event_circles(event_id,circle_id)`、`event_products(event_circle_id,product_id)`、`personal_purchases(event_id,event_product_id,user_id)`、共同購入明細・担当者・不足対象者の重複防止。
- `purchase_results` は `personal_purchase_id` と `shared_purchase_item_id` の片方だけを設定する。SQLite では INSERT / UPDATE トリガー、その他の DB では CHECK 制約を使用する。
- 金額・購入結果数量の非負制約は SQLite ではトリガー、その他の DB では CHECK 制約で実装する。
- 主要検索用 index はイベント状態、イベント日時、サークル名称、商品状態、支払い状態、承認対象、通知、変更履歴に設定した。
- すべての FK は原則 `RESTRICT`。過去の業務履歴を破壊する CASCADE は設定しない。

### soft delete対象

- `users`、`groups`、`events`、`circles`、`products`、`event_products`、`shared_purchase_items`。

### Model / Factory / Test

- 最小 Model: `Group`、`Event`、`Circle`、`Product`、`EventCircle`、`EventProduct`、`PersonalPurchase`、`SharedPurchase`、`SharedPurchaseItem`、`PurchaseResult`、`Payment`。`User` には `SoftDeletes` と `user_id` を追加。
- 最小 Factory: 上記の基盤エンティティと購入結果用。`UserFactory` は `user_id` を生成する。
- `tests/Feature/DatabaseSchemaTest.php` はテーブル/カラム、UNIQUE、FK、購入結果の排他、金額非負、soft delete を検証する。

### 検証状況と未解決事項

- Codex環境では PHP / Laravel を実行していない。仮想環境への接続方法が未記録のため、`php artisan migrate:status`、`php artisan migrate`、`php artisan test` は未実行。
- 仮想環境で Migration を実行する前に、既存 DB の Migration 状態を必ず確認する。破壊的コマンドは使用しない。
- 最高責任者1名・責任者最低1名・承認過半数・イベント時間による変更制限・担当者の選定・通知重複抑止は後続Phaseの業務ロジックで保証する。

### 仮想環境接続の調査（2026-08-17）

- Codex 実行環境は Windows。プロジェクトのパスは `D:\work\zen1Local\ZenProject1`。
- Codex 実行環境では PHP と Composer を利用できない。SSH、Docker、Docker Compose、WSL のコマンド自体は存在する。
- プロジェクト内の README、docs、scripts、Docker Compose / Dockerfile、Vagrant、CI 設定を安全に確認したが、Laravel 仮想環境の接続先または実行手順は見つからなかった。README は Laravel 標準文書のみ。
- 接続先・認証情報を推測せず、仮想環境上の `migrate:status`、`migrate`、`test` は未実行。接続方法の提示後に実施する。

### Phase 状態（2026-08-17）

- Phase 1: ローカル実装完了／仮想環境検証待ち。
- ローカル最終静的レビューでは、Migration の依存順、FK、nullable、UNIQUE、INDEX、SQLite トリガーおよび他 DB 用 CHECK、soft delete、金額・数量型、購入結果対象排他、`purchase_assignee_user_id`、予定数量スナップショット、既存 Migration 非変更を確認した。
- Factory はイベント関連の参照整合性を保ち、`PurchaseResultFactory` は元の個人購入から `event_product_id` と `planned_quantity` を引き継ぐ。
- `git diff --check` は成功。仮想環境での Migration / Test はユーザーが実行する役割分担であり、結果受領後に本ファイルへ反映する。

### MySQL識別子長エラー対応（2026-08-18）

- 仮想環境（Laravel 12.10.2 / PHP 8.2.32 / MySQL 8.0.46）で `2026_08_17_000003_create_purchase_tables` の実行中、MySQL の64文字識別子上限により失敗した。
- 原因は `product_purchase_assignees_shared_purchase_item_id_user_id_unique`（65文字）の Laravel 自動生成 UNIQUE 名。
- `2026_08_17_000003_create_purchase_tables.php` の業務構造は変更せず、複合 UNIQUE / INDEX に短い明示名を設定した。主な名前は `ppa_item_user_uq`、`spi_purchase_product_uq`、`prsu_result_user_uq`、`pp_event_product_user_uq`。
- 全 Phase 1 Migration を確認し、64文字超の自動 UNIQUE / INDEX 名は他に存在しない。自動 FK 名の最長は58文字であり、MySQL上限内。
- MySQL は DDL を暗黙コミットするため、失敗した未記録 Migration が途中までテーブルを作成している可能性がある。再実行前に `migrate:status` と、`000003` が作成するテーブルの存在を読み取り専用で確認する。途中テーブルが存在する場合は、破壊的操作をせず再実行前に対応を判断する。
