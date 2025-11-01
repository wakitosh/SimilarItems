# Changelog

All notable changes to this project will be documented in this file.

## [0.2.1] - 2025-11-01

### Added
- Jitter-aware tie-breaking: when light jitter is enabled, items with equal scores are randomly ordered per reload before falling back to modified date, ensuring visible variation even when many top candidates tie.

### Changed
- README updated to clarify jitter behavior (tie-breaking and selection), and IIIF thumbnail default noted as `/square/240,/0/default.jpg` with client-side fallback.

### Fixed
- Cases where candidates equal the display limit now still vary in order across reloads (with jitter on) due to randomized tie-breaking.

### 追加（日本語）
- ジッター対応の同点タイブレーク: 微揺らぎが有効な場合、スコアが同点の要素は modified の前にリロード毎ランダムで並べ替え、同点が多いときでも目に見える変化を保証します。

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
