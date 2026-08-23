# プロジェクトコンテキスト（Claude管理）

最終更新: 2026-08-22（Phase 1〜10 実装完了時点）
仕様の正: `docs/claude/definition-of-done.md`（完成定義書）
旧文書 `docs/codex/project-context.md`（2026-08-17/18）はDB設計の意図の参照のみに使う。

> **運用ルール**: 実装を始める前に本書と完成定義書を読む。仕様を変える必要が出たら、実装より先に完成定義書を更新する。

---

## 1. 技術構成

- Laravel 12 / PHP 8.2 以上（検証は PHP 8.4 + SQLite、本番想定は PHP 8.2 + MySQL 8.0）
- 認証は自作コントローラ（Breeze / Fortify 不使用）。ログインは **user_id + パスワード**、メール認証必須
- ビュー: Blade。**共通レイアウト `resources/views/components/app-layout.blade.php`** に統一（ゲスト画面は `guest-layout`）
- CSS: **Tailwind のクラス名で記述**し、`php tools/build-css.php` が使用クラスだけの `public/css/app.css` を生成する。
  Node が使える環境では `npm run build` で本家 Tailwind に切り替わる（レイアウトが `public/build/manifest.json` の有無で自動判定）
- 見た目（配色・書体・角丸）はユーザーが3種類から選ぶ。`resources/css/theme.css` が `app.css` の後に重なる（→ 9章「見た目の切り替え」）
- ロケール: `ja`。`lang/ja/` にバリデーション・認証・パスワードのメッセージ
- 認可: **Policy に一元化**（`Gate::policy` を `AppServiceProvider` で登録）
- 業務ロジックは `app/Services/` に集約。ルール違反は `BusinessRuleException` で表現し、コントローラでフォームエラーに変換する

## 2. ディレクトリ

| パス | 内容 |
| --- | --- |
| `app/Enums` | GroupRole / InvitationStatus / EventStatus / ProductStatus / PurchaseResultStatus / PaymentStatus / SettlementStatus / ApprovalStatus / ApprovalActionType / Theme |
| `app/Policies` | GroupPolicy / EventPolicy / EventCirclePolicy / PurchasePolicy / SettlementPolicy |
| `app/Services` | GroupMemberService / EventService / CatalogService / PurchaseListService / PurchaseResultService / SettlementService / ApprovalService / NotificationService / ChangeHistoryService / AccountDeletionGuard |
| `app/Support` | TextNormalizer（サークル名の表記ゆれ吸収） |
| `resources/views/components` | app-layout / guest-layout / bottom-nav / card / button / input / textarea / badge / avatar / alert / empty-state / group-icon / event-card / event-steps / styles |
| `tools/build-css.php` | 同梱CSSのビルド、テーマCSSの複写、Service Worker のキャッシュ名更新 |
| `resources/css/theme.css` | 見た目の切り替え（`--zt-*` トークンと既存ユーティリティの読み替え）。手書き。`public/css/theme.css` は複写物 |

## 3. ドメインモデルの考え方

- **個人購入リスト（personal_purchases）= 各参加者の「欲しいもの」**。これが需要の記録。
- **共同購入リスト（shared_purchases / shared_purchase_items）= サークル単位でまとめて買う数**。
  参加者の希望を合計して作り（`syncSharedPurchaseFromWishes`）、責任者が手動調整もできる。希望が0のサークルには作らない。
- **購入結果（purchase_results）** は共同購入明細に1件、または個人購入に1件（DBのCHECK制約で排他）。
  - 不足 → `purchase_result_shortage_users` に「誰が何点受け取れないか」
  - 超過 → `excess_takeovers` に「誰が引き取るか」（1件のみ）
- **精算** は購入結果から「受益者 → 立替者」の債務を組み立て、純額を相殺して最小回数の送金（settlements）を生成する。
  `payments` は送金1件に対する支払い記録で、`payment_items` にどの購入結果の何点分かを記録する。

## 4. 権限（要点）

| | 最高責任者 | 責任者 | 一般メンバー |
| --- | --- | --- | --- |
| グループ編集 | ○ | ○ | × |
| グループ削除 | ○ | × | × |
| 招待・招待取消 | ○ | ○ | × |
| 役割変更 | ○ | × | × |
| 除名 | 全ロール | 一般のみ | × |
| イベント作成・編集 | ○ | ○ | × |
| 状態を進める | ○ | ○（精算完了を除く） | × |
| 状態を戻す | ○ | × | × |

- 責任者・最高責任者は **在籍者が1人になったら脱退・除名・降格できない**（`GroupMemberService` に集約）。
- 脱退・除名済み（`left_at` が入っている）ユーザーは全ての判定で非メンバー扱い。再招待で復帰でき、役割は一般メンバーにリセットされる。
- グループ作成直後は責任者0人を許容する（完成定義書の決定）。ただしイベント作成には責任者が1人以上必要。

## 5. イベントの状態と操作

`準備中 → 受付中 → 確定済 → 開催中 → 精算中 → 完了`

- 準備中→受付中: 責任者が直接実行（開催日と責任者の存在を検証）
- 受付中→確定済: **承認フロー**（参加者1人以上、共同購入リストに確定担当者が必要）。`fixed_at` を記録し内容をロック
- 確定済→開催中 / 開催中→精算中: 責任者が直接実行。精算中に入ると精算リストを自動生成（全明細に購入結果が必要）
- 精算中→完了: **承認フロー**（最高責任者のみ申請可、未精算の送金が残っていると不可）
- 完了→精算中（再オープン）: **承認フロー**（最高責任者のみ）
- 確定後の内容変更は `event.unlock` の承認で一時的に解禁し、`relock` で再ロックする

## 6. 承認フロー

- 対象: イベントの確定 / 精算の完了 / 再オープン / 確定後の内容変更
- 可決条件: 責任者以上の**過半数**の賛成。申請者の1票は自動で賛成。**最高責任者の賛成で即時可決**
- 否決も過半数で確定。可決すると `ApprovalService::apply()` が実際の操作を実行する

## 7. テスト

`php artisan test` — **511 tests / 1,671 assertions すべてPASS**（SQLite :memory:、2026-08-23時点。PHP 8.5 では PDO 定数の DEPR 警告が出るが失敗ではない）

| 区分 | 主な内容 |
| --- | --- |
| Unit | GroupRole / EventStatus のロジック、最小送金アルゴリズム |
| Feature/Auth | 登録・ログイン・メール認証 |
| Feature/Account | パスワード再設定・プロフィール・退会 |
| Feature/Group | 作成・編集・削除・画像・招待・再加入・役割・脱退・除名・アクセス制御 |
| Feature/Event | 作成・状態遷移・参加表明・閲覧制御 |
| Feature/Catalog | サークル重複検知・商品CRUD |
| Feature/Purchase | 個人購入・共同購入・担当者・購入結果（不足／超過） |
| Feature/Settlement | 相殺・送金生成・支払い報告と受取確認 |
| Feature/Approval | 過半数承認・即時可決・否決・解禁 |
| Feature | 通知・変更履歴・画面描画・デモSeeder・**通しシナリオ** |
| Feature/Security | 認可レビューで見つかった抜け道の回帰テスト |
| Feature/Settlement | 金額の整合性（明細数量・訂正時の再生成・完了後の凍結） |

`tests/Support/CreatesGroups.php` にグループ／イベント／カタログ／精算までを組み立てるヘルパーがある。

## 8. 手動確認

```bash
php artisan migrate
php artisan storage:link
php artisan db:seed --class=DemoSeeder
php artisan serve
```

ログインID `owner001` / `leader01` / `buyer001` / `member01`（パスワード `password`）。

## 9. ブラッシュアップで追加した機能（2026-08-23）

| 機能 | 実装 |
| --- | --- |
| 当日の買い物リスト | `ShoppingListService` / `ShoppingListController`。担当サークルを `BoothSorter` で配置順に並べ、ワンタップで購入結果を登録する |
| やること一覧 | `UserTaskService`。招待・参加表明・購入希望・購入結果・精算・承認・責任者不在をホームに集約 |
| 商品単位の購入担当 | `PurchaseListService::syncProductAssignees()`。1サークルを複数人で分担する場合に使う（`product_purchase_assignees`） |
| 画像アップロード | `ImageStorageService`。グループ画像・サークルの配置マップ・商品画像 |
| イベントの複製 | `EventService::duplicate()`。サークル・商品・画像を引き継ぎ、購入データは引き継がない |
| 検索・並び替え | サークル一覧の検索（名称・配置・商品名）と配置順／名前順／登録順 |
| ページネーション | 通知・変更履歴（`x-paginator`） |
| 精算の共有テキスト | `SettlementService::shareText()` とコピーボタン |
| 定期実行 | `events:advance-scheduled`。開催日到来で自動的に開催中へ、購入希望・購入結果のリマインド通知 |
| エラーページ | 403 / 404 / 419 / 429 / 500 / 503 の日本語ページ |
| 二重送信防止 | `x-behaviour`（送信中はボタンを無効化、フラッシュの自動消去） |

### 実用性向上（2026-08-23 第7弾）
| 機能 | 実装 |
| --- | --- |
| CSVエクスポート | `ExportService` / `ExportController`。購入結果・精算リストを UTF-8 BOM 付きCSVで出力。CSVインジェクション対策あり |
| 前回の購入希望の取り込み | `PurchaseListService::copyWishesFrom()`。サークル名・商品名の正規化キー（`TextNormalizer`）で突き合わせ、入力済みは上書きしない |
| 未精算のまとめ | `SettlementService::outstandingFor()` / `settlements.mine`。参加中の全グループを横断して集計し、ホームにも要約を出す |

### 会場での使い勝手（2026-08-23 第8弾）
| 機能 | 実装 |
| --- | --- |
| PWA | `public/manifest.webmanifest` / `public/sw.js` / `public/offline.html`。アイコンは `php tools/build-icons.php` で生成する（GD、外部ツール不要） |
| Service Worker の方針 | 静的ファイルのみキャッシュ優先。画面遷移は常にネットワーク優先で、失敗時に `offline.html` を返す。**ログイン後のHTMLはキャッシュしない**（共有端末での情報漏れを防ぐため） |
| 配置マップの目印 | `event_circles.map_x` / `map_y`（0〜100の％）。編集画面で画像をタップしてピンを置く。画像の差し替え・削除でピンも消える |
| 画面スリープ防止 | 買い物リストの「画面を消さない」トグル（Screen Wake Lock API、非対応端末では非表示） |

### 独立レビューでの指摘と対応（2026-08-23 第9弾）
| 指摘 | 対応 |
| --- | --- |
| 配置マップ画像を差し替えても古いピンが残る | `CatalogService::updateCircle()` を `elseif` に修正。フォーム側でもファイル選択・削除チェック時に隠しフィールドを消す |
| 購入結果CSVに他人の個人購入希望が含まれる（画面では見えない情報） | `purchaseResultsCsv(Event, User $viewer)` にして、個人購入は本人の分だけ出力する |
| 精算一覧で1件ごとにクエリが増える（`reportedPayment()` と Policy） | `reportedPayment()` は読み込み済みの `payments` を使う。`event.group.activeMembers` を eager load |
| 未精算を残したまま脱退・除名・退会できてしまい、イベントが完了できなくなる | `GroupMemberService::leave()/remove()` と `AccountDeletionGuard` で未精算をブロック |
| 受取確認ボタンが Policy を見ずに描画される | `@can('confirm', $reported)` を追加 |
| Service Worker が `/storage/` のアップロード画像をキャッシュしてしまう | `/storage/` を除外。オフライン用HTMLが無い場合のフォールバック `Response` も追加 |
| ホームに「開けないイベント」が並ぶ | `EventPolicy::view` で絞り込む |
| 購入希望の取り込みを二重送信すると一意制約で500 | `insertOrIgnore` にして冪等にする |

### UI/UXレビューでの指摘と対応（2026-08-23 第10弾）
| 指摘 | 対応 |
| --- | --- |
| 金額が確定する操作（受取確認・受取拒否）、承認の投票、招待の辞退、担当者を外す、招待取消、サークル一括「買えた」に確認が無い | すべて `onsubmit="return confirm(...)"` を追加。`tests/Feature/UiConventionTest.php` で全 Blade を走査し、破壊的な form に確認があることを検証する |
| 「購入希望から再集計する」が担当者の割り当てを黙って消す | 画面に警告を出し、確認ダイアログでも明記 |
| プロフィール画面に `id="password"` が2つあり、ラベルとエラーが混ざる | 退会確認は `deletion_password` にフィールド名を分離 |
| 買い物リストから購入結果を登録すると一覧に飛ばされて戻れない | `?from=shopping` を付け、戻る先と保存後のリダイレクトを買い物リストにする |
| タップ領域が28pxしかない | ボタンに最小の高さ（sm=40px / md=44px / lg=48px）。文字リンクにも余白を追加 |
| 用語のゆれ（買いたいもの／欲しいもの／購入希望、更新／保存する／変更を保存） | 「購入希望」「変更を保存」に統一 |
| サークルはあるが商品が0件のとき購入希望画面が真っ白 | 空状態を表示 |
| iPhoneのホームインジケータにボトムナビが隠れる | `pb-safe`（`env(safe-area-inset-bottom)`）を追加 |
| スキップリンクが `sr-only` のままでフォーカスしても見えない | `focus:not-sr-only` を追加 |
| リンク先の無いお知らせが `<a href なし>` で描画される | `div` で描画する |
| `border-dashed` がCSSジェネレータに無く、破線がすべて実線になる | `border-dashed` / `border-dotted` / `min-h-*` / `pb-safe` / `not-sr-only` を追加 |
| サークル詳細で商品を2件以上持つと遅延ロード例外（500） | `eventProducts.product` を eager load。件数を増やした回帰テストを追加 |

### テスト網羅性レビューでの指摘と対応（2026-08-23 第11弾）
| 指摘 | 対応 |
| --- | --- |
| 責任者が購入結果を訂正すると立替者（返金先）が責任者にすり替わる | `PurchaseResultService::resolvePayer()` を追加し、既存の立替者を保つ。初回登録時は確定済みの担当者を使う |
| 精算完了後でも「精算中→開催中」に戻せば購入結果を書き換えられ、精算リストと永久に食い違う | `PurchasePolicy` の凍結条件から状態の限定を外し、`EventService::revert()` で精算中からの差し戻しを禁止 |
| 精算リスト生成に失敗しても状態だけ進んでしまう | `advance()` の状態更新と精算リスト生成を同一トランザクションに |
| 賛否同数で承認申請が永久に固まる（取り下げ手段なし） | 最高責任者の反対は即時否決。`approvals.withdraw` を追加（申請者・最高責任者） |
| 承認者数が変わっても再判定されない | `ApprovalService::reevaluatePending()` を `GroupMemberService` の脱退・除名・役割変更から呼ぶ |
| 除名・降格した人の票が残って可決を左右する | `evaluate()` で現在の責任者以上の票だけを数える |
| 相殺の端数で「0点 ¥500」と表示される | 0点のときは「相殺分の一部」と表示。あわせて内訳のN+1も解消 |

### アカウント・入力まわりのレビューと対応（2026-08-23 第12弾）
| 指摘 | 対応 |
| --- | --- |
| メールアドレスを変えても旧アドレス宛の再設定トークンが残り、そのアドレスで登録した別人のパスワードを変えられる | `ProfileController::update()` で旧アドレスのトークンを削除する |
| メールアドレスの変更にパスワード確認が無く、セッションを盗まれるとアカウントごと奪われる | `email_current_password`（`current_password` ルール）を必須にする |
| 退会者が関わる精算・共同購入の画面が500になる | User への `belongsTo` をすべて `withTrashed()` に。`displayName()` に「（退会済み）」を付け、`x-avatar` を null 安全に |
| 一括登録で全角カンマ「，」が区切りにならず、行全体が1件のサークル名になる | 区切り文字に「，」を追加 |
| 一括登録がマイナス価格を正の価格として受け入れる | マイナス記号を検出してエラーにする |
| 一括登録だけ商品名の長さ制限が無い | 100文字の上限を追加 |
| `BoothSorter` が「ホール」の長音符を区切りと誤認して並び順が壊れる | 「ホール」を先に取り除いてから解析する |
| 購入結果リマインドが同日に何度も送られる | 購入希望リマインドと同じ当日重複チェックを追加 |
| パスワード再設定の送信・実行に回数制限が無い | 両方に `throttle:6,1` |
| 一括登録が1行あたり6クエリ | 商品名の重複判定をメモリで行い、`force` 時は重複検索を省く（60行で301→約2.5倍改善） |
| `invitation.sent` に日本語文が無い | 文言を追加し、`tests/Feature/MessageKeyCoverageTest.php` で対応漏れを機械的に検出する |

### グループ・入力まわりのレビューと対応（2026-08-23 第13弾）
| 指摘 | 対応 |
| --- | --- |
| グループを削除すると、招待された人の「招待」画面が永久に500になる | `Invitation::group()` を `withTrashed()` に |
| 最後の責任者を最高責任者に「昇格」させると責任者が0人になる | 昇格・降格の両方で `assertNotLastOfRequiredRole()` を通す |
| ユーザー検索で「_」1文字を打つと全登録者のログインID・氏名が見える | `SearchKeyword` を追加。ワイルドカードをエスケープし、ユーザー検索は2文字以上を必須に |
| 長すぎる検索キーワードでDBエラー（500） | 100文字に丸める |
| `days` に配列以外を送ると500 | `prepareForValidation()` で型を確認し、バリデーションに任せる |
| 「画像を削除する」のチェックを外し忘れて新しい画像を選ぶと両方消える | `ImageStorageService::sync()` で新しい画像を優先 |
| グループ・商品を削除しても画像ファイルが残る | 削除時に `images->delete()` を呼ぶ |
| 論理削除済みのイベントしか無いグループが削除できる | `events()->withTrashed()->exists()` で判定 |
| 配列パラメータに件数上限が無く、1件1クエリで数千クエリ発行できる | `max:` を追加し、購入希望の保存は商品をまとめて取得する |
| 範囲外のページ番号が「99999 / 2」と表示される | `x-paginator` で範囲外を検知して案内を出す |

### 動作確認での指摘と対応（2026-08-23 第14弾）
| 指摘 | 対応 |
| --- | --- |
| 精算で支払い報告はできるが、受け取る側に「受け取った」ボタンが表示されず受取確認ができない | `Payment::class` の Policy が未登録で、ビューの `can('confirm', $payment)` が常に false だった。`AppServiceProvider` に `Gate::policy(Payment::class, SettlementPolicy::class)` を追加。ボタン表示を画面描画で検証する回帰テストを `SettlementTest` に追加（POST 直叩きのテストでは検出できなかった） |

### 精算の改善（2026-08-23）
| 機能 | 実装 |
| --- | --- |
| 収支の内訳の詳細ページ | `SettlementService::breakdownFor()` / `settlements.breakdown`（`/events/{event}/settlements/breakdown/{user}`）。精算リストの「収支の内訳」の行から、そのメンバーの立替（誰の分を何点）と購入（誰が立替か）を商品単位で確認できる。個人購入は購入者本人が立替者になるため債務が発生せず、この内訳には現れない（summary() と同じ集計基準） |

### Notionタスクの消化（2026-08-23）
| 機能 | 実装 |
| --- | --- |
| 招待リンク（合い言葉） | `GroupInviteLinkService` / `JoinController` / `group_invite_links`。未ログインでも招待内容を表示し、ログイン・登録・メール認証のあと自動で招待画面に戻る（`JoinController::SESSION_KEY`） |
| 予算と残高 | `BudgetService` / `event_members.budget`。受付中は「予定」、開催中以降は「実績」で差し引く。買い物リストの sticky ヘッダに常時表示 |
| 巡回ルート | `ShoppingRouteService` / `shopping_routes` / `event_circles.sellout_risk`。既定順・手動並べ替え・共有テキスト |
| 会場マップ | `VenueMapController` / `events.map_image_path` / `event_circles.venue_map_x,y`。拡大縮小（ボタン＋ピンチ）、購入済の色分け |
| 電波対策 | `x-offline-guard`（入力の保存・復元、圏外送信の抑止、圏外バナー）と、買い物リストの控えを `offline.html` で表示 |
| 全画面の描画確認 | `tests/Feature/AllScreensRenderTest.php`。GETルートを自動で列挙して描画し、500 が出ないことを確認する（ルートを足すと自動で対象に入る） |

### 見た目の切り替え（2026-08-23）
| 機能 | 実装 |
| --- | --- |
| 3種類のデザインをユーザーごとに選べる | `App\Enums\Theme`（soft / venue / editorial）と `users.theme`。アカウント画面の「デザイン」カードから切り替える（`profile.theme.update`）。既定は `soft` |

考え方: **Blade のクラス名は一切変えていない**。`resources/css/theme.css` が
`html[data-theme="…"]` ごとに `--zt-*` の意味づけトークン（背景・面・文字・アクセント・角丸・影・書体）を定義し、
既存の Tailwind ユーティリティクラス（`.bg-white` `.text-slate-500` `.bg-sky-600` など）をそのトークンに読み替える。
`app.css` より **後に** 読み込むこと、および `html[data-theme]` を前置して詳細度で勝つことが前提になっている。
ヘッダ・本文・下部ナビは `data-app-header` / `data-app-main` / `data-app-nav` で指し、クラス名に依存しない。

注意点:
- 詳細度はクラスが型セレクタより強い。`body` を塗るには `html[data-theme] body.bg-slate-100` のように
  相手と同じだけクラスを含める必要がある（`html[data-theme] body` だけでは負ける）
- 塗りつぶし面（`.bg-sky-600` など）は背景色と文字色を同時に指定するため、
  文字色の読み替えより **後ろ** に書く。順番を入れ替えると venue（暗色）で文字が白のまま潰れる
- `users.theme` は Eloquent の enum キャストを使わない。壊れた値でも `Theme::fromValue()` が既定に落とすため、
  DBを手で書き換えても画面が落ちない
- 書体は Google Fonts。読めない環境では `theme.css` 側のフォールバックで端末標準の書体になる
- `resources/css/theme.css` が正。`php tools/build-css.php` が `public/css/theme.css` へ複写する。
  複写漏れは `tests/Feature/Account/ThemeTest.php` が内容一致で検出する

ドキュメント: `docs/er-diagram.md` / `docs/screens.md` / `docs/deployment.md`（更新手順） / `docs/settlement-methods.md`

**デプロイ時の注意**: `php tools/build-css.php` は CSS の再生成と `resources/css/theme.css` の複写に加えて、
`public/sw.js` のキャッシュ名をプリキャッシュ対象の内容ハッシュで書き換える。
これを飛ばすと Service Worker が古いCSS・古いオフライン案内を返し続ける。
`tests/Feature/PwaTest.php` でキャッシュ名と中身の一致を検証している。

### セキュリティ面
- ログインは「ログインID + IP」単位で 5回/60秒 のレート制限（`LoginRequest::ensureIsNotRateLimited()`）。
  成功時にカウンタをリセットし、ロックアウト時は `Illuminate\Auth\Events\Lockout` を発火する
- パスワード変更時は `Auth::logoutOtherDevices()` で他端末のセッションを無効化する。
  これを機能させるため `AuthenticateSession` ミドルウェアを web グループに追加している

### 品質面
- `Model::preventLazyLoading()` を本番以外で有効化し、N+1クエリを検出して例外にしている。
  リレーションを辿る前に `loadMissing()` を呼ぶこと。
- `php tools/lint-closures.php` でクロージャの `use` 漏れを検出できる（テストからも実行している）。

## 10. 既知の制約・今後の課題

- メール送信は認証メールのみ（`MAIL_MAILER=log`）。通知はアプリ内のみ
- 未精算が1件でも残っているメンバーは、脱退・除名・退会ができない（精算を完了させてから）
- 退会したユーザーのログインID・メールアドレスは再利用できない（`unique` 制約はソフトデリート行も見るため）
- `Model::preventLazyLoading()` は「複数行を取得したクエリ」にしか働かない。
  ルートモデルバインディングで取得した単一モデルの遅延ロードは検出されないため、
  一覧系は `tests/Feature/QueryCountTest.php` で件数を増やしてもクエリが増えないことを確認している
- 承認フローは提案内容（変更後の値）を保持しない。「解禁 → 責任者が変更 → 再ロック」という運用で表現している
- 受取確認が1件でも完了したイベントでは、購入結果の訂正と精算リストの再生成ができない（金銭が動いた後の遡及変更を防ぐため）
- 送金回数の最小化はヒューリスティック（完全一致の相殺 → 大きい順の貪欲法）。厳密な最小回数は保証しない
- 本番デプロイ手順は未整備（完成定義の対象外）
