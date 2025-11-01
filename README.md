## SimilarItems (Omeka S module)

This module registers a Resource Page Block Layout named `similarItems` so it appears in Admin → Sites → [site] → Configure resource pages. Rendering is async and theme-driven. The module provides global settings that control the similarity logic.

---

## For library staff (how to use)

Installation
1. Place this folder under `modules/SimilarItems` and activate it in Admin → Modules.
2. In Admin → Sites → [your site] → Configure resource pages, add the block “Similar items.”

Settings (Admin → Modules → Similar Items → Configure)
- Scope to site: On
- Use Item Sets seeding: On
- Item Set weight: 2
- Debug log: Off (turn On temporarily for testing; logs go to `logs/application.log`)
- Same-title handling when no alternatives exist: Allow (default) or Exclude
- Light jitter (reload-level variation): Off by default. When enabled, results are sampled from a slightly larger top pool so the list varies subtly on each reload without losing relevance.

Mappings (advanced)
- Call number: `dcndl:callNumber` / Class number: `dc:subject`
- Bib ID: `tsukubada:bibid` / NCID: `dcndl:sourceIdentifier`
- Author ID: `tsukubada:authorId` / Authorized name: `tsukubada:authorizedName`
- Subject: your subject term
- (Optional) Location, Issued, Material type, Viewing direction

Recommended weights (balanced, diverse)
- Bib ID: 0; NCID: 6; Author ID: 5; Authorized name: 3; Subject: 5
- Domain bucket: 2; Shelf: 1; Class proximity: 1; Threshold: 5
- Item Sets: 2

Why these weights (rationale)
- NCID (6): A strong bibliographic signal that often links near‑editions/printings without collapsing to the exact same volume. It pulls truly related records while avoiding “duplicates.”
- Author ID (5): An authority‑based exact link across a creator’s works. Strong, but slightly below NCID to keep topical balance and prevent author‑only clusters.
- Authorized name (3): Weaker than Author ID because of name variants/homonyms; used as a fallback when IDs are missing.
- Subject (5): High topical affinity but can be broad/noisy depending on cataloging practice. Balanced with NCID/Author to avoid overly general lists.
- Domain bucket (2), Shelf (1), Class proximity (1): Light “stack browsing” flavor. These signals add serendipity via physical/notation proximity but are kept low to avoid homogenizing the list.
- Item Sets (2): Curated group membership is useful especially when mappings are sparse; a modest weight prevents one set from dominating results.
- Bib ID (0) + penalty: Series/volume siblings are plentiful. With a strong penalty for same BibID (and same base title), they won’t flood the top while still appearing when alternatives are scarce.

Diversity and thresholds
- Class proximity threshold (≈5) allows near neighbors in classification without drifting too far.
- Final “diversification” stage prefers different base titles first; the above weights cooperate by preventing any single signal from overwhelming variety.

Tuning tips
- More author‑centric lists: raise Author ID (and possibly Authorized name) by +1–2.
- Strong, well‑curated subjects: raise Subject to 6–7; consider lowering Shelf/Class if lists look too homogeneous.
- Stack‑browsing feel: gently raise Shelf to 2 or Class proximity to 2, but monitor variety.

Serendipity
- Demote same Bib ID: On; Penalty: 150
- Same base title penalty: automatically the same as above (min 100)
- If "Same-title handling" is Exclude and no recommendation candidates remain, the module fetches a random set of items (site-scoped if enabled) so something is shown.

Title–volume separators (one per line)
- Default: ` , `; you may add `，` or `、` or ` - `. Minor spacing/variant differences are handled.

Title display
- Max display title length: configurable (default 60; set 0 for unlimited).

What you should see
- A good mix of “related but not identical” items. Series volumes (same base title/Bib ID) are pushed down, and, if alternatives exist, different titles are prioritized. If no alternatives exist at all, the module will show volumes later in the list.

Testing (two options)
1) Turn on Debug log and open an item page. The helper logs signals, queries, and final counts to `logs/application.log`.
2) Call the async endpoint with `debug=1`:
   `/similar-items/recommend?id={ITEM_ID}&limit=12&site={SITE_SLUG}&debug=1`
   → JSON contains `html` and `debug` arrays (id, title, url, score, base_title, signals).

---

## For developers (how it works)

Signals and scoring
- Equality queries: NCID / Author ID / Authorized name / Subject
- Seeding: Item Sets (fallback for sparse mappings)
- Proximity: Domain bucket / Shelf / Class (threshold)
- Penalties: Same base title / Same Bib ID (configurable)

Final-stage diversification
- After scoring and sorting, results are reordered to prefer different base titles first, then fill remaining, and show same-series items last if needed. This improves variety even when physical arrangement (shelf/class) is strong.

Async endpoint
- `GET /similar-items/recommend?id=...&limit=...&site=...` returns `{ html }`
- Add `debug=1` to also get `{ debug: [...] }` with structured rows.

Thumbnails
- Prefer IIIF `/square/240,/0/default.jpg` from media `info.json`, falling back to Omeka `thumbnailUrl('square'|'medium')`. If the IIIF server rejects upscaling, the client falls back to `/square/max/0/default.jpg` automatically.

Jitter behavior (when enabled)
- Sorting: score ties are randomized per reload before falling back to modified date.
- Selection: when candidates exceed the display limit, a slightly larger top pool is sampled with score-based weights so lineups vary while keeping relevance.
- If the number of candidates equals the limit, the composition is fixed, but the order among equal-score items still changes per reload.

Key files
- `src/View/Helper/SimilarItems.php` — scoring, penalties, diversification
- `src/Controller/RecommendController.php` — async JSON (+debug)
- `view/similar-items/partial/list.phtml` — HTML list
- Theme: `themes/*/view/common/resource-page-blocks/similar-items.phtml` — loader and strings

Defaults
- See `Module.php` for install/form defaults and setting keys `similaritems.*`.

License: MIT

============================================================

## SimilarItems（Omeka S モジュール）

このモジュールは、管理画面 → サイト →［サイト名］→ リソースページの構成に表示される `similarItems` というブロックレイアウトを登録します。描画は非同期でテーマ側が担当し、類似度ロジックはモジュールのグローバル設定で制御できます。

---

## 図書館スタッフ向け（使い方）

インストール
1. このフォルダを `modules/SimilarItems` に配置し、管理画面 → モジュール で有効化します。
2. 管理画面 → サイト →［対象サイト］→ リソースページの構成で「Similar items」ブロックを追加します。

設定（管理画面 → モジュール → Similar Items → 設定）
- サイトを範囲に含める：オン
- アイテムセットで種まき：オン
- アイテムセット重み：2
- デバッグログ：オフ（テスト時のみオン。出力先は `logs/application.log`）
- 代替候補が無いときの同一タイトルの扱い：許可（既定）/ 除外
- 微揺らぎ（リロード毎にわずかに変動）：既定オフ。オンにすると上位候補のやや広いプールから重み付きで抽出し、関連性を保ったまま表示が微妙に入れ替わります。

マッピング（上級者向け）
- 請求記号：`dcndl:callNumber` ／ 分類記号：`dc:subject`
- BibID：`tsukubada:bibid` ／ NCID：`dcndl:sourceIdentifier`
- 著者ID：`tsukubada:authorId` ／ 権限定名：`tsukubada:authorizedName`
- 件名：使用している件名の語彙
- （任意）所在、発行年、資料種別、綴じ方向

推奨ウェイト（バランス良く多様性重視）
- BibID：0、NCID：6、著者ID：5、権限定名：3、件名：5
- 分野バケット：2、棚：1、分類近接：1、閾値：5
- アイテムセット：2

なぜこの配分か（背景・理由）
- NCID（6）: 同一巻を避けつつ、版や刷の近い書誌をうまく引き寄せる強い信号。重複に落ちず「本当に関連が強い」領域を押し上げます。
- 著者ID（5）: 権威IDによる厳密なリンク。著者偏重になりすぎないように NCID よりわずかに低く設定し、主題とのバランスを取っています。
- 権限定名（3）: ID 不在時の代替。表記揺れや同名異人の影響があるため、ID より弱めに設定。
- 件名（5）: 主題的な近さを強く反映。ただし目録実務により広すぎたりノイズが混ざる場合があるため、NCID/著者とのバランスで 5 に。
- 分野バケット（2）・棚（1）・分類近接（1）: 「棚での近さ」によるセレンディピティを少量付与。均質化を避けるため弱めにとどめています。
- アイテムセット（2）: キュレーションされた集合の近さは有用（特にマッピングが疎な場合）が、支配的にならない程度に控えめ。
- BibID（0）＋強いペナルティ: 同一シリーズ（巻違い）が多数出やすいため、上位を独占しないよう強く抑制。代替が無いときは後段で拾います。

多様性と閾値
- 分類近接の閾値（≈5）は「近すぎず遠すぎない」範囲に調整。
- 最終段の「多様化」により、まず異なるベースタイトルを優先。上記ウェイトは単一の信号が多様性を壊さないよう配慮しています。

調整の指針
- 著者中心に寄せたい: 著者ID（必要に応じて権限定名も）を +1〜2。
- 件名付与の品質が高い: 件名を 6〜7 に引き上げ、均質化が見られる場合は棚／分類の重みを下げる。
- 「棚ブラウジング」感を強めたい: 棚を 2、または分類近接を 2 へ。ただし一覧の多様性に注意して微調整。

セレンディピティ（多様性）
- 同一BibIDの降格：オン、ペナルティ 150
- 同一ベースタイトルの降格：上記と同程度（最低100）
- 「同一タイトルの扱い」を「完全除外」にし、推薦候補が残らない場合は、（サイト範囲設定がオンならそのサイト内で）無作為にアイテムを取得して表示します。

タイトル・巻区切り（1 行に 1 つ）
- 既定値は ` , `。必要に応じて `，` や `、`、` - ` を追加できます。空白や全角・半角の差はある程度吸収されます。

タイトル表示
- 表示タイトルの最大文字数を設定可能（既定 60。0 で無制限）。

期待される表示
- 「同じではないが関連が強い」アイテムが混ざった一覧になります。シリーズ（同一ベースタイトル/BibID）は下位に回され、代替がある場合は異なるタイトルが優先されます。代替が全く無い場合は、シリーズ巻が後段に表示されます。

テスト方法（2 通り）
1) デバッグログをオンにしてアイテムページを開くと、シグナル・検索・最終件数が `logs/application.log` に出力されます。
2) `debug=1` を付けて非同期エンドポイントを叩きます：
   `/similar-items/recommend?id={ITEM_ID}&limit=12&site={SITE_SLUG}&debug=1`
   → `html` と `debug`（id, title, url, score, base_title, signals）を含む JSON が返ります。

---

## 開発者向け（仕組み）

シグナルとスコアリング
- 等価一致：NCID／著者ID／権限定名／件名
- 種まき：アイテムセット（マッピングが疎な場合のフォールバック）
- 近接：分野バケット／棚／分類（閾値）
- ペナルティ：同一ベースタイトル／同一BibID（設定可能）

最終段の多様化
- スコア付けとソート後、まず「異なるベースタイトル」を優先して 1 件ずつ採用し、次に残りを充填、最後に同一シリーズを追加します。棚・分類の近接が強い場合でも一覧の多様性が向上します。

非同期エンドポイント
- `GET /similar-items/recommend?id=...&limit=...&site=...` は `{ html }` を返します。
- `debug=1` を付けると `{ debug: [...] }` で構造化行（id, title, url, score, base_title, signals）も返します。

サムネイル
- メディアの `info.json` から IIIF `/square/240,/0/default.jpg` を優先し、無い場合は Omeka の `thumbnailUrl('square'|'medium')` を使用します。サーバーが拡大を許可しない場合は、クライアント側で自動的に `/square/max/0/default.jpg` にフォールバックします。

ジッターの挙動（有効時）
- ソート: スコアが同点の要素は、modified の前に「リロード毎にランダム」な順序で並び替えます。
- 選択: 候補が表示件数を超える場合は、上位のやや広いプールからスコア重み付きで抽出するため、関連性を保ったまま顔ぶれが変化します。
- 候補数が表示件数と同数のときは、顔ぶれは固定ですが、同点帯の並びはリロード毎に入れ替わります。

主要ファイル
- `src/View/Helper/SimilarItems.php` — スコア・ペナルティ・多様化
- `src/Controller/RecommendController.php` — 非同期 JSON（+debug）
- `view/similar-items/partial/list.phtml` — HTML リスト
- テーマ：`themes/*/view/common/resource-page-blocks/similar-items.phtml` — ローダーと文言

既定値
- インストール/フォーム既定値と `similaritems.*` の設定キーは `Module.php` を参照してください。

ライセンス：MIT

