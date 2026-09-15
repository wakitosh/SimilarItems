# SimilarItems (Omeka S Module)

This module provides a "Similar Items" page block for Omeka S, designed to display contextually relevant recommendations on item pages. It uses a configurable, weighted scoring engine and renders results asynchronously for a smooth user experience.

The display is controlled by the active theme, while all recommendation logic is managed through this module's global settings.

## Features

- **Configurable Scoring Engine**: Fine-tune recommendation relevance using multiple weighted signals (Author ID, Authorized name, Subject, Series title, Publisher, Domain buckets, Item sets, etc.). Weights can be positive (boost) or negative (penalty).
- **Async Loading**: Recommendations are loaded via a JSON API after the main page content, preventing slow page loads.
- **Advanced Serendipity Control**: Promote diversity by penalizing items from the same series (BibID) and same base title, with final-stage diversification by base title.
- **Smart Candidate Expansion**: Expands the candidate pool using item sets and mapped properties (Author/Subject/Series/Publisher), with an internal hard cap to keep performance predictable.
- **Multi-match Bonus (Optional)**: When enabled, multi-valued properties add extra score based on how many distinct values match between the seed item and each candidate, with a configurable decay rate.
- **Title Normalization**: Intelligently groups items by their base title, ignoring volume numbers and separators (e.g., "Title, Vol. 1" and "Title, Vol. 2" are treated as having the same base title).
- **Light Jitter**: Subtly varies results on each page reload to increase discovery, without sacrificing top relevance.
- **Rich Diagnostics**: A debug mode provides detailed logs and a structured JSON payload, showing exactly how each recommendation was scored.
- **Usage Logging (research)**: Optional, self-contained collection of impressions, in-viewport views and clicks, with server-side reconstruction of recommendation-driven browsing chains. Browse and export as CSV/TSV from the admin UI - no access to the web server's raw logs required.
- **Control-Group Trial (research)**: Optional randomised assignment of visitors to a scored arm, a random-items arm and a hidden arm, so the effect of the feature and of the scoring engine can be measured without a before/after baseline.

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
  - `Exclude (no fallback)`: Hides same-title items. If this results in zero candidates, the block will remain empty (useful for debugging and evaluation).

#### Property Mappings

Connects the concepts used by the module to the properties in your Omeka S vocabulary. Properties are grouped by their role in the recommendation process.

- **Candidate Selection + Scoring**:
  - These are the primary signals for relevance. When a property value overlaps between the seed item and a candidate, the item is added to the candidate pool and its score is adjusted based on the corresponding weight. Matches on multi-valued properties can receive an additional multi-match bonus.
  - Properties: `Author ID`, `Authorized name`, `Subject`, `Series title`, `Publisher`.
  - Properties that expand candidates via "Domain buckets": `Call number`, `Class number`.

- **Scoring Only**:
  - These properties are not used for initial candidate selection, but they add to the score if a candidate is already in the pool and meets an equality or proximity condition.
  - Equality matches: `Material type`, `Publication place` (configured via Omeka's `Location` mapping).
  - Proximity/Derived matches: `Issued` (Issued proximity), `Call number` (Shelf match), `Class number` (Class proximity). Note that if Call number is missing, Class number may be used as a fallback for shelf matching. Additionally, both Call number and Class number can bring candidates into the pool via Domain buckets.

- **Penalty-focused**:
  - `Bibliographic ID`: Primarily used to apply a penalty to items from the same bibliographic record, suppressing same-series items in favor of diverse recommendations.

#### Weights and Thresholds

Configures the scoring weights and proximity thresholds. Higher weights give a signal more influence over the final score.

- **Weights**:
  - The base score applied when a match or proximity is detected for each property (e.g., `Author ID`) or concept (e.g., `Shelf match`, `Class proximity`).
  - **Shelf match evaluation**: Uses the first part of the `Call number` (up to a hyphen or space) for an exact match. If `Call number` is empty, the entire `Class number` is used.
  - **Class proximity evaluation**: Extracts numeric values from the `Class number` and compares them. If within the configured threshold, score is added. If `Class number` is empty, numeric values from `Call number` are extracted as a fallback.
  - Negative weights are allowed and act as penalties.
- **Thresholds**:
  - `Class proximity threshold`: Items with class numbers within this range are considered "close."
  - `Issued proximity threshold`: Items with publication years within this range are considered "close."

#### Serendipity

Settings designed to increase the diversity of results and promote discovery by penalizing items that are "too similar."

- **Demote same bibliographic record**: (Recommended: On) Master switch for diversity. When enabled, applies a penalty to items sharing the same bibliographic ID (e.g., volumes of a series).
- **Same base-title handling**: Controls whether same-base-title candidates are allowed, or excluded (with random fallback if none remain).
- **Penalty Values**:
  - `Penalty for same bibliographic record`: The score to subtract from candidates sharing the current item's Bib ID.
  - `Penalty for same base title`: The score to subtract from candidates sharing the current item's normalized base title.

#### Multi-match Bonus

Applies to multi-valued properties (e.g., items with multiple subjects). Adds extra score based on how many distinct values match between the seed item and each candidate.

- **Supported Properties**:
  - `Author ID`
  - `Authorized name`
  - `Subject`
  - `Series title`
  - `Publisher`
- **How it works**:
  - A standard match adds the base weight (e.g., `Subject` weight).
  - If additional values of the same property also match, a "bonus" score is added.
  - The bonus increases with the number of matched values but is multiplied by a decay rate so that subsequent matches contribute less.
- **Decay rate**:
  - If set to `0`, every match adds the same base weight (flat addition).
  - If set to `0.2` (or greater than `0`), the second and subsequent matches will yield progressively smaller bonuses.
  - Higher decay rates mean subsequent matches have less impact, making the first match relatively more important.
- **Use cases**:
  - When you want to assign higher scores to items that share *many* subjects, rather than just one.
  - When properties like series titles or publishers can hold multiple values, and you want to prioritize items that share multiple matching values over a single coincident shared value.

#### Title Rules

- **Title-volume separators**: Define strings used to separate a base title from volume information (e.g., ` , `, ` - `, ` : `).
  - Matching is **exact** (the configured string must appear as-is; leading/trailing spaces are significant).
  - Example: if you configure ` , ` then `, ` will **not** be treated as a separator.
  - When this setting is configured (non-empty), separators are the **only** base-title rule: the module will not automatically strip trailing numbers/years/volume markers unless a configured separator matches.

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
  - `Subject`: 4
  - `Authorized name`: 4
  - `Series title`: 3
  - `Publisher`: 2
  - `Item sets`: 3
- **Scoring Only**:
  - `Domain bucket`: 3
  - `Shelf match`: 2
  - `Class proximity`: 1 (Threshold: 5)
  - `Material type`: 2
  - `Publication place`: 1
  - `Issued proximity`: 1 (Threshold: 5 years)
- **Penalties**:
  - `Bibliographic ID`: 0 (Used for penalty, not scoring)
  - `Penalty for same bibliographic record`: 150
  - `Penalty for same base title`: 150

#### Rationale Behind the Weights

- **Strong Signals (Author ID, Subject)**: `Author ID` and `Subject` are the primary drivers of creator/topic affinity.
- **Fallback Signals (Authorized name, Item sets)**: `Authorized name` is a weaker author signal, while `Item sets` provides a curated context, useful when other metadata is sparse.
- **"Stack-Browsing" Signals (Domain bucket, Shelf match, Class proximity)**: These are intentionally weighted low to add a flavor of physical "shelf browsing" and serendipity without overpowering the topical signals.
- **Light Boosts (Material type, Issued proximity, Publication place)**: These provide a gentle nudge towards items of the same type, similar time period, or origin adding subtle relevance.
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
  - The `Penalty for same base title` is also applied.
- **When Off**: These penalties are disabled. This can be useful for testing or if you want to prioritize direct series relationships.
- **Final-Stage Diversification**: After all scoring is complete, the module performs a final reordering step. It prioritizes showing items with *different* base titles first, which significantly enhances the variety of the results.

---

## Testing and Debugging

You can verify the module's behavior in two ways:

### 1. Debug Log

Enable `Debug log` in the module settings. All scoring, query, and diagnostic information for each request will be logged to `logs/application.log`.

### 2. Async Endpoint with `debug=1`

Call the recommendation endpoint directly in your browser with the `debug=1` parameter.

**URL Format:**
`/similar-items/recommend?id={ITEM_ID}&limit=12&site={SITE_SLUG}&debug=1`

This returns a JSON object containing the rendered `html` and a `debug` payload.

- **`debug`**: An array of recommended items, each with:
    - `id`, `title`, `url`, `score`, `base_title`.
    - `signals`: An array of signals that contributed to the score (e.g., `['shelf', 2]`).
  - `values`: The underlying property values that triggered the signals, providing full transparency. Includes `properties`, `buckets`, `shelf`, `class_prefix`, and `class_number`.
- **`debug_meta`**: Context for the request (e.g., settings and request-scoped options).
- **`debug_seed`**: Seed item information for debugging, including the current item's bucket keys (`cur_buckets`).

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

- **Works out of the box**: The module ships a complete default block (`view/common/resource-page-blocks/similar-items.phtml`) that renders a placeholder, calls the `/similar-items/recommend` endpoint and injects the result, together with neutral default styling (`asset/css/similar-items.css`). No theme code is required for the scoring engine or the usage log to work.
- **Rendering Partial**: The module uses a simple partial (`view/similar-items/partial/list.phtml`) to render the list of items.
- **Theme Override (optional)**: A theme may provide its own `view/common/resource-page-blocks/similar-items.phtml` to take over:
    - The loading container and any placeholder/spinner UI.
    - The JavaScript that calls the `/similar-items/recommend` endpoint and injects the returned HTML.
    - Localized strings for the block title or other UI elements.

  An override must keep injecting the endpoint's `html` verbatim; that string carries the hidden usage-log payload. Give the container a `data-similar-items` attribute so the logging script can scope click tracking to the block.
- **Thumbnails**: The module attempts to use IIIF thumbnails (`/square/240,/0/default.jpg`) and falls back to standard Omeka thumbnails. The client-side script can implement a further fallback (e.g., to `/square/max/0/default.jpg`) if an image fails to load.
- **Title Length**: The maximum length of item titles is controlled by the theme via a theme setting (e.g., `similar_items_title_max_length`).

## Usage Logging (research)

The module can record how recommendations are actually used, so that browsing behaviour can be analysed without requesting access to the web server's raw access logs. Logging is **off by default**; enable it under *Modules → Similar Items → Configure → 利用ログ収集（研究用）*.

### What is recorded

Two tables are created on install (or on the first request after enabling):

**`similaritems_impression`** - one row per served recommendation request. Because the block is rendered on every item page that carries it, an impression row doubles as a page-view record and carries the browsing path of a session.

| Column | Purpose in analysis |
| --- | --- |
| `created_at`, `impression_key` | Time base and join key for events |
| `session_key`, `visitor_key` | Pseudonymous session grouping (see *Privacy*) |
| `seed_item_id`, `seed_item_title`, `seed_buckets`, `seed_item_sets` | Which item the visitor was on, and its subject domain(s) |
| `requested_limit`, `result_count`, `is_empty` | Exposure volume and miss rate |
| `candidate_count`, `duration_ms` | Cost of the scoring run (performance reporting) |
| `results` (JSON) | Per rank: item id, score, domain bucket, base title and which signals fired |
| `variant`, `config_hash`, `tiebreak`, `jitter` | Which algorithm configuration produced this list (A/B comparison) |
| `parent_event_id`, `chain_key`, `hop_depth` | Position in a recommendation-driven browsing chain |
| `entry_kind`, `referrer_host` | How the visitor reached the page: `similar_items`, `internal`, `search_engine`, `social`, `external`, `direct`. Reported by the browser (`document.referrer`) with the `view` event, so it is empty when that beacon is lost |
| `device`, `is_bot`, `locale`, `site_slug` | Segmentation |

**`similaritems_event`** - client-side events attached to an impression.

| Column | Purpose in analysis |
| --- | --- |
| `event_type` | `view` (the block entered the viewport) or `click` |
| `was_visible` | For a `view` row, whether the block was ever actually seen |
| `target_item_id`, `target_rank`, `target_score`, `target_signals` | Which recommendation was opened - position bias, score-response relation, signal effectiveness |
| `target_bucket`, `cross_domain` | Whether the click left the seed item's subject domain (the serendipity measure) |
| `dwell_ms`, `visible_ms` | Time from render, and from the block becoming visible, to the click |

Rank, score and signals are always taken from the stored impression rather than from the browser, so they cannot be forged by a client.

### How browsing chains are reconstructed

When a visitor clicks a recommendation, the click is recorded server-side. On the next item page, the module looks for an unconsumed click from the same session that targeted exactly this item within the configured window (default 300 s). If it finds one, the new impression inherits the `chain_key` and increments `hop_depth`; otherwise it starts a new chain at depth 0. The browser additionally passes the click key forward through `sessionStorage`, which corrects the match when the same item is reached twice in one session.

`hop_depth` therefore answers "how many recommendations in a row did this visitor follow?", and grouping by `chain_key` yields whole browsing paths.

### Privacy

- The session key is a random token in a first-party, `HttpOnly`, `SameSite=Lax` cookie (`si_slog`) with a sliding expiry. It contains no personal data and is only used to group requests into a browsing path. It can be switched off, in which case a daily-rotating pseudonymous key derived from IP and user agent is used instead (lower accuracy).
- IP addresses are stored as a salted hash by default; `raw` and `none` are also available.
- The signed-in user id is **not** recorded unless the operator opts in.
- Obvious bots are detected by user agent and excluded by default.
- Set a retention period in days to drop old rows; the policy is applied when an administrator opens the log dashboard.
- The hashing salt is created once at install time (and again when logging is switched on) and never rotated. The log dashboard shows it, together with the formula that reproduces a stored `client_ip` from a raw address - that is how this log can be joined to a web server access log when the log format cannot be changed. **Keep the salt out of published datasets**: the IPv4 space is small enough that releasing it alongside the hashes would make the addresses recoverable.

Announce the collection in your site's privacy policy before enabling it on a production site.

### Control-group trial

The log on its own can say which recommendation was chosen, but not whether showing recommendations changes behaviour at all, nor whether the scoring beats simply putting some items on the page. Two control arms answer that, by randomising visitors within the live site instead of relying on a before/after comparison.

| Arm | What the visitor sees | Contrast it provides |
| --- | --- | --- |
| `default` | Scored recommendations | - |
| `random` | The same block, same number of items, drawn at random | vs `default`: what the **scoring engine** contributes, holding interface, position count and layout constant. Also gives an empirical chance level for CTR. |
| `off` | Nothing; the block is hidden | vs `default`: what the **feature as a whole** contributes to browsing. |

Assignment is per session and deterministic: the arm is derived from the session key and a stored seed, so it never changes mid-session, needs no storage of its own, and can be recomputed during analysis. Weights are relative (e.g. 80 / 10 / 10). The trial is **off by default**; until an operator starts it every visitor gets `default`.

Each control arm can be switched on independently. A disabled arm weighs zero whatever its configured weight, and the remainder is reallocated automatically - turning `off` out of an 80/10/10 split leaves `default` 88.9% / `random` 11.1%. The log dashboard shows the effective allocation, not the raw weight fields.

**`random` alone is a complete experiment for the algorithm claim**, and it is both faster and milder than running `off` as well: the share given to `random` can be raised, and a visitor in that arm still gets a list of items. What it cannot establish is whether the feature as a whole increases browsing; for that, the descriptive share of page views reached via a recommendation (`entry_kind = similar_items`) and the before/after access-log analysis are the fallbacks.

Three properties make the arms comparable:

- **The `off` arm still records an impression.** Nothing is displayed, but the page view is logged, so session-level outcomes - pages per session, distinct items per session - exist in every arm. Without this the control sessions would be invisible.
- **The `off` arm is hidden from the layout stylesheet**, not from script, so there is no flash of a loading block that then disappears.
- **The seed item's domain buckets are recorded in every arm**, so `cross_domain` (the serendipity measure) is comparable across arms.

The admin dashboard shows a per-arm table: impressions, sessions, pages and items per session, share reached via a recommendation, in-viewport rate, CTR, CTR among viewed, cross-domain share and mean response time. `arm` is included in the impressions, events and chains exports.

Two caveats worth carrying into the analysis:

- The control arms deliberately degrade the service for part of the audience. Decide the split and the duration as a service question, not only a statistical one.
- The `default` arm is slower than `random`, because only it runs the scoring engine. A CTR difference could therefore partly reflect latency. `duration_ms` is recorded per impression so it can be conditioned on.

### Admin UI and exports

*Admin → Modules → Similar Items logs* provides:

- A **dashboard**: impressions, sessions, in-viewport rate, CTR (per impression and per viewed impression), **CTR by rank**, **hop-depth distribution**, chains with at least one hop, cross-domain click share, entry channels, devices, variants, and the most recommended-from / most clicked items.
- **Impression** and **event** tables with filters (date range, site, variant, device, seed item, session) and paging.
- **CSV / TSV export** of three datasets:
  - `impressions` - the raw impression rows, including the `results` JSON;
  - `events` - the raw view/click rows;
  - `chains` - one row per browsing chain: `chain_key`, session, start/end, page count, `max_hop`, entry channel and the ordered `item_path` (e.g. `10307;10791;11302`).

Timestamps are exported as ISO 8601 in the site's configured time zone, and CSV carries a UTF-8 BOM for spreadsheet software.

### Example analysis

```sql
-- CTR by rank (bots excluded)
SELECT e.target_rank, COUNT(*) AS clicks
FROM similaritems_event e
WHERE e.event_type = 'click' AND e.is_bot = 0
GROUP BY e.target_rank ORDER BY e.target_rank;

-- Browsing depth distribution
SELECT hop_depth, COUNT(*) AS page_views
FROM similaritems_impression WHERE is_bot = 0
GROUP BY hop_depth ORDER BY hop_depth;

-- Share of clicks that crossed a subject domain
SELECT AVG(cross_domain) AS cross_domain_rate
FROM similaritems_event
WHERE event_type = 'click' AND cross_domain IS NOT NULL AND is_bot = 0;
```

To compare configurations, set a **variant label** (e.g. `baseline`, `jitter-on`) for each trial period; `config_hash` additionally fingerprints the effective weights so configuration drift is visible even without a label.

### Theme requirements

None, and the client script does not depend on the theme's layout either: it is queued on the MVC render event rather than on the `view.layout` view event, which a theme that overrides `layout.phtml` can silently swallow. The module's bundled default block already uses the async endpoint, so logging works on an untouched theme. The endpoint prepends a hidden `<script type="application/json" data-similar-items-log>` payload to the rendered list, and the module ships its own client script (`asset/js/similar-items-log.js`), loaded automatically on public pages while logging is enabled. Any theme that injects the returned `html` into the page is logged correctly. Themes that render the list themselves can read the same payload from `log` in the JSON response.

### Caveats

- `view` and `click` events are sent with `navigator.sendBeacon`; a small loss rate is normal. Impressions are written server-side and are not affected.
- With jitter enabled, the same seed item yields different lists on reload. The `results` JSON is stored per impression, so rank-level analysis stays correct.
- Bot filtering is heuristic. Keep `is_bot` in the export and re-filter during analysis if needed.
- The entry channel comes from the browser: an impression whose `view` beacon never arrived keeps an empty `entry_kind`. Report that share rather than treating it as `direct`. `similar_items` is the exception - it is established server-side from the click chain and is not affected.
- For internal navigation the referrer is the full URL (including a search query string); for external referrers modern browsers send only the origin.

## Key Files

- `src/View/Helper/SimilarItems.php`: The core logic for scoring, seeding, and diversification.
- `src/Controller/RecommendController.php`: The async JSON endpoint; also writes the impression log.
- `src/Log/LogService.php`: Usage-log schema, writing, chain resolution and privacy handling.
- `src/Experiment/ArmAssigner.php`: Randomised, session-stable assignment to control arms.
- `src/Controller/EventController.php`: Public endpoint that receives view/click events.
- `src/Controller/Admin/LogsController.php`: Log dashboard, tables and CSV/TSV export.
- `view/common/resource-page-blocks/similar-items.phtml`: Default block; loads recommendations from the async endpoint.
- `asset/js/similar-items-log.js`: Theme-independent client script for view/click logging.
- `asset/css/similar-items.css`: Neutral default styling for the bundled block.
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
- **利用ログ収集（研究用）**: 推薦の表示（インプレッション）、実際に画面に入ったか（可視化）、クリックを記録し、推薦経由の回遊経路をサーバ側で復元します。管理画面から閲覧・CSV/TSV 出力でき、Apache 等の生ログへのアクセスを必要としません。
- **対照群試験（研究用）**: 閲覧者を「通常の推薦」「ランダム推薦」「非表示」に無作為割付でき、前後比較のベースラインなしに、機能そのものとスコアリングの効果を測定できます。

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
  - `完全除外（候補がなければそのまま）`：同一ベースタイトルのアイテムを非表示にします。候補が0件になった場合は、ランダム表示を行わず、そのまま0件になります（デバッグ・検証用途）。

#### プロパティ対応付け

モジュールが利用する概念と、お使いのOmeka Sが持つ語彙のプロパティを紐付けます。プロパティは、その役割に応じて機能が異なります。

- **候補に追加＋加点**:
  - ここで指定されたプロパティを現在のアイテムと共有するアイテムは、類似候補としてプールに追加され、設定した「重み」に基づくスコアが加算されます。「一致回数加点」が有効な場合、複数値の一致に応じて追加ボーナスを得られます。
  - 対象: `著者ID`, `著者名典拠形`, `主題`, `シリーズタイトル`, `出版者`
  - ※ `請求記号` および `分類記号` も、設定された「分野バケット」経由での候補範囲拡大に役立ちます。

- **加点のみ（候補選択には不使用）**:
  - ここで指定されたプロパティは、新たな候補を探すためには使われません。すでにピックアップされた候補の中から、一致や近接などの条件を満たしたものにスコアを加算するために用いられます。
  - 一致条件: `資料種別`（資料種別一致）, `出版地`（出版地一致／UI上は `Location` などのプロパティをマッピングできます）。
  - 近接・派生条件: `出版年`（出版年近接）, `請求記号`（棚記号一致）, `分類記号`（分類近接）。※ 請求記号と分類記号は「分野バケット」経由での候補範囲拡大のトリガーとしても機能します。また、請求記号がない場合は分類記号が棚記号や近接の代用（フォールバック）として使われる場合があります。

- **ペナルティ用**:
  - `書誌ID`: 主に、同一書誌の別ボリューム（同一シリーズの巻違いなど）に減点ペナルティを与え、表示順位を下げることで推奨結果の多様性を高めることに使われます。

#### ウェイトと閾値

スコアリングの重み（加点）と、近接判定の閾値を設定します。ウェイトの数値が大きいほど、そのシグナルが最終スコアに与える影響が強くなります。

- **重み**:
  - 各プロパティ（`著者ID`など）や一致条件（`棚記号`、`分類近接`など）に該当したときに加算されるスコアの基本値です。マイナスの数値を設定した場合はペナルティ（減点）として機能します。
  - **棚記号の判定**: 「請求記号」の前半（ハイフン・スペース等の区切り文字の前までの文字列）、または数値のみの場合は先頭の数字部分を切り出して完全一致で判定します。請求記号がない場合は分類記号全体を使用します。
  - **分類近接の判定**: 「分類記号」の最初の数字部分を切り出して数値として比較し、差が「閾値: 分類近接」以内であれば加点します。分類記号がない場合は請求記号の中から同様に数字を切り出して使用します。
- **閾値**:
  - `分類近接の閾値`: 分類番号の差がこの数値以内のアイテムを「近い」と見なします。
  - `出版年近接の閾値`: 出版年の差がこの年数以内のアイテムを「近い」と見なします。

#### セレンディピティ

結果の多様性を高め、偶然の発見を促すための設定です。主にペナルティを利用して、似すぎているアイテムの表示順位を下げます。

- **セレンディピティ: 同一書誌を抑制**: （推奨：オン）多様性のためのマスタースイッチです。オンのとき、同じ書誌ID（例: 全集の各巻）を持つ候補にペナルティを課します。
- **同一ベースタイトルの扱い**: 同一ベースタイトルの候補を許可するか、完全除外するかを制御します（除外で0件になった場合はランダム表示）。
- **ペナルティの値**:
  - `ペナルティ: 同一書誌ID`: 現在のアイテムと書誌IDが同じ候補から減算するスコア。
  - `ペナルティ: 同一ベースタイトル`: 現在のアイテムとベースタイトルが同じ候補から減算するスコア。

#### 一致回数ボーナス（Multi-match）

複数値を持つプロパティ（例：主題が複数付与されている場合）について、「いくつ値が一致したか」に応じて追加スコアを与える仕組みです。

- **対象となるプロパティの例**:
  - `著者ID`
  - `著者名典拠形`
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

- **タイトルと巻号の区切り文字**: ベースタイトルと巻数情報を区切る文字列を定義します（例：` , `, ` - `, ` : `）。
  - 判定は **完全一致**（指定した文字列がそのまま現れた場合のみ区切りとみなします。前後のスペースも区切りの一部です）。
  - 例：` , ` を指定した場合、`, ` は区切りとして扱われません。

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
  - `主題`: 4
  - `著者名典拠形`: 4
  - `シリーズタイトル`: 3
  - `出版者`: 2
  - `アイテムセット`: 3
- **近接・一致系（加点のみ）**:
  - `分野バケット`: 3
  - `棚記号`: 2
  - `分類近接`: 1 (閾値: 5)
  - `資料種別`: 2
  - `出版地`: 1
  - `出版年近接`: 1 (閾値: 5年)
- **ペナルティ**:
    - `書誌ID`: 0 （スコア加算はせず、ペナルティ判定にのみ使用）
    - `ペナルティ: 同一書誌ID`: 150
    - `ペナルティ: 同一ベースタイトル`: 150

#### ウェイト設定の理論的背景

- **強力なシグナル（著者ID, 主題）**: `著者ID` と `主題` が著者・トピックの主要なシグナルです。
- **フォールバックシグナル（著者名典拠形, アイテムセット）**: `著者名典拠形` は著者に対する「揺れ」のシグナルであり、`アイテムセット` はメタデータが乏しいときに有用なコンテキストを提供します。
- **「書架散策」的シグナル（分野バケット, 棚記号, 分類近接）**: これらは意図的に低く設定されており、主題シグナルを圧倒することなく、物理的な「棚ブラウジング」のニュアンスなどを加えます。「分類近接」の閾値を活用することで周辺の分類を幅広く拾うことができます。
- **軽いブースト（資料種別, 出版年近接, 出版地）**: 同じタイプのアイテムや類似の時期／同じ地域で出版されたアイテムに対して微小な関連性を追加します。主題や著者シグナルを上書きしない程度の控えめな値にするのが無難です。
- **書誌ID（0ウェイト + ペナルティ）**: 同じシリーズのアイテム（例：同じ全集の各巻）はしばしば豊富に存在します。ウェイトを0に設定し、強いペナルティを適用することで、他候補を上位に上げて多様な結果を提供します。より良い一致が存在しない場合には依然として推薦先として利用されます。

> 注: NCID は 0.4.0 以降、類似度シグナルとしては使用しておらず、設定画面からも削除されています。

#### 調整のヒント

- **著者中心の結果を増やすには**: `著者ID` のウェイトを6または7に増やします。
- **主題のマッチングを強化するには**: 主題のカタログが強力な場合、`主題` のウェイトを6または7に増やします。
- **「書架散策」感を出すには**: `棚記号` または `分類近接` を2に優しく増やします。結果があまりにも均質にならないように監視します。

#### セレンディピティと多様性の制御

これらの設定は、同じシリーズのアイテムによって結果が支配されるのを防ぐために連携して機能します。

- **セレンディピティ: 同一書誌を抑制（スイッチ）**: これは多様性のためのマスタースイッチです。
  - **オンのとき**: `ペナルティ: 同一書誌ID` および `ペナルティ: 同一ベースタイトル` が、それぞれ該当する候補のアイテムに適用されます。
  - **オフのとき**: これらのペナルティが無効になります。テストや直接的なシリーズ関係を優先表示したい場合に役立ちます。
- **最終段階の多様化**: すべてのスコアリングが完了した後、モジュールは最終的な再配置ステップを実行します。異なるベースタイトルを持つアイテムを優先的に表示するようにし、結果の多様性を大幅に向上させます。

---

## テストとデバッグ

モジュールの動作は2つの方法で確認できます。

### 1. デバッグログ

モジュール設定で `デバッグログ` を有効にします。リクエストごとのスコアリング、クエリ、診断情報のすべてが `logs/application.log` に記録されます。

### 2. `debug=1` 付き非同期エンドポイント

ブラウザで直接推奨エンドポイントを呼び出します。

**URLフォーマット:**
`/similar-items/recommend?id={ITEM_ID}&limit=12&site={SITE_SLUG}&debug=1`

これにより、描画された `html` と `debug` ペイロードを含むJSONオブジェクトが返されます。

- **`debug`**: 推奨アイテムの配列。各アイテムには以下の情報が含まれます。
    - `id`, `title`, `url`, `score`, `base_title`
    - `signals`: スコアに貢献したシグナルの配列。
  - `values`: シグナルの根拠となったプロパティの実際の値。完全な透明性を提供します。`properties`, `buckets`, `shelf`, `class_prefix`, `class_number` を含みます。
- **`debug_seed`**: デバッグのためのシード（対象）アイテム情報。現在のアイテムのバケットキー（`cur_buckets`）などを含みます。

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

- **テーマ改修なしで動作**: モジュールは完全に動作する既定ブロック（`view/common/resource-page-blocks/similar-items.phtml`）を同梱しています。プレースホルダを描画し、`/similar-items/recommend` エンドポイントを呼び出して結果を挿入します。最小限の既定スタイル（`asset/css/similar-items.css`）も付属します。スコアリングエンジンと利用ログを機能させるためにテーマ側の実装は不要です。
- **描画パーシャル**: モジュールはアイテムリストを描画するためにシンプルなパーシャル（`view/similar-items/partial/list.phtml`）を使用します。
- **テーマによる上書き（任意）**: テーマ側で `view/common/resource-page-blocks/similar-items.phtml` を用意すれば、以下を引き取れます。
    - 読み込み中のコンテナや、プレースホルダ／スピナーなどのUI。
    - `/similar-items/recommend` エンドポイントを呼び出し、返されたHTMLを挿入するJavaScript。
    - ブロックタイトルなど、UI要素の多言語対応文字列。

  上書きする場合も、エンドポイントが返す `html` をそのまま挿入してください。この文字列に利用ログ用の隠しデータが含まれます。また、クリック計測の範囲を限定できるよう、コンテナに `data-similar-items` 属性を付けてください。
- **サムネイル**: モジュールはIIIFサムネイル（`/square/240,/0/default.jpg`）を優先し、なければOmekaの標準サムネイルにフォールバックします。画像読み込みに失敗した場合、クライアント側スクリプトでさらにフォールバック（例：`/square/max/0/default.jpg`へ）を実装できます。
- **タイトル長**: アイテムタイトルの最大長は、テーマ設定（例：`similar_items_title_max_length`）によってテーマ側で制御します。

## 利用ログ収集（研究用）

推薦がどのように使われているかをモジュール内部で記録し、Apache 等の生ログを参照しなくても利用状況（回遊）を分析できるようにします。既定では**無効**です。*モジュール → Similar Items → 設定 → 利用ログ収集（研究用）* で有効化してください。

### 記録される内容

インストール時（または有効化後の最初のリクエスト時）に 2 つのテーブルを作成します。

**`similaritems_impression`** — 推薦を 1 回返すごとに 1 行。ブロックを含むアイテムページの表示ごとに必ず生成されるため、セッション内のページ遷移そのものを表す記録にもなります。

| カラム | 分析上の意味 |
| --- | --- |
| `created_at`, `impression_key` | 時刻と、イベントとの結合キー |
| `session_key`, `visitor_key` | 匿名のセッション識別（「プライバシー」参照） |
| `seed_item_id`, `seed_item_title`, `seed_buckets`, `seed_item_sets` | 閲覧中のアイテムと、その分野 |
| `requested_limit`, `result_count`, `is_empty` | 露出件数と空振り率 |
| `candidate_count`, `duration_ms` | スコアリングの計算コスト（性能報告用） |
| `results`（JSON） | 順位ごとのアイテム ID・スコア・分野・ベースタイトル・発火したシグナル |
| `variant`, `config_hash`, `tiebreak`, `jitter` | どの設定で生成された推薦か（A/B 比較用） |
| `parent_event_id`, `chain_key`, `hop_depth` | 推薦経由の回遊経路における位置 |
| `entry_kind`, `referrer_host` | 流入経路：`similar_items` / `internal` / `search_engine` / `social` / `external` / `direct`。`view` イベントでブラウザから受け取る `document.referrer` に基づくため、ビーコンが失われた場合は空になります |
| `device`, `is_bot`, `locale`, `site_slug` | セグメント分け |

**`similaritems_event`** — ブラウザ側で発生し、インプレッションに紐づくイベント。

| カラム | 分析上の意味 |
| --- | --- |
| `event_type` | `view`（ブロックが画面内に入った）または `click` |
| `was_visible` | `view` 行で、実際に見えたか／見えないまま離脱したか |
| `target_item_id`, `target_rank`, `target_score`, `target_signals` | 開かれた推薦：順位バイアス、スコアと反応の関係、シグナル別の効き方 |
| `target_bucket`, `cross_domain` | クリック先が元の分野の外かどうか（セレンディピティ指標） |
| `dwell_ms`, `visible_ms` | 描画時点／可視化時点からクリックまでの時間 |

順位・スコア・シグナルは常に保存済みインプレッションから引くため、クライアントから偽装できません。

### 回遊経路の復元方法

推薦がクリックされると、その事実がサーバ側に記録されます。次のアイテムページでは、同一セッションで「そのアイテムを対象とした未消費のクリック」が設定時間内（既定 300 秒）にあるかを探し、見つかれば新しいインプレッションが `chain_key` を引き継ぎ `hop_depth` を 1 増やします。見つからなければ深さ 0 の新しい経路として開始します。加えてブラウザ側が `sessionStorage` 経由でクリックキーを持ち越すため、同一セッション内で同じアイテムに複数回到達した場合でも対応関係が正しく補正されます。

そのため `hop_depth` は「推薦を何回連続でたどったか」を、`chain_key` でのグループ化は回遊経路全体を表します。

### プライバシー

- セッション識別子は第一者クッキー `si_slog`（`HttpOnly` / `SameSite=Lax`、スライド式有効期限）に保存されるランダム値です。個人情報は含まず、リクエストを一続きの経路にまとめる目的にのみ使用します。無効化した場合は、IP と User-Agent から日次で入れ替わる擬似 ID を代用します（精度は落ちます）。
- IP アドレスは既定でソルト付きハッシュとして保存します。`raw`（そのまま）と `none`（保存しない）も選べます。
- ログイン中のユーザ ID は、明示的に有効化しない限り記録しません。
- 明らかなボットは User-Agent により判定し、既定で除外します。
- 保持日数を設定すると、期限切れの行は管理画面のログ画面を開いたタイミングで削除されます。
- ハッシュ用ソルトはインストール時（およびログ有効化時）に一度だけ生成され、以後変更されません。管理画面のログ画面に、保存済み `client_ip` を生アドレスから再現する式とともに表示します。ログフォーマットを変更できない環境で、Web サーバのアクセスログと突合するための手段です。**ソルトは公開データに含めないでください**。IPv4 空間は総当たり可能な規模のため、ハッシュと同時に公開するとアドレスが復元可能になります。

本番サイトで有効化する前に、プライバシーポリシー等での告知をご検討ください。

### 対照群試験

ログだけでは「どの推薦が選ばれたか」は分かっても、「推薦を出すこと自体が行動を変えたか」「スコアリングが単に何かを並べるより優れているか」は分かりません。前後比較に頼らず、公開サイト内で閲覧者を無作為に振り分けることでこれに答えます。

| アーム | 閲覧者が見るもの | 得られる対比 |
| --- | --- | --- |
| `default` | 通常のスコアリング推薦 | - |
| `random` | 見た目も件数も同じで、資料だけ無作為 | `default` との比較で、UI・位置・件数を一定にしたまま**スコアリングの寄与**が測れます。CTR の偶然水準も得られます。 |
| `off` | 何も表示しない（ブロックごと非表示） | `default` との比較で、**機能全体が回遊に与える寄与**が測れます。 |

振り分けはセッション単位かつ決定的です。セッションキーと保存済みシードから導出するため、途中で表示が変わることがなく、専用の保存領域も不要で、分析時に再計算できます。配分は相対値（例 80 / 10 / 10）。試験は**既定で無効**で、開始するまで全員が `default` になります。

対照群アームは個別に有効・無効を切り替えられます。無効にしたアームは配分値にかかわらず重み 0 になり、残りで自動的に再配分されます（80/10/10 から `off` を外すと default 88.9% / random 11.1%）。管理画面のログ画面には設定値ではなく実効配分を表示します。

**`random` アームだけでもアルゴリズム評価の実験としては完結します。** その方が期間も短く、影響も軽くなります（`random` の配分を上げられ、このアームの閲覧者も資料一覧自体は見られるため）。成立しないのは「機能全体が回遊を増やしたか」という問いだけで、それには推薦経由到達率（`entry_kind = similar_items`）という記述統計と、生ログによる前後比較が代替になります。

比較可能性を担保している点が3つあります。

- **`off` アームでもインプレッションを記録します。** 何も表示しませんがページ閲覧は記録されるため、セッションあたりのページ数・到達資料数といったセッション単位の指標がすべてのアームで揃います。これがないと対照群のセッションが不可視になります。
- **`off` はレイアウトのスタイルシートで非表示にします**（スクリプトではなく）。読み込み中のブロックが一瞬見えてから消える、ということが起きません。
- **シード資料の分野バケットを全アームで記録します。** これにより `cross_domain`（セレンディピティ指標）をアーム間で比較できます。

管理画面にはアーム別の表を表示します：インプレッション、セッション、セッションあたりページ数・資料数、推薦経由到達率、可視化率、CTR、可視化ベース CTR、分野越え率、平均応答時間。`arm` は impressions / events / chains の各エクスポートに含まれます。

分析時に持ち越すべき注意が2点あります。

- 対照群は公開サービスの一部を意図的に劣化させます。配分と期間は統計上の判断だけでなく、サービス上の判断として決めてください。
- `default` アームだけがスコアリングを実行するため `random` より遅くなります。CTR の差に応答時間の影響が混じる可能性があります。`duration_ms` をインプレッション単位で記録しているので、統制変数として条件付けできます。

### 管理画面とエクスポート

*管理画面 → モジュール → Similar Items logs* で以下を提供します。

- **ダッシュボード**：インプレッション数、セッション数、可視化率、CTR（インプレッション基準／可視化基準）、**順位別 CTR**、**回遊深度の分布**、1 ホップ以上の経路数、分野越えクリック率、流入経路、デバイス、条件ラベル、推薦元・クリック先の上位アイテム。
- **インプレッション一覧**と**イベント一覧**（期間・サイト・条件ラベル・デバイス・シードアイテム・セッションで絞り込み、ページ送り対応）。
- 3 種類のデータセットの **CSV / TSV エクスポート**：
  - `impressions` — インプレッション生データ（`results` JSON を含む）
  - `events` — 表示・クリックの生データ
  - `chains` — 回遊経路ごとに 1 行：`chain_key`、セッション、開始／終了、ページ数、`max_hop`、流入経路、順序付きの `item_path`（例 `10307;10791;11302`）

日時はサイトのタイムゾーンで ISO 8601 として出力し、CSV には表計算ソフト向けに UTF-8 BOM を付与します。

### 分析例

```sql
-- 順位別クリック数（ボット除外）
SELECT e.target_rank, COUNT(*) AS clicks
FROM similaritems_event e
WHERE e.event_type = 'click' AND e.is_bot = 0
GROUP BY e.target_rank ORDER BY e.target_rank;

-- 回遊深度の分布
SELECT hop_depth, COUNT(*) AS page_views
FROM similaritems_impression WHERE is_bot = 0
GROUP BY hop_depth ORDER BY hop_depth;

-- 分野をまたいだクリックの割合
SELECT AVG(cross_domain) AS cross_domain_rate
FROM similaritems_event
WHERE event_type = 'click' AND cross_domain IS NOT NULL AND is_bot = 0;
```

設定を比較する場合は、試行期間ごとに**条件ラベル**（例 `baseline`, `jitter-on`）を設定してください。ラベルがなくても `config_hash` が実効設定の指紋になるため、設定変更の混入を検知できます。

### テーマ側の要件

ありません。クライアントスクリプトの読み込みもテーマのレイアウトに依存しません（`view.layout` ビューイベントではなく MVC の render イベントで登録するため。前者は `layout.phtml` を上書きしたテーマに握り潰されることがあります）。モジュール同梱の既定ブロックが非同期エンドポイントを使用するため、テーマを改修していなくてもログが取得できます。エンドポイントは描画済みリストの先頭に隠し要素 `<script type="application/json" data-similar-items-log>` を付加し、モジュール同梱のクライアントスクリプト（`asset/js/similar-items-log.js`）がログ有効時に公開ページへ自動で読み込まれます。返却された `html` をページに挿入するテーマであれば、そのまま正しく記録されます。リストを独自に描画するテーマは、JSON 応答の `log` キーから同じ情報を取得できます。

### 注意点

- `view` / `click` イベントは `navigator.sendBeacon` で送信するため、わずかな欠落は正常です。インプレッションはサーバ側で記録するため影響を受けません。
- 微揺らぎを有効にしていると、同じシードでもリロードごとに一覧が変わります。`results` はインプレッションごとに保存されるため、順位単位の分析は正しく行えます。
- ボット判定はヒューリスティックです。必要に応じてエクスポートに含まれる `is_bot` を使い、分析側で再フィルタしてください。
- 流入経路はブラウザから受け取るため、`view` ビーコンが届かなかったインプレッションは `entry_kind` が空になります。`direct` と混同せず、欠測として割合を報告してください。ただし `similar_items` はクリック連鎖からサーバ側で確定するため影響を受けません。
- サイト内遷移ではリファラは完全な URL（検索クエリ文字列を含む）ですが、外部サイトからの流入では最近のブラウザはオリジンのみを送信します。

## 主要ファイル

- `src/View/Helper/SimilarItems.php`: スコアリング、種まき、多様化のコアロジック。
- `src/Controller/RecommendController.php`: 非同期JSONエンドポイント。インプレッションログの記録も担当。
- `src/Log/LogService.php`: 利用ログのスキーマ、書き込み、回遊経路の解決、プライバシー処理。
- `src/Experiment/ArmAssigner.php`: 対照群アームへのセッション単位・決定的な無作為割付。
- `src/Controller/EventController.php`: 表示・クリックイベントを受け取る公開エンドポイント。
- `src/Controller/Admin/LogsController.php`: ログのダッシュボード、一覧、CSV/TSV エクスポート。
- `view/common/resource-page-blocks/similar-items.phtml`: 既定ブロック。非同期エンドポイントから推薦を取得します。
- `asset/js/similar-items-log.js`: テーマに依存しないクライアント側ログ収集スクリプト。
- `asset/css/similar-items.css`: 同梱ブロック用の最小限の既定スタイル。
- `Module.php`: 設定キーと既定値を定義。

## ライセンス

MIT

