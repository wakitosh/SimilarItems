## SimilarItems (Omeka S module)

This module registers a Resource Page Block Layout named `similarItems` so it appears in Admin → Sites → [site] → Configure resource pages. The block delegates rendering to a theme partial so each theme can fully control the look and feel. In addition, the module provides global configuration (admin-wide) that drives the similarity logic.

- Compatible with: Items
- Block label: “Similar items”
- Rendering: Delegated to theme partial `common/resource-page-blocks/similar-items`
- Global settings: Provided by this module (Admin → Modules → Similar Items → Configure)

### Features
- Register a “Similar items” resource page block (items only)
- Global, module-wide similarity settings (scope, criteria, weights)
- Theme-driven UI and layout (e.g., two-column list, hover thumbnails, mascot, bubble text)

### Requirements
- Omeka S ≥ 4.x
- A theme that provides or overrides `view/common/resource-page-blocks/similar-items.phtml`

### Installation
1. Place this folder under `modules/SimilarItems`.
2. Activate the module in Admin → Modules.
3. Optionally configure the module in Admin → Modules → Similar Items → Configure.
4. In Admin → Sites → [your site] → Configure resource pages, add the block “Similar items” where you want it.

### Configuration (Global, module-level)
These settings are stored as admin-wide settings and are read by the theme partial via `$this->setting()`:
- similaritems.scope_site: Limit candidates to the current site only (0/1)
- similaritems.use_item_sets: Use shared Item Sets as a signal (0/1)
- similaritems.weight_item_sets: Weight to add when Item Sets overlap (integer ≥ 0)
- similaritems.prop{1..4}.term: Property term to use (e.g., `dcterms:subject`)
- similaritems.prop{1..4}.match: Match type per property
	- eq: exact match
	- cont: contains/substring match
	- in: treat the current item’s value as a dictionary (split by comma/space/etc.) and use each token as an exact match query
- similaritems.prop{1..4}.weight: Weight per property (integer ≥ 1)
- similaritems.terms_per_property: Max number of terms per property to query per item (default 6)
- similaritems.pool_multiplier: Pool size multiplier for gathering and ranking candidates (default 4)

Fallback behavior:
- If no properties are configured, the logic falls back to:
	- dcterms:subject (match=eq, weight=2)
	- dcterms:creator (match=eq, weight=1)

### Theme integration (Display settings)
The theme can control visibility and text strings via theme settings (examples from `foundation_tsukuba2025`):
- similar_items_enable: Master toggle for the block output
- similar_items_max: Max number of results to display
- similar_items_show_title: Show/hide the block title
- similar_items_title_ja / similar_items_title_en: Block title (JA/EN)
- similar_items_bubble_ja / similar_items_bubble_en: Mascot bubble text (JA/EN)

Note: If `similar_items_enable` is off in the theme, the block renders nothing even if the module is active.

### How it works (at a glance)
- The theme partial collects “signals” from the current item (Item Sets, property values) and performs API searches to collect candidate items.
- Candidates are scored by overlaps and weights, then sorted by score (desc), breaking ties by last modified (desc).
- The module’s global settings control which signals are used and how strongly, while the theme controls the UI.

---

## SimilarItems（Omeka S モジュール、日本語）

このモジュールは “Similar items（類似アイテム）” ブロックを登録し、管理画面（サイト → リソースページの設定）で追加できるようにします。描画はテーマのパーシャルに委譲し、見た目やレイアウトはテーマ側で自由に制御できます。さらに、類似判定のロジックを司る設定をモジュール全体設定（グローバル設定）として提供します。

- 対応: アイテム
- ブロック名: “Similar items”
- 描画: テーマの `common/resource-page-blocks/similar-items` に委譲
- グローバル設定: 管理画面 → モジュール → Similar Items → 設定

### 機能
- “Similar items（類似アイテム）” ブロックの登録（アイテム用）
- モジュール全体の類似判定設定（スコープ、条件、重み）
- テーマ側での UI 制御（2カラム、ホバーサムネイル、マスコット、吹き出しテキストなど）

### 必要条件
- Omeka S 4.x 以上
- `view/common/resource-page-blocks/similar-items.phtml` を提供または上書きするテーマ

### インストール
1. 本フォルダを `modules/SimilarItems` に配置
2. 管理画面 → モジュールで有効化
3. 必要に応じて 管理画面 → モジュール → Similar Items → 設定 で類似判定を調整
4. 管理画面 → サイト → （サイト） → リソースページの設定 で “Similar items” ブロックを追加

### 設定（グローバル：モジュール設定）
テーマ側の部分テンプレートから `$this->setting()` で読み取られる、管理画面全体の設定です：
- similaritems.scope_site: 候補を現在のサイトに限定（0/1）
- similaritems.use_item_sets: アイテムセットの一致を類似判定に使用（0/1）
- similaritems.weight_item_sets: アイテムセット一致時の加点（整数 ≥ 0）
- similaritems.prop{1..4}.term: 使用するプロパティ（例: `dcterms:subject`）
- similaritems.prop{1..4}.match: マッチ方法
	- eq: 完全一致
	- cont: 含む（部分一致）
	- in: 現在アイテムの値を辞書（カンマや空白等で分割）として扱い、各トークンで完全一致検索
- similaritems.prop{1..4}.weight: 各プロパティの重み（整数 ≥ 1）
- similaritems.terms_per_property: 1プロパティあたりの最大語数（デフォルト 6）
- similaritems.pool_multiplier: 候補プール倍率（デフォルト 4）

フォールバック動作：
- プロパティ未設定時は、次の既定にフォールバックします。
	- dcterms:subject（match=eq, weight=2）
	- dcterms:creator（match=eq, weight=1）

### テーマ連携（表示系の設定）
以下はテーマ設定（例：`foundation_tsukuba2025` の `theme.ini`）で管理されます。
- similar_items_enable: ブロック表示のON/OFF
- similar_items_max: 表示件数
- similar_items_show_title: ブロックタイトルの表示/非表示
- similar_items_title_ja / similar_items_title_en: タイトル（日本語/英語）
- similar_items_bubble_ja / similar_items_bubble_en: 吹き出しテキスト（日本語/英語）

注: テーマ側で `similar_items_enable` がOFFの場合、モジュールが有効でもブロックは何も描画しません。

### 概要（処理の流れ）
- テーマのパーシャルが、現在のアイテムからシグナル（アイテムセット、プロパティ値など）を収集し、API 検索で候補アイテムを取得します。
- 候補に対して重み付けスコアを加算してランキングし、スコア（降順）、同点は更新日時（降順）で並べます。
- モジュールのグローバル設定で「何をどれだけ重視するか」を制御し、UI はテーマ側で制御します。

### 備考
- このモジュールは表示そのものをテーマに委譲します。UI の具体的な挙動（2カラム、ホバーサムネイル、吹き出し等）はテーマ側の実装に依存します。
- 既存サイトやテーマとの互換性のため、プロパティ未設定時のフォールバックを備えています。
