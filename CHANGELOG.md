# Changelog

All notable changes to this project will be documented in this file.

## [0.4.3] - 2026-01-13

### EN

#### Fixed
- Shelf/class proximity and bucket evaluation now strip leading labels like `CAL:` and `NDC9:`/`NDC6:` (including fullwidth colon `：`) before parsing and comparison.
- Title–volume separators used for base-title normalization are now matched strictly by exact string (including leading/trailing spaces), with no lenient comma/spacing variants.
- When title-volume separators are configured, base-title normalization no longer strips trailing numbers/years/volume markers unless a configured separator matches (prevents accidental truncation).
- Scoring now honors negative weights consistently across signals (bucket/shelf/class proximity/material/issued/item sets) and applies property overlap scoring even when candidates are added via expansion.
- Shelf key parsing now prefers the non-numeric prefix for alphanumeric/kana call numbers (e.g. `ル185` → `ル`).

### 日本語

#### 修正
- 棚番号／分類近接および分野バケット判定において、`CAL:` や `NDC9:` `NDC6:` 等の「ラベル＋コロン」（全角コロン `：` を含む）が先頭に付く場合でも、ラベル部分を除去した値で判定するように修正しました。
- ベースタイトル抽出に用いる「タイトルと巻号の区切り文字」を、指定した文字列の完全一致（前後スペースを含む）で判定するように変更しました（カンマやスペースのゆるい同一視は行いません）。
- 「タイトルと巻号の区切り文字」が設定されている場合、区切りに一致しない限り末尾の数字/年号/巻号などを自動で切り落とさないように修正しました（意図しない切り詰めを防止）。
- 負の重みが一部のシグナルで反映されない不具合を修正しました（バケット/棚/分類近接/資料種別/出版年近接/アイテムセット）。また、拡大で入った候補にもプロパティ一致（著者・主題等）を適用します。
- 棚番号の抽出を改善し、英字/仮名+数字の記号は非数字プレフィックスを採用するようにしました（例: `ル185` → `ル`）。

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
