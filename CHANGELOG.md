# Changelog

All notable changes to this project will be documented in this file.

## [0.5.9] - 2026-09-17

### EN

#### Changed
- Non-public items are never recommended, to anyone. Candidate lookups previously returned whatever the current identity was allowed to read, so a signed-in editor or administrator was shown recommendations drawn from a larger pool than visitors get. The block lives on public site pages and answers "what else might a visitor want", so an item no visitor can reach is not a valid answer; showing them also misled staff reviewing the feature, who were judging a list no visitor would see. The constraint is applied at a single wrapper around the API rather than on each query, so a lookup added later to the scoring engine cannot omit it. The random control arm draws from the same restricted pool.

### 日本語

#### 変更
- 非公開資料を誰に対しても推薦しないようにしました。候補の検索は従来、閲覧者の権限で読める資料をそのまま返していたため、ログインした編集者・管理者には一般利用者より広い母集団から推薦が選ばれていました。このブロックは公開サイト上にあり「一般利用者が次に見たいものは何か」に答えるものなので、誰も到達できない資料は答えになりません。職員が推薦の質を点検する際に、実際には表示されない一覧を評価してしまう問題もありました。制約は個々のクエリではなく API のラッパ1箇所で適用するため、後からスコアリングに検索を追加しても漏れません。ランダム対照群も同じ母集団から抽出します。

## [0.5.8] - 2026-09-17

### EN

#### Added
- Signed-in visits are excluded from the log by default (`similaritems.log.skip_authenticated`). A signed-in editor or administrator is shown recommendations drawn from a pool that includes private items, so their impressions are not comparable with a visitor's - and nothing marks them apart afterwards unless user ids are being stored. Both impressions and events are skipped; recommendations themselves are unaffected. Identity is taken from the session in the event endpoint, never from the payload, so a client cannot claim to be anonymous to get past it.

### 日本語

#### 追加
- ログイン中の閲覧を既定で記録対象から外しました（`similaritems.log.skip_authenticated`）。ログインした編集者・管理者には非公開資料を含む母集団から推薦が選ばれるため、一般利用者のインプレッションとは比較できません。ユーザ ID を記録していない限り、後から両者を判別する手段もありません。インプレッション・イベントの双方を記録せず、推薦の表示自体には影響しません。イベント受付では利用者の判定をセッションから行い、送信内容からは取らないため、匿名を偽って回避することはできません。

## [0.5.7] - 2026-09-17

### EN

#### Fixed
- "Most clicked recommendations" showed bare item ids. Click events deliberately record only the id, so the titles for that short list are now looked up for display. They are read from the resource table rather than through the API, because the API filters by visibility and would have made non-public items look deleted.

#### Changed
- The manual now explains what a beacon is and why it matters: impressions are recorded by the server, but whether the block reached the screen and which link was pressed are only knowable inside the visitor's browser, so a small script reports them. Beacons are not guaranteed to arrive, which is why the visibility rate and both click-through rates are lower bounds while the impression count is not affected.

### 日本語

#### 修正
- 「Most clicked recommendations」がアイテム ID しか表示していませんでした。クリックイベントは意図的に ID のみを記録しているため、この短い一覧に限り表示時にタイトルを引くようにしました。API ではなく resource テーブルから読みます。API は公開範囲で絞り込むため、非公開アイテムが削除済みのように見えてしまうからです。

#### 変更
- マニュアルに「ビーコン」の説明を追加しました。インプレッションはサーバが自分で記録できるが、ブロックが画面に入ったか・どのリンクが押されたかは閲覧者の画面の中でしか分からないため、小さなスクリプトが知らせている、という仕組みと、ビーコンは確実には届かないので可視化率・CTR はいずれも下限値であり、インプレッション数は影響を受けないこと。

## [0.5.6] - 2026-09-16

### EN

#### Added
- Selective deletion on the impression list: tick the rows to remove and press "Delete selected". The events attached to those impressions are removed with them. POST only, CSRF protected, with a confirmation prompt, and the current filters and page are preserved on return.
  - Deleting an impression that others were chained to would leave them pointing at a row that no longer exists, so the affected chains are rebuilt afterwards: an impression whose parent is gone becomes a chain root again (its `parent_event_id`, `parent_impression_id` and `chain_key` are reset, `hop_depth` returns to 0, and an `entry_kind` of `similar_items` becomes unknown because the link that explained it is gone), and everything below it has its depth and chain key recomputed. The result message says how many were detached.

### 日本語

#### 追加
- インプレッション一覧に選択削除を追加しました。削除したい行をチェックして「Delete selected」を押します。対象インプレッションに紐づくイベントも一緒に削除されます。POST 限定・CSRF 保護・確認ダイアログ付きで、戻り先では絞り込み条件とページが維持されます。
  - 連鎖の親にあたるインプレッションを削除すると、子が存在しない行を指したままになるため、影響を受けた連鎖を削除後に再構築します。親を失ったインプレッションは再び連鎖の起点になり（`parent_event_id`・`parent_impression_id`・`chain_key` を再設定、`hop_depth` は 0 に戻し、`entry_kind` が `similar_items` だったものは根拠が失われるため未判定に）、その下流は深度と連鎖キーを再計算します。切り離された件数は結果メッセージに表示します。

## [0.5.5] - 2026-09-16

### EN

#### Fixed
- The Viewed card reported only two of the three possible outcomes, so the figures did not add up to the impression count. An impression whose browser never reported at all - typically because the visitor left before the block was rendered, so the client never had anything to observe - fell into neither "viewed" nor "never on screen". The count is now shown as `N not reported`, on the summary card and in the per-arm table, and the three add up to the impression total. The manual explains the split and notes that the visibility rate is therefore a lower bound, and that a large unreported count points at the response time.

### 日本語

#### 修正
- Viewed カードが3区分のうち2つしか表示しておらず、合計がインプレッション数と一致しませんでした。ブラウザから報告が届かなかったインプレッション（多くは推薦の計算が終わる前に離脱され、クライアントが観測対象を認識できなかったもの）が、「可視化」にも「画面に入らなかった」にも入っていませんでした。`N not reported` として概況カードとアーム別表に表示し、3区分の合計がインプレッション数と一致するようにしました。マニュアルにも区分の説明を追加し、可視化率が下限値であること、not reported が多い場合は応答時間を疑うべきことを明記しました。

## [0.5.4] - 2026-09-16

### EN

#### Added
- A manual for the log dashboard, reachable from every log page ("How to read this page", `/admin/similar-items/logs/help`). It explains what each figure counts and how to read it: the distinction between rendered and actually seen, the two click-through rates and when to use each, why the rank table divides by the impressions that actually offered that rank, what the browsing-depth figures mean, what each entry channel means and why an unclassified one must not be read as "direct", what the control arms compare, and the caveats that matter when interpreting small samples, missing beacons and bot filtering. Written to be readable before any data has been collected.

### 日本語

#### 追加
- ログ画面の読み方マニュアルを追加しました。各ログ画面から「How to read this page」で開けます（`/admin/similar-items/logs/help`）。各数値が何を数えているかと読み方を説明します：「表示された」と「実際に見られた」の違い、2種類の CTR の使い分け、順位別表の分母が「その順位を実際に提示した回数」である理由、回遊深度の意味、流入経路の各値と「未判定」を `direct` と混同してはいけない理由、対照群アームが何を比較しているか、少数サンプル・ビーコン欠測・ボット判定に関する注意。データが1件も無い状態でも読めるように書いてあります。

## [0.5.3] - 2026-09-16

### EN

#### Fixed
- Internal navigation was filed as `external`. The self-host comparison used `HTTP_HOST`, which behind a reverse proxy is not the host the visitor used, so every in-site referrer failed the check - 39% of the first day's impressions were mislabelled and not one `internal` row was produced. The browser now reports whether the referrer is same-origin, which is the only reliable judgement, and the server-side fallback also considers `X-Forwarded-Host`. Existing rows can be corrected in place from the stored `referrer_host`.
- Visibility tracking died permanently once a block was written off as "not seen". Switching tabs fires `visibilitychange`, which flushed a `was_visible = 0` view and set the sent flag, so a visitor who came back and scrolled to the block was still recorded as never having seen it - and any subsequent click reported `was_visible = 0` with no `visible_ms`, which cannot happen in reality. Production showed 7 such clicks on the first day. The observer now keeps running, records the moment of first visibility regardless, and sends a follow-up that upgrades the earlier row; the server applies the upgrade instead of rejecting the duplicate.

### 日本語

#### 修正
- サイト内遷移が `external` に分類されていました。自ホスト判定に `HTTP_HOST` を使っていましたが、リバースプロキシ経由では閲覧者が実際に使ったホストと一致しないため、サイト内リファラがすべて判定に失敗していました（初日のインプレッションの 39% が誤分類され、`internal` は 1 件も生成されず）。リファラが同一オリジンかどうかはブラウザだけが確実に判定できるため、その結果を送信する方式に変更し、サーバ側のフォールバックでも `X-Forwarded-Host` を見るようにしました。既存の行は保存済みの `referrer_host` から補正できます。
- 一度「未可視」と記録されると、以後の可視化追跡が永久に止まっていました。タブ切替で `visibilitychange` が発火すると `was_visible = 0` の view を確定送信して送信済みフラグが立つため、閲覧者が戻ってブロックまでスクロールしても「見ていない」ままになり、その後のクリックが `was_visible = 0`・`visible_ms` なしで記録されていました（現実にはあり得ない組み合わせで、本番初日に 7 件発生）。監視を継続し、初回可視時刻は送信状況にかかわらず記録し、先の行を訂正する追送を行うようにしました。サーバ側は重複として拒否せず訂正を適用します。

## [0.5.2] - 2026-09-15

### EN

#### Fixed
- The dashboard counted every `view` row as "viewed", including the ones the client reports on page hide for a block that never reached the screen (`was_visible = 0`). That overstated the in-viewport rate and put never-seen impressions into the denominator of the viewed CTR - defeating the purpose of the event, which is to separate rendered from actually seen. Both the summary and the per-arm table now count visible views only, and the number of impressions reported as never on screen is shown alongside. No data was lost: `was_visible` was already stored, so existing rows re-aggregate correctly.

### 日本語

#### 修正
- ダッシュボードが `view` イベントを `was_visible` に関係なく「可視化」として数えていました。クライアントは、ブロックが画面に入らないまま離脱した場合も `was_visible = 0` で `view` を送るため、可視化率が過大になり、可視化ベース CTR の分母に「見られていない表示」が混入していました。「表示された」と「実際に見られた」を分離するというこのイベントの目的に反するため、概況・アーム別表とも可視化済みのみを数えるようにし、「画面に入らなかった件数」を併記するようにしました。データの欠損はありません（`was_visible` は当初から保存しているため、既存の行も正しく再集計されます）。

## [0.5.1] - 2026-09-15

### EN

#### Fixed
- The client logging script was never loaded on themes that override `layout.phtml` without repeating `$this->trigger('view.layout')`. That event is fired only from Omeka's own layout, so every listener attached to it is silently dropped by such a theme - and the theme in production here is one of them. The result was that impressions were recorded but **no `view` or `click` event ever arrived**, and the "off" arm's hiding stylesheet would not have been applied either. The assets are now queued on the MVC render event, which runs regardless of the layout in use. Verified on both an overriding and a non-overriding theme, on browse, item and error pages, injecting exactly once.

> Operators: any other module relying on `view.layout` (CSSEditor, for one) is affected by the same theme behaviour and should be checked separately.

### 日本語

#### 修正
- `layout.phtml` を上書きし `$this->trigger('view.layout')` を呼ばないテーマでは、クライアント側ログ収集スクリプトが読み込まれませんでした。このイベントは Omeka コアのレイアウトからしか発火されないため、そうしたテーマでは同イベントに登録したリスナーがすべて無視されます（本番で使用中のテーマが該当）。結果としてインプレッションは記録されるものの、**`view`・`click` イベントが 1 件も届かない**状態になり、対照群「非表示」アームの非表示スタイルも適用されないところでした。MVC の render イベントで登録する方式に変更し、レイアウトの実装に依存しないようにしました。上書きテーマ・非上書きテーマの双方、一覧／資料／エラーページで、重複なく 1 回だけ注入されることを確認済みです。

> 運用上の注意: `view.layout` に依存する他のモジュール（CSSEditor など）も同じ影響を受けます。別途確認してください。

## [0.5.0] - 2026-09-15

### EN

#### Added
- **Usage logging for research** (opt-in, off by default). The module now collects the data needed to study how recommendations are used, without requiring access to the web server's raw access logs.
  - New table `similaritems_impression`: one row per served recommendation, with seed item and its domain buckets, per-rank results (id, score, bucket, base title, signals), candidate pool size, computation time, effective configuration (`variant`, `config_hash`, `tiebreak`, `jitter`), referrer-based entry channel, device, and bot flag.
  - New table `similaritems_event`: in-viewport `view` and `click` events, with rank, score, signals and domain of the clicked item, plus dwell time from render and from first visibility. Rank/score/signals are resolved server-side from the stored impression so they cannot be forged.
  - Server-side reconstruction of recommendation-driven browsing chains (`chain_key`, `hop_depth`, `parent_event_id`), corrected by a click key carried forward in `sessionStorage`.
  - New public endpoint `/similar-items/event` (POST only) and a theme-independent client script `asset/js/similar-items-log.js`, loaded automatically on public pages while logging is enabled.
  - New admin screen *Similar Items logs*: dashboard (CTR overall and by rank, in-viewport rate, hop-depth distribution, chain statistics, cross-domain click share, entry channels, devices, top seeds and targets), impression and event tables with filters, log deletion, and CSV/TSV export of `impressions`, `events` and derived `chains` datasets.
  - Privacy controls: pseudonymous first-party session cookie (can be disabled in favour of a daily-rotating key), IP stored hashed / raw / not at all, optional user id, bot exclusion, and a retention period in days.
- **Control-group trial** (opt-in, off by default). Visitors are assigned by session to `default` (scored), `random` (same block, randomly drawn items) or `off` (block hidden), so the effect of the feature and of the scoring engine can be measured without a before/after baseline.
  - Assignment is deterministic from the session key and a stored seed: stable within a session, requiring no storage, and recomputable during analysis. Weights are configurable, and each control arm can be switched on independently: a disabled arm weighs zero and the remainder is reallocated automatically. The dashboard reports the effective allocation.
  - The `off` arm still records an impression, so session-level outcomes (pages and items per session) exist in every arm; it is hidden from the layout stylesheet, so there is no flash of a loading block.
  - The seed item's domain buckets are recorded in every arm, keeping the cross-domain (serendipity) measure comparable.
  - New `arm` column on both log tables, included in all three exports, with a per-arm comparison table on the dashboard.
- Neutral default styling for the bundled block (`asset/css/similar-items.css`).
- The hashing salt is now created and stored at install time and whenever logging is switched on, instead of lazily on the first logged request, so that it is fixed before a study starts. The log dashboard shows it together with the formula that reproduces a stored `client_ip` from a raw address, which is what allows this log to be joined to a web server access log.
- `SimilarItems::getLastStats()` exposes candidate pool size, seed buckets and ranking options from the last scoring run.
- Scored candidates now carry their primary domain bucket, which makes cross-domain (serendipity) analysis possible.

#### Changed
- The module's default block (`view/common/resource-page-blocks/similar-items.phtml`) now loads recommendations from the async endpoint instead of running its own inline, hard-coded scoring (item sets 3 / subject 2 / creator 1). A site with no theme override therefore gets the configured scoring engine, the same markup as a themed site, and usage logging - previously it silently bypassed all three. Themes that override this partial are unaffected.

#### Fixed
- The entry channel (`entry_kind`, `referrer_host`) was derived from the recommendation request's `Referer` header. That request is an XHR issued by the item page itself, so every visit was classified as `internal`. The browser now reports `document.referrer` with the `view` event instead; chain-derived `similar_items` still takes precedence.
- `Module::onBootstrap()` did not call `parent::onBootstrap()`, so module event listeners were never attached.

### 日本語

#### 追加
- **研究用の利用ログ収集**（既定は無効、明示的に有効化）。Apache 等の生ログを参照しなくても、推薦の利用状況を分析できるようになりました。
  - 新テーブル `similaritems_impression`：推薦 1 回につき 1 行。シードアイテムとその分野、順位ごとの結果（ID・スコア・分野・ベースタイトル・発火シグナル）、候補数、計算時間、実効設定（`variant`, `config_hash`, `tiebreak`, `jitter`）、リファラに基づく流入経路、デバイス、ボット判定を記録します。
  - 新テーブル `similaritems_event`：画面内に入ったことを示す `view` と `click` を記録。クリック先の順位・スコア・シグナル・分野に加え、描画時点および可視化時点からの滞留時間を保持します。順位・スコア・シグナルは保存済みインプレッションからサーバ側で解決するため、偽装できません。
  - 推薦経由の回遊経路をサーバ側で復元（`chain_key`, `hop_depth`, `parent_event_id`）。`sessionStorage` で持ち越したクリックキーにより対応関係を補正します。
  - 公開エンドポイント `/similar-items/event`（POST 専用）と、テーマに依存しないクライアントスクリプト `asset/js/similar-items-log.js` を追加。ログ有効時に公開ページへ自動で読み込まれます。
  - 管理画面「Similar Items logs」を追加：ダッシュボード（全体および順位別 CTR、可視化率、回遊深度の分布、経路統計、分野越えクリック率、流入経路、デバイス、推薦元・クリック先の上位）、絞り込み付きのインプレッション／イベント一覧、ログ削除、`impressions`／`events`／派生データセット `chains` の CSV・TSV エクスポート。
  - プライバシー設定：匿名の第一者セッションクッキー（無効化して日次ローテーションの擬似 ID に切替可）、IP のハッシュ化／そのまま／保存しない、ユーザ ID 記録の可否、ボット除外、保持日数。
- **対照群試験**（既定は無効、明示的に有効化）。閲覧者をセッション単位で `default`（通常の推薦）／`random`（同じ見た目でランダムな資料）／`off`（ブロック非表示）に割り付け、前後比較のベースラインなしに機能そのものとスコアリングの効果を測定できます。
  - 割付はセッションキーと保存済みシードから決定的に導出します。セッション内で固定され、保存領域を必要とせず、分析時に再計算できます。配分は設定可能で、対照群アームは個別に有効・無効を切り替えられます（無効にしたアームは重み 0 になり、残りで自動的に再配分）。ダッシュボードには実効配分を表示します。
  - `off` アームでもインプレッションを記録するため、セッション単位の指標（セッションあたりページ数・資料数）が全アームで揃います。非表示はレイアウトのスタイルシートで行うため、読み込み中のブロックが一瞬見えることはありません。
  - シード資料の分野バケットを全アームで記録し、分野越え（セレンディピティ）指標の比較可能性を保ちます。
  - 両テーブルに `arm` 列を追加し、3 種のエクスポートすべてに含め、ダッシュボードにアーム別比較表を表示します。
- 同梱ブロック用の最小限の既定スタイル（`asset/css/similar-items.css`）を追加しました。
- ハッシュ用ソルトを、最初のログ書き込み時ではなく**インストール時およびログ有効化時**に生成・保存するようにしました。観測開始前に値が確定します。管理画面のログ画面に、保存済み `client_ip` を生アドレスから再現する式とともに表示します（Web サーバのアクセスログとの突合に必要）。
- `SimilarItems::getLastStats()` を追加し、直近のスコアリング実行の候補数・シードの分野・並び替えオプションを取得できるようにしました。
- スコアリング済み候補が主分野（バケット）を保持するようになり、分野越え（セレンディピティ）の分析が可能になりました。

#### 変更
- モジュール既定ブロック（`view/common/resource-page-blocks/similar-items.phtml`）を、独自のインラインスコアリング（アイテムセット 3／主題 2／著者 1 の固定重み）ではなく、非同期エンドポイントから推薦を取得する方式に変更しました。これにより、テーマを上書きしていないサイトでも設定済みのスコアリングエンジン、テーマ適用サイトと同じマークアップ、利用ログ収集が有効になります（従来はいずれも迂回されていました）。このパーシャルを上書きしているテーマには影響しません。

#### 修正
- 流入経路（`entry_kind`, `referrer_host`）を推薦リクエストの `Referer` ヘッダから判定していました。このリクエストはアイテムページ自身が発行する XHR のため、すべての流入が `internal` と判定されていました。`view` イベントでブラウザから `document.referrer` を受け取る方式に変更しました（クリック連鎖から確定する `similar_items` が優先されます）。
- `Module::onBootstrap()` が `parent::onBootstrap()` を呼んでおらず、モジュールのイベントリスナーが登録されていませんでした。

## [0.4.6] - 2026-03-18

### EN

#### Changed
- Completely overhauled and synchronized `README.md` for exact parity between English and Japanese sections (e.g., added missing "Multi-match Bonus" section to English docs).
- Synchronized admin UI field labels with README terminology exactly.
- Added explicit documentation in `ConfigForm.php` field descriptions (UI info blocks) and `README.md` explaining the background extraction logic for "Shelf match" and "Class proximity".

### 日本語

#### 変更
- `README.md` を全面的に見直し、英語と日本語のドキュメントの構造と内容を完全に同期させました（英語版に欠落していた「一致回数ボーナス」セクションを追加など）。
- 管理画面の各設定項目ラベルの表記を README の用語と厳密に一致させました。
- 「重み: 棚記号」および「重み: 分類近接」について、システム内で文字列や修飾子がどのように抽出・評価され、フォールバックするかという具体的な処理ロジックを、設定画面上の説明テキストおよび README に追記しました。

## [0.4.5] - 2026-02-21

### EN

#### Changed
- Removed the `class_exact` scoring signal from recommendation logic.
- Removed the corresponding admin setting field `Weight: Class exact match`.
- Classification scoring now relies on shelf match and class proximity only.

### 日本語

#### 変更
- 推薦ロジックから `class_exact`（分類記号の完全一致）シグナルを削除しました。
- 管理画面の対応する設定項目「重み: 分類記号（完全一致）」を削除しました。
- 分類系の加点は「棚一致」と「分類近接」に統一しました。

## [0.4.4] - 2026-02-10

### EN

#### Changed
- Admin config UI: hide debug-only mapping fields for Location and Viewing direction (no functional change; settings keys remain available).
- Admin config UI: removed redundant help text from some weight fields (Item sets and Shelf).

### 日本語

#### 変更
- 管理画面の設定UI: デバッグ用の「出版地」「閲覧方向」の対応付け項目を非表示にしました（機能面の変更はありません。設定キー自体は維持されます）。
- 管理画面の設定UI: 一部の重み（アイテムセット／棚記号）から重複していた説明文を削除しました。

## [0.4.3] - 2026-01-13

### EN

#### Fixed
- Shelf/class proximity and bucket evaluation now strip leading labels like `CAL:` and `NDC9:`/`NDC6:` (including fullwidth colon `：`) before parsing and comparison.
- Title–volume separators used for base-title normalization are now matched strictly by exact string (including leading/trailing spaces), with no lenient comma/spacing variants.
- When title-volume separators are configured, base-title normalization no longer strips trailing numbers/years/volume markers unless a configured separator matches (prevents accidental truncation).
- Scoring now honors negative weights consistently across signals (bucket/shelf/class proximity/material/issued/item sets) and applies property overlap scoring even when candidates are added via expansion.
- Shelf key parsing now keeps the leading token (up to space/dot/hyphen) for call numbers (e.g. `ハ220-186` → `ハ220`, `ル185` → `ル185`).

### 日本語

#### 修正
- 棚番号／分類近接および分野バケット判定において、`CAL:` や `NDC9:` `NDC6:` 等の「ラベル＋コロン」（全角コロン `：` を含む）が先頭に付く場合でも、ラベル部分を除去した値で判定するように修正しました。
- ベースタイトル抽出に用いる「タイトルと巻号の区切り文字」を、指定した文字列の完全一致（前後スペースを含む）で判定するように変更しました（カンマやスペースのゆるい同一視は行いません）。
- 「タイトルと巻号の区切り文字」が設定されている場合、区切りに一致しない限り末尾の数字/年号/巻号などを自動で切り落とさないように修正しました（意図しない切り詰めを防止）。
- 負の重みが一部のシグナルで反映されない不具合を修正しました（バケット/棚/分類近接/資料種別/出版年近接/アイテムセット）。また、拡大で入った候補にもプロパティ一致（著者・主題等）を適用します。
- 棚番号の抽出を改善し、請求記号の先頭トークン（空白/ドット/ハイフンまで）を棚記号として扱うようにしました（例: `ハ220-186` → `ハ220`, `ル185` → `ル185`）。

## [0.4.2] - 2026-01-13

### EN

#### Fixed
- Domain bucket rule evaluation now correctly supports nested boolean groups (`all`/`any`) inside other groups. This fixes cases like a Literature bucket rule that intends: `call_number` starts with "ル" AND does NOT start with "ル185".

### 日本語

#### 修正
- 分野バケットの条件評価で、条件の入れ子（`any` の中に `all` を置く等）が正しく評価されない問題を修正しました。これにより「`ル` で始まり、かつ `ル185` では始まらない」などの条件が意図どおりに機能します。

## [0.4.1] - 2025-12-18

### EN

#### Changed
- Default domain bucket rules updated: numeric prefixes are now evaluated on `call_number` (instead of `class_number`), and the History bucket includes a `call_number` prefix rule for "東亜研".
- Classification proximity/exact scoring now requires matching non-numeric class prefixes before comparing numeric parts (prevents unintended boosts based on digits alone).
- Debug payload now includes `class_prefix` in both seed and candidate `values` for easier verification.

#### Fixed
- Multi-match bonus calculation adjusted to add bonus only for 2nd+ distinct matches and avoid double-counting with the base property weight.
- Multi-match for Author ID and Authorized name now considers all seed values (not only the first), so 2+ overlaps correctly receive a bonus.

### 日本語

#### 変更
- 分野バケットの既定ルールを更新：数字プレフィックスの判定対象を `class_number` ではなく `call_number` に変更し、「歴史」バケットに `call_number` が「東亜研」で始まる条件を追加しました。
- 分類近接／分類完全一致の加点に「文字プレフィックス一致」を前提条件として追加し、数字だけが近いケースで意図せず加点されるのを防ぎます。
- デバッグ出力の `values` に `class_prefix` を追加し、判定の検証がしやすくなりました。

#### 修正
- multi-match（一致回数加点）の計算を「2件目以降のボーナスのみ」に整理し、基本ウェイトとの二重カウントを防止しました。
- 著者ID／典拠形著者名の multi-match がシード側の先頭1件のみで評価されていた問題を修正し、2件以上一致した場合に正しくボーナスが加点されるようにしました。

## [0.4.0] - 2025-12-05

### EN

#### Added
- New candidate signals for selection and scoring: Series title and Publisher can now be mapped and weighted like Author/Subject, broadening the pool of potential neighbors.
- Optional multi-match bonus: when enabled, multi-valued properties (Author ID, Authorized name, Subject, Series, Publisher) add extra score based on the number of distinct matching values with a configurable decay factor.
- Classification exact-match weight: in addition to class proximity, a separate weight applies when the normalized numeric part of the class matches exactly.
- Publication place weight: when mapped, matching publication place contributes an optional score bonus.

#### Changed
- Hard cap on candidate pool size (default 1000) to keep performance stable even when many properties are used for candidate expansion.
- NCID removed from candidate selection and scoring: mapping and weight fields have been dropped from the settings UI, and NCID is no longer used as a similarity signal.
- Shelf-based candidate expansion UI is now hidden and effectively disabled; domain buckets and other signals are the primary tools for broadening the pool.
- Weights now accept negative values for all signals, allowing explicit penalties via "Weights & Thresholds" in addition to dedicated same-bibid/title penalties.

#### Fixed
- Title normalization and matching logic updated to respect the new settings while keeping existing behavior (e.g., Japanese volume markers and separators) intact.

### 日本語

#### 追加
- 候補選出＋スコア加算のシグナルに「シリーズタイトル」「出版者」を追加し、著者・主題と同様にマッピングと重み付けが可能になりました。メタデータが十分な資料では候補の幅が広がります。
- 一致回数加点（オプション）を追加：有効にすると、著者ID／著者名典拠形／主題／シリーズタイトル／出版者など多値プロパティで、一致件数に応じて減衰付きで加点します（最初の一致は既存の重み、2件目以降は重み×減衰率）。
- 分類記号の完全一致ウェイトを追加：従来の「分類近接」に加え、正規化した数値部分が完全一致した場合に別枠の重みを適用できます。
- 出版地の一致ウェイトを追加：対応付けされた出版地プロパティが一致した場合に、任意の重みでスコアを加点できます。

#### 変更
- 候補プールの上限を導入（既定1000件）：複数のシグナルで候補を拡大した場合でも、一定数で打ち切ることで性能と応答時間を安定させます。
- NCID を類似性シグナルから除去：設定画面からNCIDのマッピング／重みを削除し、候補選出やスコアリングには用いないようにしました。
- 棚情報による候補拡大のUIを非表示化し、現行バージョンでは事実上無効化しました。分野バケットや他のプロパティを通じて候補を広げる設計に整理しています。
- 「ウェイトと閾値」セクションのすべての重みで負の値を受け付けるようにし、同一書誌ID・同一タイトル以外の条件についても「一致時の減点」を設定できるようにしました。

#### 修正
- タイトル正規化ロジックを、設定可能な区切り文字と既存の日本語パターン（第N巻／巻之〜 等）の両方を尊重する形に整理し、従来の base title 抽出結果を崩さないようにしました。

## [0.3.2] - 2025-11-03

### EN

#### Added
- Tie-break policy setting in the admin UI (Basic settings): choose how to order equal-score candidates.
  - Score only (no extra tie-breaking)
  - Prefer more matching signals (consensus)
  - Prefer strongest match (max weight)
- The helper reads this setting as the default policy, still allowing per-request override via `tiebreak` query parameter.
 - New per-request override: `jitter=0` disables light jitter for the current request (useful for A/B tests and deterministic comparisons). `jitter=1` can force-enable it.

#### Fixed
- Admin config form now retrieves the application container robustly (creation context preferred), avoiding deprecated plugin manager access and preventing null errors on some environments.

### 日本語

#### 追加
- 同点時の優先基準を設定画面（基本設定）に追加：同点候補の並び順を選べます。
  - スコアのみ（同点はそのまま）
  - 一致シグナル数優先（根拠が多い方を上位）
  - 最大重み優先（最も強い一致を上位）
- 既定は設定値を使用し、必要に応じてリクエストの `tiebreak` パラメータで一時的に切替可能です。
 - リクエスト単位の上書きに `jitter=0` を追加：当該リクエストの微揺らぎを無効化（A/Bテストや厳密比較に有用）。`jitter=1` で強制有効化。

#### 修正
- 設定フォームのサービス取得を堅牢化（creation context を優先）。プラグインマネージャ経由の非推奨アクセスを避け、環境によって発生していた null エラーを防止しました。

## [0.3.1] - 2025-11-03

### EN

#### Fixed
- Public access for recommendations: allow anonymous users to call the async endpoint by registering ACL permissions for `RecommendController::list`. This resolves `PermissionDeniedException` when logged out.

#### Added
- LICENSE file (MIT) at the module root for clarity and packaging.

### 日本語

#### 修正
- 推薦APIの公開アクセス: ACL により匿名ユーザでも `RecommendController::list` にアクセス可能とし、ログアウト時の `PermissionDeniedException` を解消しました。

#### 追加
- モジュール直下に LICENSE（MIT）ファイルを追加しました。

## [0.3.0] - 2025-11-02

### EN

#### Added
- Full Japanese localization of the admin settings UI with clear section headings.
- Visible section descriptions in the settings form via helper elements compatible with Omeka's renderer.

#### Changed
- Terminology unified across UI and docs:
  - “Seeding/Boost/Bonus” → “候補拡大/スコア加算”.
  - English docs aligned to “Candidate Expansion” and “Scoring”.
- Settings reorganized into clearer groups:
  - Basic, Candidate Expansion, Light Jitter, Property Mappings, Weights & Thresholds, Serendipity, Title Rules, Domain Bucket (JSON).
- Moved switches/weights to appropriate sections:
  - “Use item sets for similarity assessment” moved under Candidate Expansion.
  - “Weight: Item Set Match” moved under Weights & Thresholds.
- Page-scoped CSS for the settings page to improve fieldset spacing (no global style impact).
- README updated (English/Japanese) to match the current settings and terminology.

#### Fixed
- Resolved a Laminas DomainException by ensuring helper elements include minimal labels.
- Minor formatting and lint fixes in form definition and module class.

#### Notes
- Shelf scoring (weight) is independent of the shelf-based candidate expansion switch.
- “Class proximity” is a scoring-only signal and does not affect candidate selection.
- No breaking changes to public APIs; existing configuration keys are preserved.

### 日本語

#### 追加
- 管理画面の設定UIを日本語化（セクション見出しを含む）。
- Omeka のレンダラ互換のヘルパー要素で、見出し直下のセクション説明を表示。

#### 変更
- 用語をUIとドキュメントで統一：
  - “Seeding/Boost/Bonus” → “候補拡大/スコア加算”。
  - 英語ドキュメントは “Candidate Expansion / Scoring” に整合。
- 設定を分かりやすいグループへ再編：
  - 基本設定／候補拡大／微揺らぎ／プロパティ対応付け／ウェイトと閾値／セレンディピティ／タイトルルール／分野バケット（JSON）。
- スイッチ・重みの配置を適正化：
  - 「アイテムセットを類似判定に使用」を「候補拡大」へ移動。
  - 「重み: アイテムセット一致」を「ウェイトと閾値」へ移動。
- 設定ページ限定のCSSで fieldset の余白を調整（グローバル影響なし）。
- README（英／日）を現行設定と用語に合わせて更新。

#### 修正
- ラベル無し要素で発生していた Laminas の DomainException を、最小限のラベル付与で解消。
- フォーム定義およびモジュールクラスの体裁・Lint の軽微な修正。

#### ノート
- 棚のスコア加算（weight）は、棚の候補拡大スイッチと独立して適用されます。
- 「分類近接」は候補選択には使わず、スコア加算のみのシグナルです。
- 破壊的変更はありません（既存の設定キーは維持）。

## [0.2.3] - 2025-11-01

### Added
- Light boosts (optional):
  - Material type equality boost (case-insensitive) with configurable weight.
  - Issued-year proximity boost with configurable weight and threshold (±N years).
- Debug values now surface optional mapped properties for visibility: Location, Issued, Material type, and Viewing direction (when mappings are set).

### Changed
- Shelf seeding robustness: prioritize "starts-with" candidates first with Unicode normalization (e.g., full-/half-width), then apply a precise exact-shelf post-filter; improved diagnostics and counters in logs.
- README updated (EN/JA) to document the new light boosts and added debug fields.

### 追加（日本語）
- 軽いブースト（任意）を追加：
  - 資料種別の一致ブースト（大文字小文字を無視、重みを設定可）。
  - 刊行年の近接ブースト（±N 年、重み・閾値を設定可）。
- デバッグ値に任意マッピングのプロパティ（所在／刊行年／資料種別／閲覧方向）を表示（マッピングされている場合）。

### 変更（日本語）
- 棚の種まきを強化：Unicode 正規化を行った上で「前方一致」を優先的に収集し、その後に厳密な同一棚の後段フィルタを適用。ログの統計（scanned/exact/dups/added/mismatched/no_call など）を拡充。
- README を更新（英日）— 新しい軽いブーストとデバッグ追加項目を記載。

## [0.2.2] - 2025-11-01

### Added
- Theme setting `similar_items_title_max_length` to control the display title truncation length from the active theme (0 = unlimited).
- Debug meta now includes `cur_buckets` (current item's bucket keys) to help verify bucket-based weights.

### Changed
- Removed the module-side config input for "Max display title length"; title truncation is now theme-driven. The module still reads `similaritems.title_max_length` as a backward-compatible fallback only.
- README updated to clarify that title max length is theme-controlled.

### Fixed
- Bucket evaluation now derives `class_number` from `call_number` when a dedicated class property is not mapped, ensuring rules like `class_number` prefix match work as expected (e.g., "210-H..." -> 210).

### 追加（日本語）
- テーマ設定 `similar_items_title_max_length` を追加（表示タイトルの最大文字数をテーマ側で制御、0 は無制限）。
- デバッグメタに `cur_buckets`（現在アイテムのバケットキー）を追加し、バケット系ウェイトの検証を容易化。

### 変更（日本語）
- モジュール側の「表示タイトルの最大文字数」入力欄を撤去。以後はテーマ側で制御。モジュールの `similaritems.title_max_length` は互換フォールバックとしてのみ参照。
- README を更新（表示タイトルの最大長はテーマ側で制御）。

### 修正（日本語）
- 分類プロパティ未設定時でも `call_number` から `class_number` を派生して評価するようにし、`class_number` の prefix 条件が期待通りに効くように修正（例: "210-H..." → 210）。

## [0.2.1] - 2025-11-01

### Added
- Jitter-aware tie-breaking: when light jitter is enabled, items with equal scores are randomly ordered per reload before falling back to modified date, ensuring visible variation even when many top candidates tie.
- Expanded debug payload: when `debug=1`, each row now includes `values` containing the actual property values behind signals and proximity context (buckets, shelf, class_number).
 - Debug meta now includes `cur_buckets` showing the current item's bucket keys for easier troubleshooting of bucket-based weights.

### Changed
- README updated to clarify jitter behavior (tie-breaking and selection), and IIIF thumbnail default noted as `/square/240,/0/default.jpg` with client-side fallback.

### Fixed
- Cases where candidates equal the display limit now still vary in order across reloads (with jitter on) due to randomized tie-breaking.

### 追加（日本語）
- ジッター対応の同点タイブレーク: 微揺らぎが有効な場合、スコアが同点の要素は modified の前にリロード毎ランダムで並べ替え、同点が多いときでも目に見える変化を保証します。
- デバッグ出力の拡充: `debug=1` のとき、各行に `values`（シグナルの根拠となる実際のプロパティ値と近接コンテキスト: buckets, shelf, class_number）を追加。
 - `debug_meta` に現在アイテムの `cur_buckets` を追加し、バケット系ウェイトの検証を容易化。

### 変更（日本語）
- README を更新（ジッターの挙動: 同点タイブレークと上位プール抽出の説明、IIIF サムネイル既定 `/square/240,/0/default.jpg` とフォールバックの明記）。

### 修正（日本語）
- 候補数が表示件数と同数の場合でも（ジッター有効時）同点帯は並びが変動するようになりました。

## [0.2.0] - 2025-11-01

### Added
- Admin setting to control behavior when only same-title candidates exist: Allow (default) or Exclude.
- Async recommendations endpoint can return structured debug data when `debug=1` (id, title, url, score, base_title, signals).
- Title normalization uses configurable title–volume separators; improved Japanese patterns (上/中/下 variants, 之, numerals).
- Site-aware URLs for recommendations with router fallback to avoid empty links.
- IIIF thumbnails via `/square/240,/0/default.jpg`, falling back to Omeka thumbnails (and client-side fallback to `/square/max/0/default.jpg` on error).
- Final-stage diversification prioritizing different base titles, then fills, then same-series last.
- README updated with a full Japanese translation section.
- Random fallback: when Exclude mode yields 0 candidates, fetch a random page of items and sample up to the limit (site-scoped if enabled).
- Config: Max display title length (default 60; 0 = unlimited).
- Optional light jitter: when enabled, the helper samples from a slightly larger top pool so results vary subtly on reload (without losing top relevance).

### Changed
- Default weights tuned for diversity: NCID=6, Author ID=5, Authorized name=3, Subject=5, Domain=2, Shelf=1, Class proximity=1, Item Sets=2.
- Default same BibID penalty increased to 150; same-title penalty aligned to at least 100.

### Fixed
- Logger compatibility (Laminas/PSR-3) and removal of HTML diagnostics; logs now go to `logs/application.log` when enabled.
- Safer fallbacks when site scoping or item set seeding yields few candidates.

### 追加（日本語）
- 「同一タイトルのみ」のときの挙動を設定化（許可/完全除外）。
- `debug=1` で構造化データを返す非同期API（id, title, url, score, base_title, signals）。
- タイトル正規化の強化（設定可能な区切り、上/中/下・之・数字などの日本語パターン）。
- サイト対応URLとルーターフォールバックで空リンク回避。
- IIIF `/square/240,/0/default.jpg` サムネイル、Omekaサムネイルへのフォールバック（エラー時はクライアント側で `/square/max/0/default.jpg` にフォールバック）。
- 最終段の多様化（異なるタイトル優先→充填→シリーズ最後）。
- README に日本語訳セクションを追加。
- 完全除外で0件の場合、ランダムフォールバック（サイト範囲がオンならサイト内）で limit まで表示。
- 表示タイトルの最大文字数を設定可能（既定60、0で無制限）。
- 微揺らぎ（任意設定）：オンにすると上位候補の少し広いプールから重み付き抽出し、リロード毎に表示がわずかに入れ替わります（上位の関連性は維持）。

### 変更（日本語）
- 既定ウェイトを多様性重視に調整（NCID=6, 著者ID=5, 権限定名=3, 主題=5, 分野=2, 棚=1, 分類近接=1, アイテムセット=2）。
- 同一BibIDペナルティを150に引き上げ、同一タイトルの降格も最低100に整合。

### 修正（日本語）
- ロガー互換性（Laminas/PSR-3）とHTML診断の撤去（有効時は `logs/application.log` に出力）。
- サイトスコープやアイテムセット種まきで候補が少ない場合のフォールバックを堅牢化。

## [0.1.0] - 2025-10-30
Initial release.

### Added
- Registers a Resource Page Block Layout named `similarItems` so it appears in Admin → Sites → Configure resource pages.
- Delegates rendering to the theme partial `view/common/resource-page-blocks/similar-items.phtml` to allow full theme control of UI.
- Provides global (module-wide) configuration for similarity logic via Admin → Modules → Similar Items → Configure:
  - Scope to current site (on/off)
  - Use Item Sets with weight
  - Up to four property-based criteria (term, match type: eq/cont/in, weight)
  - Cap of terms per property and a pool-size multiplier to balance API calls vs. ranking quality
- Theme integration (example: foundation_tsukuba2025) supporting:
  - Two-column UI with list + mascot and hover thumbnail popovers
  - Speech bubble row (JA/EN), right-aligned with pointer
  - Title text (JA/EN) and show/hide toggle; maximum results
- Default similarity behavior (when no properties are explicitly configured):
  - dcterms:subject (eq, weight=2)
  - dcterms:creator (eq, weight=1)
  - Item set overlap (weight=3 if enabled)

### Notes
- If the theme setting `similar_items_enable` is off, the block renders nothing even if the module is active.
- This module focuses on logic and registration; visual presentation remains with the active theme.

[0.1.0]: https://github.com/wakitosh/SimilarItems/releases/tag/v0.1.0
[0.2.0]: https://github.com/wakitosh/SimilarItems/releases/tag/v0.2.0
