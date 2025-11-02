# SimilarItems (Omeka S Module)

This module provides a "Similar Items" page block for Omeka S, designed to display contextually relevant recommendations on item pages. It uses a configurable, weighted scoring engine and renders results asynchronously for a smooth user experience.

The display is controlled by the active theme, while all recommendation logic is managed through this module's global settings.

## Features

- **Configurable Scoring Engine**: Fine-tune recommendation relevance using multiple weighted signals.
- **Async Loading**: Recommendations are loaded via a JSON API after the main page content, preventing slow page loads.
- **Advanced Serendipity Control**: Promote diversity by penalizing items from the same series (BibID) and prioritizing different titles.
- **Smart Seeding**: Expands the candidate pool using item sets and call number proximity ("shelf seeding") to surface relevant items even with sparse metadata.
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

All settings are located in **Admin → Modules → Similar Items → Configure**.

#### Basic Settings

- **Scope to current site**: (Recommended: On) Restricts recommendations to items within the current site.
- **Use Item Sets for seeding**: (Recommended: On) Uses items from shared item sets as an initial pool of candidates. A good fallback when other signals are sparse.
- **Debug log**: (Default: Off) When enabled, detailed diagnostic information is written to `logs/application.log`.
- **Same-title handling**: Controls behavior when only same-title candidates are found.
    - `Allow` (Default): Shows the highest-scoring items, even if they are from the same series.
    - `Exclude`: Hides same-title items to maximize diversity. If this results in zero candidates, the block will be filled with a random selection of items instead.
- **Light jitter**: (Default: Off) If enabled, the final list is sampled from a slightly larger pool of top-scoring items, causing the order and selection to vary subtly on each page reload.

#### Mappings

Map the module's concepts to your vocabulary's properties.

| Concept                 | Purpose                                                              |
| ----------------------- | -------------------------------------------------------------------- |
| **Call number**         | Used for shelf seeding and class proximity.                          |
| **Class number**        | Used for class-based proximity signals. Falls back to call number.   |
| **Bibliographic ID**    | Identifies a bibliographic record (e.g., a book series). Used for penalties. |
| **NCID**                | A strong signal for related works (e.g., different editions).        |
| **Author ID**           | An authority-controlled identifier for a creator.                    |
| **Authorized name**     | A fallback for author matching when IDs are unavailable.             |
| **Subject**             | Matches items with shared subjects.                                  |
| **Location** (Optional) | For debug display.                                                   |
| **Issued** (Optional)   | Used for the "Issued proximity" boost.                               |
| **Material** (Optional) | Used for the "Material type equality" boost.                         |
| **Viewing** (Optional)  | For debug display.                                                   |

#### Shelf Seeding

This feature helps discover physically co-located items.

- **Enable Shelf seeding**: (Default: Off) When enabled, the module seeds the candidate pool with items that have a similar call number prefix (i.e., are on the same "shelf").
- **How it works**:
    1.  It first attempts a "starts-with" search on the normalized call number for precision.
    2.  If that yields few results, it can fall back to a broader "like" search.
    3.  It normalizes full-width characters to half-width (e.g., "２１０" → "210") to improve match rates.
    4.  A final post-filter ensures only exact shelf matches are used for scoring.
- **Diagnostics**: When `Debug log` is on, detailed shelf seeding statistics are logged (e.g., `scanned`, `exact`, `added`, `mismatched`), helping to diagnose why few or no shelf-based candidates may appear.

#### Title Normalization

- **Title–volume separators**: Define characters or strings used to separate a base title from volume information (e.g., ` , `, ` - `, ` : `). This helps the module correctly identify items belonging to the same series.

#### Domain Bucket Rules (JSON)

This advanced setting allows you to create custom "domain buckets" to group items by broad subject areas, which can be more robust than relying on specific subject headings alone. Items belonging to the same bucket receive a score boost, helping to surface topically related materials.

- **Purpose**: To create thematic groupings (e.g., "History," "Science," "Art") based on patterns in your classification or call numbers. This is especially useful when dealing with multiple classification schemes (like NDC, DDC, and local schemes) in a single collection.
- **Format**: The setting takes a JSON object with two main keys: `fields` and `buckets`.
    - `fields`: Maps short names (e.g., `call_number`) to the Omeka properties you want to test against. This avoids repeating long property names in the rules.
    - `buckets`: An array of rule objects. Each object defines a single bucket:
        - `key`: A unique identifier for the bucket (e.g., `history`).
        - `labels`: A map of language codes to display names (e.g., `"ja": "歴史"`).
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

- **Core Signals**:
    - `NCID`: 6
    - `Author ID`: 5
    - `Subject`: 5
    - `Authorized name`: 3
    - `Item Sets`: 2
- **Proximity & Boosts**:
    - `Domain bucket`: 2
    - `Shelf`: 1
    - `Class proximity`: 1 (Threshold: 5)
    - `Material type equality`: 2
    - `Issued proximity`: 1 (Threshold: 5 years)
- **Bibliographic ID**:
    - `Bib ID weight`: 0
    - `Same Bib ID penalty`: 150

#### Rationale Behind the Weights

- **Strong Signals (NCID, Author ID, Subject)**: `NCID` (6) is weighted highest as it links different editions or printings without being an exact duplicate. `Author ID` (5) and `Subject` (5) provide strong creator and topic affinity.
- **Fallback Signals (Authorized name, Item Sets)**: `Authorized name` (3) is a weaker author signal, while `Item Sets` (2) provides a curated context, useful when other metadata is sparse.
- **"Stack-Browsing" Signals (Domain, Shelf, Class)**: These are intentionally weighted low (1-2) to add a flavor of physical "shelf browsing" and serendipity without overpowering the topical signals.
- **Light Boosts (Material, Issued)**: These provide a gentle nudge towards items of the same type or from a similar time period, adding subtle relevance.
- **Bib ID (0 weight + penalty)**: Items from the same series (e.g., volumes of a journal) are often plentiful. By setting the weight to 0 and applying a strong penalty (150), they are pushed down the list, making room for more diverse results while still being available if no better matches exist.

#### Tuning Tips

- **For more author-centric results**: Increase the `Author ID` weight to 6 or 7.
- **For stronger topical matching**: If your subject cataloging is strong, increase the `Subject` weight to 6 or 7.
- **For a "stack-browsing" feel**: Gently increase `Shelf` or `Class proximity` to 2. Monitor results to ensure they don't become too homogeneous.

#### Serendipity and Diversity Controls

These settings work together to prevent results from being dominated by items from the same series.

- **Demote same Bib ID (Switch)**: This is the master switch for diversity. When **On**:
    - The `Same Bib ID penalty` is applied to any item sharing the current item's Bib ID.
    - The `Same base title penalty` is also automatically applied.
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

- **設定可能なスコアリングエンジン**: 複数のシグナル（信号）に重みを付けて、推奨の関連性を細かく調整できます。
- **非同期読み込み**: メインコンテンツの表示後にJSON API経由で推奨リストを読み込むため、ページの表示速度が低下しません。
- **高度なセレンディピティ制御**: 同一シリーズ（BibID）のアイテムにペナルティを与え、異なるタイトルを優先することで、表示の多様性を高めます。
- **スマートな種まき（Seeding）**: アイテムセットや請求記号の近さ（棚シーディング）を利用して候補の母集団を広げ、メタデータが少ない場合でも関連アイテムを発見します。
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

すべての設定は **管理画面 → モジュール → Similar Items → 設定** にあります。

#### 基本設定

- **現在のサイトを範囲に含める**: （推奨：オン）推奨対象を現在のサイト内のアイテムに限定します。
- **アイテムセットで種まき**: （推奨：オン）共通のアイテムセットに属するアイテムを候補の初期プールとして使用します。他のシグナルが少ない場合の有効なフォールバックです。
- **デバッグログ**: （既定：オフ）有効にすると、詳細な診断情報が `logs/application.log` に書き込まれます。
- **同一タイトルの扱い**: 同じベースタイトルの候補しか見つからない場合の挙動を制御します。
    - `許可`（既定）：同一シリーズであっても、スコアが最も高いアイテムを表示します。
    - `除外`：多様性を最大化するため、同一ベースタイトルのアイテムを非表示にします。これにより候補が0件になった場合は、代わりにランダムなアイテム群が表示されます。
- **微揺らぎ**: （既定：オフ）有効にすると、最終的なリストが上位スコアの少し広いプールからサンプリングされるようになり、リロードごとに順序や顔ぶれがわずかに変化します。

#### マッピング

モジュールの概念を、お使いの語彙のプロパティに紐付けます。

| 概念                  | 目的                                                       |
| --------------------- | ---------------------------------------------------------- |
| **請求記号**          | 棚シーディングや分類近接で使用します。                     |
| **分類記号**          | 分類ベースの近接シグナルで使用。請求記号で代替可。         |
| **書誌ID (BibID)**    | 書誌レコード（例：叢書）を識別。ペナルティ判定で使用。     |
| **NCID**              | 関連書誌（例：異なる版）を示す強力なシグナル。             |
| **著者ID**            | 典拠コントロールされた著者識別子。                         |
| **権威化された名称**  | 著者IDがない場合の著者マッチングの代替。                   |
| **主題**              | 共通の主題を持つアイテムをマッチングします。               |
| **所在** (任意)       | デバッグ表示用。                                           |
| **発行年** (任意)     | 「発行年近接」ブーストで使用します。                       |
| **資料種別** (任意)   | 「資料種別一致」ブーストで使用します。                     |
| **綴じ方向** (任意)   | デバッグ表示用。                                           |

#### ウェイトとペナルティ

スコアリングアルゴリズムを制御します。ウェイトが高いほど影響が強くなります。

- **シグナルのウェイト**:
    - 推奨既定値：NCID: 6, 著者ID: 5, 件名: 5, 権威化された名称: 3, 分野バケット: 2, アイテムセット: 2, 棚: 1, 分類近接: 1。
    - **軽いブースト（任意）**:
        - `資料種別の一致`: 資料種別が同じ場合に小さなボーナス（既定：+2）。
        - `発行年の近接`: 出版年が一定の範囲内（既定：±5年）にある場合に小さなボーナス（既定：+1）。

- **ペナルティ（セレンディピティのため）**:
    - `同一BibIDの降格`: （スイッチ）オンのとき、現在のアイテムと同じBibIDを持つアイテムに強いペナルティを課します。
    - `同一BibIDペナルティ`: （値）上記スイッチがオンのときに減算するスコア（例：150）。
    - `同一ベースタイトルのペナルティ`: 「同一BibIDの降格」がオンのときに自動的に適用されます。これにより、同じシリーズの巻違いなどが結果を独占するのを防ぎます。

#### 棚シーディング

物理的に近くに配架されている資料を発見しやすくする機能です。

- **棚シーディングを有効にする**: （既定：オフ）有効にすると、請求記号の接頭辞が似ている（＝同じ「棚」にある）アイテムを候補プールに加えます。
- **動作の仕組み**:
    1.  まず、正規化された請求記号に対して高精度な「前方一致」検索を試みます。
    2.  それで十分な結果が得られない場合、より広範な「like」検索にフォールバックすることがあります。
    3.  全角文字を半角に正規化（例：「２１０」→「210」）し、一致率を向上させます。
    4.  最終的な後段フィルタで、スコアリングには厳密に棚が一致したもののみを使用します。
- **診断**: `デバッグログ` がオンの場合、棚シーディングの詳細な統計情報（例：`scanned`, `exact`, `added`, `mismatched`）がログに出力され、棚ベースの候補がなぜ少ないかの診断に役立ちます。

#### タイトル正規化

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

- **コアシグナル**:
    - `NCID`: 6
    - `著者ID`: 5
    - `主題`: 5
    - `権威化された名称`: 3
    - `アイテムセット`: 2
- **近接 & ブースト**:
    - `Domain bucket`: 2
    - `Shelf`: 1
    - `Class proximity`: 1 (Threshold: 5)
    - `Material type equality`: 2
    - `Issued proximity`: 1 (Threshold: 5 years)
- **書誌ID**:
    - `Bib ID weight`: 0
    - `Same Bib ID penalty`: 150

#### ウェイト設定の理論的背景

- **強力なシグナル（NCID, Author ID, Subject）**: `NCID` (6) は、異なる版や印刷物をリンクするために最も高く設定されています。`Author ID` (5) と `Subject` (5) は、著者とトピックの強い親和性を提供します。
- **フォールバックシグナル（Authorized name, Item Sets）**: `Authorized name` (3) は弱めの著者シグナルであり、`Item Sets` (2) は、他のメタデータが乏しいときに有用なキュレーションされたコンテキストを提供します。
- **「スタックブラウジング」シグナル（Domain, Shelf, Class）**: これらは意図的に低く設定されており（1-2）、トピカルシグナルを圧倒することなく、物理的な「棚ブラウジング」のニュアンスとセレンディピティを加えます。
- **軽いブースト（Material, Issued）**: これらは、同じタイプのアイテムや類似の時期に作成されたアイテムに対して微妙な関連性を追加します。
- **Bib ID（0ウェイト + ペナルティ）**: 同じシリーズのアイテム（例：ジャーナルの巻号）はしばしば豊富に存在します。ウェイトを0に設定し、強いペナルティ（150）を適用することで、より多様な結果のために押し下げられますが、より良い一致が存在しない場合には依然として利用可能です。

#### 調整のヒント

- **著者中心の結果を増やすには**: `Author ID` のウェイトを6または7に増やします。
- **トピックマッチングを強化するには**: 主題のカタログが強力な場合、`Subject` のウェイトを6または7に増やします。
- **「スタックブラウジング」感を出すには**: `Shelf` または `Class proximity` を2に優しく増やします。結果があまりにも均質にならないように監視します。

#### セレンディピティと多様性の制御

これらの設定は、同じシリーズのアイテムによって結果が支配されるのを防ぐために連携して機能します。

- **同一Bib IDの降格（スイッチ）**: これは多様性のためのマスタースイッチです。**オン**のとき：
    - `Same Bib ID penalty` が、現在のアイテムのBib IDを共有するアイテムに適用されます。
    - `Same base title penalty` も自動的に適用されます。
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
    - `signals`: スコアに貢献したシグナルの配列（例：`['ncid', 6]`）。
    - `values`: シグナルの根拠となったプロパティの実際の値。完全な透明性を提供します。`properties`, `buckets`, `shelf`, `class_number` を含みます。
- **`debug_meta`**: リクエストのコンテキスト情報。現在のアイテムのバケットキー（`cur_buckets`）など。

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

