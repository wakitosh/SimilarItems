# SimilarItems (Omeka S Module)

This module provides a "Similar Items" page block for Omeka S, designed to display contextually relevant recommendations on item pages. It uses a configurable, weighted scoring engine and renders results asynchronously for a smooth user experience.

The display is controlled by the active theme, while all recommendation logic is managed through this module's global settings.

## Features

- **Configurable Scoring Engine**: Fine-tune recommendation relevance using multiple weighted signals (Author ID, Authorized name, Subject, Series title, Publisher, Domain buckets, Item sets, etc.).
- **Async Loading**: Recommendations are loaded via a JSON API after the main page content, preventing slow page loads.
- **Advanced Serendipity Control**: Promote diversity by penalizing items from the same series (BibID) and same base title, with final-stage diversification by base title.
- **Smart Candidate Expansion**: Expands the candidate pool using item sets and mapped properties (Author/Subject/Series/Publisher), with an internal hard cap to keep performance predictable.
- **Multi-match Bonus (Optional)**: When enabled, multi-valued properties add extra score based on how many distinct values match between the seed item and each candidate, with a configurable decay rate.
- **Title Normalization**: Intelligently groups items by their base title, ignoring volume numbers and separators (e.g., "Title, Vol. 1" and "Title, Vol. 2" are treated as having the same base title).
- **Light Jitter**: Subtly varies results on each page reload to increase discovery, without sacrificing top relevance.
- **Rich Diagnostics**: A debug mode provides detailed logs and a structured JSON payload, showing exactly how each recommendation was scored.

---

## Installation and Configuration

### 1. Installation

1.  Place the module folder into your Omeka S `modules` directory as `SimilarItems`.
2.  In the Omeka S admin dashboard, go to **Modules** and activate "Similar Items".
3.  Go to **Sites** → [Your Site] → **Configure resource pages**. On the Item page, add the "Similar items" block to your desired region.

### 2. Configuration

All settings are located in **Admin → Modules → Similar Items → Configure**. The settings are divided into the following sections.

#### Basic Settings

Controls the module's fundamental behavior.

- **Scope to current site**: (Recommended: On) Restricts recommendations to items within the current site.
- **Maximum number of results**: The maximum number of similar items to display in the page block.
- **Debug log**: (Default: Off) When enabled, detailed diagnostic information is written to `logs/application.log`.
- **Include debug JSON in payload**: (Default: Off) When enabled, the API response will include debug information.
- **Tie-break policy for equal scores**: Defines how to order items when two candidates have the same total score.
  - `None` (Default): Score only; keep ties as-is.
  - `Prefer consensus`: Prefer the item supported by more distinct positive signals (e.g., Subject + Author + Shelf beats Author + Shelf).
  - `Prefer strength`: Prefer the item with the single strongest matching signal (highest component weight).

#### Candidate Expansion

These settings broaden the pool of potential candidates for similarity assessment, helping to find relevant items even when metadata is sparse.

- **Use item sets for similarity assessment**: Adds items from shared item sets to the candidate pool.

#### Light Jitter

Introduces intentional randomness to the results to promote serendipity (accidental discovery).

- **Light jitter**: (Default: Off) If enabled, the final list is sampled from a slightly larger pool of top-scoring items, causing the order and selection to vary subtly on each page reload.
- **Candidate pool multiplier**: Defines the size of the candidate pool for jittering, as a multiple of the display limit. For example, with a limit of 6 and a multiplier of 1.5, the final 6 items will be randomly selected from the top 9 candidates (6 * 1.5).
- **Handling for same-title-only cases**: Controls behavior when only candidates with the same base title are found.
    - `Allow` (Default): Shows the highest-scoring items, even if they are from the same series.
    - `Exclude`: Hides same-title items to maximize diversity. If this results in zero candidates, the block will be filled with a random selection of items instead.

#### Property Mappings

Connects the concepts used by the module (e.g., Call Number, Author ID) to the properties in your Omeka S vocabulary. Properties are grouped by their role in the recommendation process.

- **Candidate Selection + Scoring**:
  - When a property value matches, the item is selected as a candidate *and* its score is increased. These are the primary signals for relevance.
  - Properties (typical): `Author ID`, `Authorized name (weak)`, `Subject`, `Series title`, `Publisher`.

- **Candidate Expansion + Shelf Scoring**:
  - Maps the `Call number` property. If "Use shelf information" is on, this property is used to expand the candidate pool. A score bonus is always applied to candidates on the same shelf.

- **Proximity & Equality (Scoring Only)**:
  - These properties are not used for initial candidate selection. They only add to the score if a candidate meets a proximity or equality condition.
  - Properties: `Class number`, `Issued`, `Material type`.

- **Penalty-focused**:
  - Maps the `Bibliographic ID` property. Primarily used to apply a penalty to items from the same bibliographic record, pushing them down in the results.

- **For Debugging**:
  - These properties are only displayed in debug output and do not affect scoring.
  - Properties: `Location`, `Viewing hint`.

#### Weights and Thresholds

Configures the scoring weights and proximity thresholds. Higher weights give a signal more influence over the final score.

- **Weights**:
  - The base score added when a match or proximity is detected for each property (e.g., `NCID`, `Author ID`) or concept (e.g., `Shelf`, `Class proximity`).
- **Thresholds**:
  - `Class proximity threshold`: Items with class numbers within this range are considered "close."
  - `Issued proximity threshold`: Items with publication years within this range are considered "close."

#### Serendipity

Settings designed to increase the diversity of results and promote discovery by penalizing items that are "too similar."

- **Demote same bibliographic record**: (Recommended: On) Applies a penalty to items sharing the same bibliographic ID (e.g., volumes of a series) to prevent them from dominating the results.
- **Demote same title**: (Recommended: On) Applies a penalty to items sharing the same base title.
- **Penalty Values**:
  - `Penalty for same bibliographic record`: The score to subtract when the above switch is on.
  - `Penalty for same title`: The score to subtract when the above switch is on.

#### Title Rules

- **Title-volume separators**: Define characters or strings used to separate a base title from volume information (e.g., ` , `, ` - `, ` : `). This helps the module correctly identify items belonging to the same series.

#### Domain Bucket Rules (JSON)

This advanced setting allows you to create custom "domain buckets" to group items by broad subject areas, which can be more robust than relying on specific subject headings alone. Items belonging to the same bucket receive a score boost, helping to surface topically related materials.

- **Purpose**: To create thematic groupings (e.g., "History," "Science," "Art") based on patterns in your classification or call numbers. This is especially useful when dealing with multiple classification schemes (like NDC, DDC, and local schemes) in a single collection.
- **Format**: The setting takes a JSON object with two main keys: `fields` and `buckets`.
    - `fields`: Maps short names (e.g., `call_number`) to the Omeka properties you want to test against. This avoids repeating long property names in the rules.
    - `buckets`: An array of rule objects. Each object defines a single bucket:
        - `key`: A unique identifier for the bucket (e.g., `history`).
        - `labels`: A map of language codes to display names (e.g., `"en": "History"`).
        - `any` or `all`: Defines the logic for the conditions within. `any` matches if at least one condition is true (OR), while `all` requires every condition to be true (AND).
        - **Conditions**: An array of rule conditions. Each condition is an object with:
            - `field`: The short name of the property to test (defined in `fields`).
            - `op`: The comparison operator. Supported operators are:
                - `prefix`: Checks if the property value starts with the given string.
                - `contains`: Checks if the property value contains the given string.
                - `not_prefix`: Checks if the property value does *not* start with the given string.
            - `value`: The string to compare against.

**Example Rule**:
This rule defines a "philosophy" bucket. An item is assigned to this bucket if its `call_number` property starts with "1" or "ロ".

```json
{
  "key": "philosophy",
  "labels": {"ja": "哲学"},
  "any": [
    {"field": "call_number", "op": "prefix", "value": "1"},
    {"field": "call_number", "op": "prefix", "value": "ロ"}
  ]
}
```

### 3. Configuration Guide: Weights and Serendipity

This section provides guidance on tuning the scoring algorithm to achieve your desired recommendation behavior.

#### Recommended Weights (Balanced & Diverse)

These defaults provide a good starting point for a balanced mix of topical relevance and serendipity.

- **Core Signals (Candidate Selection + Scoring)**:
  - `Author ID`: 6
  - `Subject`: 5
  - `Authorized name (weak)`: 4
  - `Series title`: 3
  - `Publisher`: 2
  - `Item Set Match`: 2
- **Proximity & Equality (Scoring Only)**:
  - `Domain bucket`: 2
  - `Shelf Match`: 1
  - `Class proximity`: 1 (Threshold: 5)
  - `Material type equality`: 2
  - `Issued proximity`: 1 (Threshold: 5 years)
- **Penalties**:
  - `Bibliographic ID`: 0 (Used for penalty, not scoring)
  - `Penalty for same bibliographic record`: 150
  - `Penalty for same title`: 150

#### Rationale Behind the Weights

- **Strong Signals (Author ID, Subject)**: `Author ID` and `Subject` are the primary drivers of creator/topic affinity.
- **Fallback Signals (Authorized name (weak), Item Set Match)**: `Authorized name (weak)` (3) is a weaker author signal, while `Item Set Match` (2) provides a curated context, useful when other metadata is sparse.
- **"Stack-Browsing" Signals (Domain, Shelf Match, Class)**: These are intentionally weighted low (1-2) to add a flavor of physical "shelf browsing" and serendipity without overpowering the topical signals.
- **Light Boosts (Material, Issued)**: These provide a gentle nudge towards items of the same type or from a similar time period, adding subtle relevance.
- **Bib ID (0 weight + penalty)**: Items from the same series (e.g., volumes of a journal) are often plentiful. By setting the weight to 0 and applying a strong penalty (150), they are pushed down the list, making room for more diverse results while still being available if no better matches exist.

> Note: NCID is no longer used as a similarity signal in 0.4.0 and later; it has been removed from the settings UI.

#### Tuning Tips

- **For more author-centric results**: Increase the `Author ID` weight to 6 or 7.
- **For stronger topical matching**: If your subject cataloging is strong, increase the `Subject` weight to 6 or 7.
- **For a "stack-browsing" feel**: Gently increase `Shelf` or `Class proximity` to 2. Monitor results to ensure they don't become too homogeneous.

#### Serendipity and Diversity Controls

These settings work together to prevent results from being dominated by items from the same series.

- **Demote same bibliographic record (Switch)**: This is the master switch for diversity. When **On**:
  - The `Penalty for same bibliographic record` is applied to any item sharing the current item's Bib ID.
- **Demote same title (Switch)**: When **On**:
  - The `Penalty for same title` is applied to any item sharing the current item's base title.
- **When Off**: Both penalties are disabled. This can be useful for testing or if you want to prioritize direct series relationships.
- **Final-Stage Diversification**: After all scoring is complete, the module performs a final reordering step. It prioritizes showing items with *different* base titles first, which significantly enhances the variety of the results.

---

## Testing and Debugging

You can verify the module's behavior in two ways:

### 1. Debug Log

Enable `Debug log` in the module settings. All scoring, query, and diagnostic information for each request will be logged to `logs/application.log`. This is the best way to inspect the shelf-seeding process.

### 2. Async Endpoint with `debug=1`

Call the recommendation endpoint directly in your browser with the `debug=1` parameter.

**URL Format:**
`/similar-items/recommend?id={ITEM_ID}&limit=12&site={SITE_SLUG}&debug=1`

This returns a JSON object containing the rendered `html` and a `debug` payload.

- **`debug`**: An array of recommended items, each with:
    - `id`, `title`, `url`, `score`, `base_title`.
    - `signals`: An array of signals that contributed to the score (e.g., `['ncid', 6]`).
    - `values`: The underlying property values that triggered the signals, providing full transparency. Includes `properties`, `buckets`, `shelf`, and `class_number`.
- **`debug_meta`**: Context for the request, including the current item's bucket keys (`cur_buckets`).

### Per-request overrides (advanced)

For A/B testing or diagnostics, you can temporarily override some settings via query parameters:

- `tiebreak=none|consensus|strength|identity` — Override the tie-break policy for this request only.
- `item_sets_weight=NUMBER` — Override the weight contributed by item-set matches (e.g., `0` disables the item-set score boost).
- `item_sets_seed_only=1` — Use item sets for candidate expansion only; do not add any score for item-set matches.
- `jitter=0` — Disable light jitter for this request (forces deterministic ordering after normal tie-break rules). Use `jitter=1` to force-enable.

These overrides do not modify saved settings; they apply to the current request only.

---

## Theme Integration

The module is responsible for the "what" (the logic), while the theme is responsible for the "how" (the presentation).

- **Rendering Partial**: The module uses a simple partial (`view/similar-items/partial/list.phtml`) to render the list of items.
- **Theme Override**: A theme should provide its own `view/common/resource-page-blocks/similar-items.phtml`. This file is responsible for:
    - The loading container and any placeholder/spinner UI.
    - The JavaScript that calls the `/similar-items/recommend` endpoint and injects the returned HTML.
    - Localized strings for the block title or other UI elements.
- **Thumbnails**: The module attempts to use IIIF thumbnails (`/square/240,/0/default.jpg`) and falls back to standard Omeka thumbnails. The client-side script can implement a further fallback (e.g., to `/square/max/0/default.jpg`) if an image fails to load.
- **Title Length**: The maximum length of item titles is controlled by the theme via a theme setting (e.g., `similar_items_title_max_length`).

## Key Files

- `src/View/Helper/SimilarItems.php`: The core logic for scoring, seeding, and diversification.
- `src/Controller/RecommendController.php`: The async JSON endpoint.
- `Module.php`: Defines configuration keys and default values.

## License

MIT

============================================================

# SimilarItems (Omeka S モジュール)

このモジュールは、アイテムページに関連性の高い推奨資料を表示するための「類似アイテム」ページブロックを提供します。重み付けを柔軟に設定できるスコアリングエンジンを搭載し、利用者の操作を妨げない非同期描画を採用しています。

表示（UI）はアクティブなテーマが担当し、推奨ロジックの制御はすべてこのモジュールのグローバル設定で行います。

## 機能

- **設定可能なスコアリングエンジン**: 著者ID・典拠形著者名・主題・シリーズタイトル・出版者・分野バケット・アイテムセットなど複数のシグナルに重みを付けて、推奨の関連性を細かく調整できます。
- **非同期読み込み**: メインコンテンツの表示後にJSON API経由で推奨リストを読み込むため、ページの表示速度が低下しません。
- **高度なセレンディピティ制御**: 同一シリーズ（BibID）および同一ベースタイトルのアイテムにペナルティを与え、最終段階でベースタイトルの多様性を優先することで、表示のバラエティを高めます。
- **スマートな候補拡張**: アイテムセットや、著者・主題・シリーズ・出版者などマッピングされたプロパティを使って候補の母集団を広げつつ、内部的な候補数上限によりパフォーマンスを一定に保ちます。
- **一致回数ボーナス（オプション）**: 有効化すると、多値プロパティについて「いくつの値が一致したか」に応じて追加スコアを与えます（減衰率は設定可能）。
- **タイトル正規化**: 巻数や区切り文字を無視してベースタイトルを賢く判定（例：「タイトル, 上巻」と「タイトル, 下巻」は同じベースタイトルとして扱われます）。
- **微揺らぎ（Light Jitter）**: ページをリロードするたびに結果をわずかに変化させ、上位の関連性を損なうことなく新たな発見を促します。
- **豊富な診断機能**: デバッグモードを有効にすると、各アイテムがどのようにスコアリングされたかを正確に示す詳細なログと構造化JSONが出力されます。

---

## インストールと設定

### 1. インストール

1.  モジュールフォルダをOmeka Sの `modules` ディレクトリに `SimilarItems` として配置します。
2.  Omeka Sの管理画面で **モジュール** に移動し、「Similar Items」を有効化します。
3.  **サイト** → [対象サイト] → **リソースページの構成** に移動し、アイテムページのお好みの領域に「Similar items」ブロックを追加します。

### 2. 設定

すべての設定は **管理画面 → モジュール → Similar Items → 設定** にあります。設定は以下のセクションに分かれています。

#### 基本設定

モジュールの基本的な動作を制御します。

- **現在のサイトを範囲に含める**: （推奨：オン）推奨対象を現在のサイト内のアイテムに限定します。
- **結果の最大表示件数**: ページブロックに表示する類似アイテムの最大件数です。
- **デバッグログ**: （既定：オフ）有効にすると、詳細な診断情報が `logs/application.log` に書き込まれます。
- **デバッグ用JSONをペイロードに含める**: （既定：オフ）有効にすると、API応答にデバッグ情報が含まれます。
- **同点時の優先基準（タイブレーク）**: 合計スコアが同じ候補の並び替え規則を指定します。
  - `なし`（既定）: スコアのみで決定し、同点はそのままにします。
  - `一致シグナル数優先`: より多くの独立した肯定的シグナルで支持されている方を上位にします（例：主題＋著者＋棚 ＞ 著者＋棚）。
  - `最大重み優先`: 単一の一致の強さ（最も大きい重み）を優先します。

#### 候補拡大

類似アイテムを探す際の母集団（候補）を広げるための設定です。メタデータが少ない場合でも関連アイテムを見つけやすくします。

- **アイテムセットを類似判定に使用**: 共通のアイテムセットに属するアイテムを候補に加えます。

#### 微揺らぎ

結果に意図的な揺らぎ（ランダム性）を加えて、セレンディピティ（偶然の発見）を促進します。

- **微揺らぎ（ジッター）**: （既定：オフ）有効にすると、最終的なリストが上位スコアの少し広いプールからサンプリングされるようになり、リロードごとに順序や顔ぶれがわずかに変化します。
- **候補プール倍率**: 微揺らぎで使用する候補プールのサイズを、表示件数に対する倍率で定義します。例えば、表示件数が6件で倍率が1.5の場合、上位9件（6 * 1.5）の候補からランダムに6件が選ばれます。
- **同一タイトルしかない場合の処理**: 同じベースタイトルの候補しか見つからない場合の挙動を制御します。
    - `許可`（既定）：同一シリーズであっても、スコアが最も高いアイテムを表示します。
    - `除外`：多様性を最大化するため、同一ベースタイトルのアイテムを非表示にします。これにより候補が0件になった場合は、代わりにランダムなアイテム群が表示されます。

#### プロパティ対応付け

モジュールが利用する概念（請求記号、著者IDなど）と、お使いのOmeka Sが持つ語彙のプロパティを紐付けます。プロパティは、その役割に応じて以下のグループに分かれています。

- **候補に追加＋スコア加算**:
  - ここで指定されたプロパティの値が一致した場合、そのアイテムは類似候補として選ばれ、さらにスコアが加算されます。関連性を見つけるための最も基本的なシグナルです。
  - 代表的な対象: `著者ID`, `著者名典拠形（弱）`, `主題`, `シリーズタイトル`, `出版者`

- **候補拡大＋棚のスコア加算**:
  - `請求記号`を紐付けます。「棚情報を類似判定に使用」がオンの場合、ここで指定したプロパティを使って候補を拡大します。また、棚が一致する候補には常にスコアが加算されます。

- **近接・一致系（スコア加算のみ）**:
  - ここで指定されたプロパティは、候補を探すためには使われません。候補の中から条件（近接や一致）を満たすものを見つけてスコアを加算するためだけに使われます。
  - 対象: `分類記号`, `出版年`, `資料種別`

- **ペナルティ中心**:
  - `書誌ID`を紐付けます。主に、同一書誌のアイテムにペナルティを与えて表示順位を下げるために使われます。

- **デバッグ用**:
  - デバッグ時にのみ表示されるプロパティです。スコアリングには影響しません。
  - 対象: `所蔵館`, `閲覧注記`

#### ウェイトと閾値

スコアリングの重みと、近接判定の閾値を設定します。ウェイトの数値が大きいほど、そのシグナルが最終スコアに与える影響が強くなります。

- **重み**:
  - 各プロパティ（`著者ID` など）や概念（`棚記号`, `分類近接` など）の一致・近接が検出されたときに加算されるスコアの基本値です。
- **閾値**:
  - `分類近接の閾値`: この数値以内の分類番号を持つアイテムを「近い」と見なします。
  - `出版年近接の閾値`: この年数以内の出版年を持つアイテムを「近い」と見なします。

#### セレンディピティ

結果の多様性を高め、偶然の発見を促すための設定です。主にペナルティを利用して、似すぎているアイテムの表示順位を下げます。

- **同一書誌を抑制**: （推奨：オン）同じ書誌IDを持つアイテム（例: 全集の各巻）にペナルティを課し、結果が単調になるのを防ぎます。
- **同一タイトルを抑制**: （推奨：オン）同じベースタイトルを持つアイテムにペナルティを課します。
- **ペナルティの値**:
  - `同一書誌へのペナルティ`: 上記スイッチがオンのときに減算するスコア。
  - `同一タイトルへのペナルティ`: 上記スイッチがオンのときに減算するスコア。

#### 一致回数ボーナス（Multi-match）

複数値を持つプロパティ（例：主題が複数付与されている場合）について、「いくつ値が一致したか」に応じて追加スコアを与える仕組みです。

- **対象となるプロパティの例**:
  - `著者ID`
  - `著者名典拠形（弱）`
  - `主題`
  - `シリーズタイトル`
  - `出版者`
- **動作イメージ**:
  - まず、通常の一致に対して基本ウェイト（例：`主題` 5点）を加算します。
  - そのうえで、同じプロパティの別の値がさらに一致した場合に「ボーナス分」を追加します。
  - ボーナスは一致した値の個数に比例して増えますが、後ろの一致ほど控えめになるように減衰率を掛けて計算されます。
- **減衰率（decay）のイメージ**:
  - 減衰率を 0 に設定すると、「1つ一致したときの重み」と同じだけを常に加算します（値の数だけフラットに足していく挙動）。
  - 減衰率を 0.2 など 0 より大きな値にすると、2つ目以降の一致は徐々に小さなボーナスになります。
  - 減衰率を大きくするほど「2つ目以降の一致の増分」は小さくなり、「最初の一致」の重みが相対的に重要になります。
- **使いどころの例**:
  - 主題が多く付与されている資料同士で、「たくさんの主題が重なっているもの」をより高く評価したい場合。
  - シリーズタイトルや出版者などが複数値になりうる環境で、「1つだけ偶然一致したもの」よりも「複数の値が一致したもの」を優先したい場合。

#### タイトルルール

- **タイトル・巻の区切り文字**: ベースタイトルと巻数情報を区切る文字や文字列を定義します（例：` , `, ` - `, ` : `）。これにより、モジュールが同じシリーズに属するアイテムを正しく識別できます。

#### 分野バケットのルール（JSON）

この高度な設定では、アイテムを大まかな主題分野でグループ化するためのカスタム「分野バケット」を作成できます。これは、特定の主題見出しだけに頼るよりも堅牢な場合があります。同じバケットに属するアイテムはスコアがブーストされ、主題的に関連する資料が推薦されやすくなります。

- **目的**: 分類や請求記号のパターンに基づいて、テーマ別のグループ（例：「歴史」「科学」「芸術」）を作成します。これは、単一のコレクション内で複数の分類体系（NDC、DDC、独自体系など）を扱っている場合に特に便利です。
- **書式**: この設定は、`fields`と`buckets`という2つの主要なキーを持つJSONオブジェクトを受け取ります。
    - `fields`: 短い名前（例：`call_number`）を、ルールで使用したいOmekaプロパティにマッピングします。これにより、ルール内で長いプロパティ名を繰り返す必要がなくなります。
    - `buckets`: ルールオブジェクトの配列。各オブジェクトが1つのバケットを定義します。
        - `key`: バケットの一意な識別子（例：`history`）。
        - `labels`: 言語コードと表示名のマップ（例：`"ja": "歴史"`）。
        - `any`または`all`: 内部の条件の論理を定義します。`any`は少なくとも1つの条件が真であれば一致し（OR）、`all`はすべての条件が真である必要があります（AND）。
        - **条件**: ルール条件の配列。各条件は以下のキーを持つオブジェクトです。
            - `field`: テストするプロパティの短い名前（`fields`で定義）。
            - `op`: 比較演算子。サポートされている演算子は次のとおりです。
                - `prefix`: プロパティ値が指定された文字列で始まるかチェックします。
                - `contains`: プロパティ値が指定された文字列を含むかチェックします。
                - `not_prefix`: プロパティ値が指定された文字列で始まら *ない* かチェックします。
            - `value`: 比較対象の文字列。

**ルール例**:
このルールは「哲学」バケットを定義します。アイテムの`call_number`プロパティが「1」または「ロ」で始まる場合、このバケットに割り当てられます。


```json
{
  "key": "philosophy",
  "labels": {"ja": "哲学"},
  "any": [
    {"field": "call_number", "op": "prefix", "value": "1"},
    {"field": "call_number", "op": "prefix", "value": "ロ"}
  ]
}
```

### 3. 設定の指針：ウェイトとセレンディピティ

このセクションでは、望ましい推奨の挙動を実現するために、スコアリングアルゴリズムを調整するための指針を提供します。

#### 推奨ウェイト（バランスの取れた多様な設定）

これらのデフォルト値は、トピカルな関連性とセレンディピティのバランスの取れたミックスを提供するための良い出発点です。

- **コアシグナル（候補に追加＋スコア加算）**:
  - `著者ID`: 6
  - `主題`: 5
  - `著者名典拠形（弱）`: 4
  - `シリーズタイトル`: 3
  - `出版者`: 2
  - `アイテムセット一致`: 2
- **近接・一致系（スコア加算のみ）**:
    - `分野バケット`: 2
    - `棚一致`: 1
    - `分類近接`: 1 (閾値: 5)
    - `資料種別一致`: 2
    - `出版年近接`: 1 (閾値: 5年)
- **ペナルティ**:
    - `書誌ID`: 0 （スコア加算はせず、ペナルティにのみ使用）
    - `同一書誌へのペナルティ`: 150
    - `同一タイトルへのペナルティ`: 150

#### ウェイト設定の理論的背景

- **強力なシグナル（著者ID, 主題）**: `著者ID` と `主題` が著者・トピックの主要なシグナルです。
- **フォールバックシグナル（著者名典拠形, アイテムセット）**: `著者名典拠形` は弱めの著者シグナルであり、`アイテムセット` は、他のメタデータが乏しいときに有用なキュレーションされたコンテキストを提供します。
- **「書架散策」的シグナル（分野, 棚, 分類）**: これらは意図的に低く設定されており（1–2）、主題シグナルを圧倒することなく、物理的な「棚ブラウジング」のニュアンスとセレンディピティを加えます。
- **軽いブースト（資料種別, 出版年）**: これらは、同じタイプのアイテムや類似の時期に作成されたアイテムに対して微妙な関連性を追加します。
- **書誌ID（0ウェイト + ペナルティ）**: 同じシリーズのアイテム（例：ジャーナルの巻号）はしばしば豊富に存在します。ウェイトを0に設定し、強いペナルティを適用することで、より多様な結果のために押し下げられますが、より良い一致が存在しない場合には依然として利用可能です。

> 注: NCID は 0.4.0 以降、類似度シグナルとしては使用しておらず、設定画面からも削除されています。

#### 調整のヒント

- **著者中心の結果を増やすには**: `著者ID` のウェイトを6または7に増やします。
- **主題のマッチングを強化するには**: 主題のカタログが強力な場合、`主題` のウェイトを6または7に増やします。
- **「書架散策」感を出すには**: `棚一致` または `分類近接` を2に優しく増やします。結果があまりにも均質にならないように監視します。

#### セレンディピティと多様性の制御

これらの設定は、同じシリーズのアイテムによって結果が支配されるのを防ぐために連携して機能します。

- **同一書誌を抑制（スイッチ）**: これは多様性のためのマスタースイッチです。**オン**のとき：
    - `同一書誌へのペナルティ` が、現在のアイテムの書誌IDを共有するアイテムに適用されます。
- **同一タイトルを抑制（スイッチ）**: **オン**のとき：
    - `同一タイトルへのペナルティ` が、現在のアイテムのベースタイトルを共有するアイテムに適用されます。
- **オフのとき**: 両方のペナルティが無効になります。これはテストに便利な場合や、直接的なシリーズ関係を優先したい場合に役立ちます。
- **最終段階の多様化**: すべてのスコアリングが完了した後、モジュールは最終的な再配置ステップを実行します。異なるベースタイトルを持つアイテムを優先的に表示するようにし、結果の多様性を大幅に向上させます。

---

## テストとデバッグ

モジュールの動作は2つの方法で確認できます。

### 1. デバッグログ

モジュール設定で `デバッグログ` を有効にします。リクエストごとのスコアリング、クエリ、診断情報のすべてが `logs/application.log` に記録されます。棚シーディングのプロセスを調査するにはこの方法が最適です。

### 2. `debug=1` 付き非同期エンドポイント

ブラウザで直接推奨エンドポイントを呼び出します。

**URLフォーマット:**
`/similar-items/recommend?id={ITEM_ID}&limit=12&site={SITE_SLUG}&debug=1`

これにより、描画された `html` と `debug` ペイロードを含むJSONオブジェクトが返されます。

- **`debug`**: 推奨アイテムの配列。各アイテムには以下の情報が含まれます。
    - `id`, `title`, `url`, `score`, `base_title`
    - `signals`: スコアに貢献したシグナルの配列。
    - `values`: シグナルの根拠となったプロパティの実際の値。完全な透明性を提供します。`properties`, `buckets`, `shelf`, `class_number` を含みます。
- **`debug_meta`**: リクエストのコンテキスト情報。現在のアイテムのバケットキー（`cur_buckets`）など。

### リクエスト単位の一時上書き（上級）

A/Bテストや診断用途として、クエリパラメータで一部の設定を一時的に上書きできます。

- `tiebreak=none|consensus|strength|identity` — このリクエストに限り、タイブレーク方針を上書きします。
- `item_sets_weight=数値` — アイテムセット一致による加点の重みを上書きします（例：`0`でブーストを無効化）。
- `item_sets_seed_only=1` — アイテムセットは候補拡大のみに用い、スコアは加点しません。
- `jitter=0` — このリクエストに限り、微揺らぎ（ジッター）を無効化します（通常のタイブレーク規則の後は決定的な順序になります）。`jitter=1` で強制的に有効化。

これらの上書きは保存されている設定を変更せず、当該リクエストにのみ適用されます。

---

## テーマ連携

このモジュールは「何を表示するか」（ロジック）を担当し、テーマは「どう表示するか」（プレゼンテーション）を担当します。

- **描画パーシャル**: モジュールはアイテムリストを描画するためにシンプルなパーシャル（`view/similar-items/partial/list.phtml`）を使用します。
- **テーマによる上書き**: テーマ側で `view/common/resource-page-blocks/similar-items.phtml` を用意すべきです。このファイルは以下を担当します。
    - 読み込み中のコンテナや、プレースホルダ／スピナーなどのUI。
    - `/similar-items/recommend` エンドポイントを呼び出し、返されたHTMLを挿入するJavaScript。
    - ブロックタイトルなど、UI要素の多言語対応文字列。
- **サムネイル**: モジュールはIIIFサムネイル（`/square/240,/0/default.jpg`）を優先し、なければOmekaの標準サムネイルにフォールバックします。画像読み込みに失敗した場合、クライアント側スクリプトでさらにフォールバック（例：`/square/max/0/default.jpg`へ）を実装できます。
- **タイトル長**: アイテムタイトルの最大長は、テーマ設定（例：`similar_items_title_max_length`）によってテーマ側で制御します。

## 主要ファイル

- `src/View/Helper/SimilarItems.php`: スコアリング、種まき、多様化のコアロジック。
- `src/Controller/RecommendController.php`: 非同期JSONエンドポイント。
- `Module.php`: 設定キーと既定値を定義。

## ライセンス

MIT

