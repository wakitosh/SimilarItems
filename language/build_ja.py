#!/usr/bin/env python3
"""Build language/ja.po (and ja.mo with msgfmt) for the Similar Items logs screens.

The msgids are read from the code, so a string added to a template without a
translation stops the build. Strings Omeka core already translates are left to
core: a module's translation is merged into the same text domain and would
otherwise change the wording of core screens too.

Run from the module root:  python3 language/build_ja.py
"""
import io
import re
import subprocess
import sys

FILES = [
    "view/similar-items/logs/index.phtml",
    "view/similar-items/logs/list.phtml",
    "view/similar-items/logs/help.phtml",
    "view/similar-items/logs/partial/impressions.phtml",
    "view/similar-items/logs/partial/events.phtml",
    "src/Controller/Admin/LogsController.php",
]
# Codes shown through $codeLabel(kind, code) in the views.
CODES = {
    "device": ["desktop", "mobile", "tablet", "unknown"],
    "entry": ["direct", "search_engine", "external", "internal", "similar_items", "social"],
    "arm": ["default", "random", "off"],
    "event": ["view", "click"],
}
EXTRA = ["Similar Items logs"]  # navigation label (config/module.config.php)
CORE_PO = "../../application/language/ja.po"

JA = {
    "%1$s (%2$s)": "%1$s（%2$s）",
    "Similar Items logs": "類似アイテムの利用ログ",
    "Similar Items logs: events": "利用ログ：イベント",
    "Similar Items logs: impressions": "利用ログ：インプレッション",
    "Similar Items logs: how to read this page": "利用ログ：この画面の見方",
    "Usage logging is currently disabled. Enable it in the SimilarItems module configuration to start collecting data.":
        "利用ログの収集は現在無効です。データを集めるには、SimilarItems モジュールの設定で有効にしてください。",
    "Impressions": "インプレッション",
    "Events": "イベント",
    "Log summary": "集計",
    "Export impressions (CSV)": "インプレッションを書き出す（CSV）",
    "Export events (CSV)": "イベントを書き出す（CSV）",
    "Export chains (CSV)": "回遊経路を書き出す（CSV）",
    "Export impressions (TSV)": "インプレッションを書き出す（TSV）",
    "Export data": "書き出し",
    "The current filter applies to the export as well.": "書き出しにも、上の絞り込みの条件がそのまま適用されます。",
    "Export CSV": "CSV で書き出す",
    "Export TSV": "TSV で書き出す",
    "How to read this page": "この画面の見方",
    "Back to the logs": "ログに戻る",
    "Date range": "期間",
    "Site slug": "サイト（スラッグ）",
    "Variant label": "バリアント名",
    "Device": "端末",
    "Devices": "端末",
    "(any)": "（すべて）",
    "Seed item ID": "シード資料の ID",
    "Session key": "セッションキー",
    "Include bots": "ボットを含める",
    "Placement trial only": "配置試験の対象のみ",
    "Delete logs": "ログの削除",
    "Target date/time": "対象の日時",
    "Up to now": "現在まで",
    "Before date/time": "指定日時より前",
    "Between date/times": "指定期間",
    "Delete the matching impression and event logs? This cannot be undone.":
        "条件に合うインプレッションとイベントのログを削除しますか？元に戻せません。",
    "Export what you need before deleting: log rows are the research data.":
        "削除の前に、必要なデータを書き出してください。ログの各行は研究データです。",
    "Joining this log to a web server access log": "Web サーバのアクセスログとの突き合わせ",
    "Client addresses are stored as a salted hash. Apply the same formula to the addresses in an access log to match its rows against these ones.":
        "閲覧者のアドレスはソルト付きのハッシュで保存しています。アクセスログのアドレスに同じ式を当てはめると、その行とこのログの行を対応づけられます。",
    "Keep the salt out of published datasets.": "ソルトは公開するデータセットに含めないでください。",
    "The IPv4 space is small enough to enumerate, so releasing the salt alongside the hashes would make the addresses recoverable. The salt is created once and never rotated: changing it would make earlier and later hashes incomparable.":
        "IPv4 のアドレス空間は総当たりできる大きさなので、ハッシュと一緒にソルトを公開するとアドレスを復元できてしまいます。ソルトは一度だけ作られ、変更されません。変えると、それより前と後のハッシュを比べられなくなります。",
    "%s empty": "推薦0件 %s",
    "Sessions": "セッション",
    "%s distinct seed items": "異なりシード資料 %s",
    "Viewed (in viewport)": "ブロックの画面到達",
    "%s of impressions": "インプレッションの %s",
    "%s never on screen": "画面に入らず %s",
    "%s not reported": "報告なし %s",
    "Clicks": "クリック",
    "CTR %1$s · %2$s among viewed": "CTR %1$s・画面到達のうち %2$s",
    "Recommendation-driven hops": "推薦経由の回遊",
    "max depth %d": "最大深度 %d",
    "Chains with ≥1 hop": "推薦を1回以上たどった経路",
    "avg %s pages": "平均 %s ページ",
    "Cross-domain clicks": "分野越えクリック",
    "%s of %s classified clicks": "分野を判定できたクリック %2$s 件中 %1$s 件",
    "Response time": "応答時間",
    "avg %s candidates, %s shown": "平均 候補 %1$s 件・提示 %2$s 件",
    "Sidebar list": "右サイドバーの一覧",
    "Row below the viewer": "ビューア下の列",
    "Floating button": "右下のフローティング",
    "Off": "無効",
    "Scheduled": "開始待ち",
    "Running": "実施中",
    "Finished": "終了",
    "Placement trial": "配置試験",
    "Status:": "状態：",
    "Period:": "期間：",
    "when enabled": "有効にした時点",
    "no end": "終了日時なし",
    "Restrict this whole page to the trial": "この画面全体を試験対象だけに絞る",
    "Assigned placement": "割り当てられた配置",
    "Items seen": "項目が見られた",
    "Items seen rate": "見られた率",
    "Clicks per impression": "インプレッションあたりのクリック",
    "Clicks per items seen": "見られたあたりのクリック",
    "all": "全体",
    "Sample-ratio check: chi-square = %1$s (%2$d degrees of freedom), p = %3$s.":
        "振り分けの偏りの点検：χ² = %1$s（自由度 %2$d）、p = %3$s。",
    "The arm sizes are far from an equal split. Page views may be going unrecorded in one placement only (a block not assigned on a site, a display fault). Do not trust the comparison until the cause is known.":
        "群の大きさが等分から大きくずれています。ある配置でだけページ閲覧が記録されていない可能性があります（ブロックの割り当て漏れ、表示の不具合など）。原因が分かるまで、比較の結果は信用できません。",
    "The arm sizes are within chance of an equal split.": "群の大きさは、等分からの偶然のずれの範囲です。",
    "Impressions where the placement shown differs from the one assigned: %1$s of %2$s. A few can come from cached pages or a settings change in another tab; many mean the block assignment should be checked.":
        "割り当てと実際に表示された配置が食い違うインプレッション：%1$s 件（全 %2$s 件）。少数ならキャッシュされたページや別タブでの設定変更、多ければブロックの割り当てを確認してください。",
    "\"Items seen\" counts impressions where at least one recommended entry was half on screen; its rate is the main measure of the trial. Placements are compared as assigned, not as shown. Signed-in visits and bots are not included.":
        "「項目が見られた」は、推薦項目の少なくとも1件の半分以上が画面に入ったインプレッションです。「見られた率」はその割合で、試験の主要な指標です。比較は、実際に表示された配置ではなく、割り当てられた配置で行います。ログイン中の閲覧とボットは含みません。",
    "No trial impressions yet.": "試験対象のインプレッションはまだありません。",
    "Control-group trial": "対照群試験",
    "Currently allocating:": "現在の配分：",
    "The trial is not running; every visitor gets the normal recommendations. The figures below are historical.":
        "試験は実施されていません。全員が通常の推薦を見ています。以下は過去の数字です。",
    "\"default vs off\" shows what the feature contributes to browsing; \"default vs random\" shows what the scoring contributes on top of simply putting items on the page. Pages and items per session exist in every arm, including the one where nothing is displayed.":
        "「通常と非表示」の比較は、機能が回遊にもたらすものを示します。「通常とランダム」の比較は、資料を並べるだけの場合に比べてスコアリングがもたらすものを示します。セッションあたりのページ数と資料数は、何も表示しない群を含むすべての群で得られます。",
    "Arm": "アーム",
    "Pages / session": "ページ／セッション",
    "Items / session": "資料／セッション",
    "Via recommendation": "推薦経由",
    "Viewed": "画面到達",
    "CTR (viewed)": "CTR（画面到達のうち）",
    "Cross-domain": "分野越え",
    "ms": "ミリ秒",
    "(none)": "（なし）",
    "\"Viewed\" counts only blocks that actually reached the screen; an impression whose block was never on screen is reported too, but is not counted here. The \"off\" arm records impressions and displays nothing, so its viewed/CTR columns are empty by construction. Export the impressions dataset for a proper statistical comparison; these figures are for monitoring.":
        "「画面到達」は、実際に画面に入ったブロックだけを数えます。一度も画面に入らなかったインプレッションも報告はされますが、ここには数えません。「非表示（off）」の群はインプレッションを記録するだけで何も表示しないため、画面到達と CTR の列は仕組み上空になります。統計的な比較には、インプレッションを書き出して使ってください。この表は監視用です。",
    "No impressions recorded under any arm yet.": "どのアームにも、まだインプレッションがありません。",
    "Click-through rate by rank": "順位別クリック率",
    "No data yet.": "まだデータがありません。",
    "Rank": "順位",
    "Offered": "提示数",
    "\"Offered\" counts impressions that actually contained that rank, so CTR is comparable across positions.":
        "「提示数」は、その順位を実際に含んでいたインプレッションの数です。そのため、CTR を順位どうしで比べられます。",
    "Browsing depth (hops via recommendations)": "回遊深度（推薦をたどった回数）",
    "Hop depth": "深度",
    "Page views": "ページ閲覧数",
    "entry": "入口",
    "Depth 0 is an item page reached some other way; depth N means N consecutive recommendation clicks.":
        "深度0は推薦以外の経路で到達した資料ページ、深度Nは推薦をN回続けてたどったことを表します。",
    "Not recorded (before 0.6.0: sidebar list)": "記録なし（0.6.0 より前。右サイドバーの一覧）",
    "Where recommendations were shown": "表示場所別",
    "Placement": "配置",
    "Block on screen (legacy)": "ブロックの画面到達（従来）",
    "Items seen: at least one recommended entry was half on screen. This is the measure to compare placements with: the same rule holds for every block, and for the floating button it can only happen while the panel is open. Recorded from 0.6.0; earlier rows have no value and appear as not seen here.":
        "項目が見られた：推薦項目の少なくとも1件の半分以上が画面に入ったこと。配置の比較にはこちらを使います。どのブロックにも同じ規則が当てはまり、フローティングではパネルが開いているときにしか成立しません。0.6.0 から記録しており、それより前の行は値がないため、ここでは見られていない扱いになります。",
    "Block on screen (legacy): 40% of the block was on screen, the rule used before 0.6.0. It is kept so the sidebar figures stay comparable with earlier data, but it favours short blocks (the row below the viewer counts as seen when only its heading shows) and does not apply to the floating button, where it records the panel being opened.":
        "ブロックの画面到達（従来）：ブロックの40%が画面に入ったこと。0.6.0 より前の規則です。右サイドバーの数字を過去のデータと比べられるよう残していますが、背の低いブロックに有利で（ビューア下の列は見出しが見えただけで成立します）、フローティングには当てはまりません（パネルが開かれたことを記録します）。",
    "One page view is one impression even when a page shows several blocks; such pages are listed under the combination.":
        "複数のブロックを表示したページでも、1回のページ閲覧はインプレッション1件です。そのようなページは、ブロックの組み合わせとして1行にまとめています。",
    "On pages with several blocks: which block": "複数のブロックがあるページ：どのブロックか",
    "Block": "ブロック",
    "First item seen here": "ここで最初に項目が見られた",
    "How visitors arrived": "流入経路",
    "Variants": "バリアント",
    "Most recommended-from items": "推薦元として多い資料",
    "Seed item": "シード資料",
    "Most clicked recommendations": "よくクリックされた推薦",
    "Clicked item": "クリックされた資料",
    "%d impressions": "インプレッション %d 件",
    "Delete the selected impressions and their events? This cannot be undone.":
        "選択したインプレッションとそのイベントを削除しますか？元に戻せません。",
    "Select all on this page": "このページをすべて選択",
    "Time": "日時",
    "Shown": "提示",
    "Pool": "候補",
    "Hop": "深度",
    "Entry": "流入",
    "Session": "セッション",
    "Variant": "バリアント",
    "Results (rank: id, score)": "結果（順位: ID、スコア）",
    "No logs yet.": "ログはまだありません。",
    "(bot)": "（ボット）",
    "(empty)": "（0件）",
    "%d selected": "%d 件を選択中",
    "First": "最初",
    "Last": "最後",
    "Page %d / %d": "%d / %d ページ",
    "%d events": "イベント %d 件",
    "Seed": "シード",
    "Target": "クリック先",
    "Score": "スコア",
    "Signals": "シグナル",
    "Domain": "分野",
    "Dwell": "滞在",
    "Since visible": "画面到達から",
    "No events yet.": "イベントはまだありません。",
    "never visible": "画面に入らず",
    "cross": "分野越え",
    "same": "同じ分野",
    "Retention policy removed %d expired impression log(s).": "保存期間の設定により、期限切れのインプレッションのログを %d 件削除しました。",
    "Security token is invalid.": "セキュリティトークンが無効です。",
    "No impression was selected.": "インプレッションが選択されていません。",
    "The selected impressions no longer exist.": "選択したインプレッションは、すでに存在しません。",
    "Deleted %1$d impression(s) and %2$d event(s).": "インプレッション %1$d 件とイベント %2$d 件を削除しました。",
    "%d impression(s) that followed a deleted one were detached and their chains recomputed.":
        "削除したインプレッションに続いていた %d 件を切り離し、回遊経路を計算し直しました。",
    "Failed to delete: ": "削除に失敗しました：",
    "Please specify a valid date/time range.": "正しい日時の範囲を指定してください。",
    "Please specify a valid date and time.": "正しい日時を指定してください。",
    "Deleted %d impression log(s).": "インプレッションのログを %d 件削除しました。",
    "Failed to delete logs: ": "ログの削除に失敗しました：",
    "Failed to read logs: ": "ログの読み込みに失敗しました：",
    "(not classified)": "（未判定）",
    "(unknown)": "（不明）",
    "device:desktop": "パソコン",
    "device:mobile": "スマートフォン",
    "device:tablet": "タブレット",
    "device:unknown": "不明",
    "entry:direct": "直接",
    "entry:search_engine": "検索エンジン",
    "entry:external": "外部サイト",
    "entry:internal": "サイト内",
    "entry:similar_items": "推薦経由",
    "entry:social": "SNS など",
    "arm:default": "通常",
    "arm:random": "ランダム",
    "arm:off": "非表示",
    "event:view": "表示",
    "event:click": "クリック",
}


def msgids_in_code():
    ids = []
    for path in FILES:
        text = io.open(path, encoding="utf-8").read()
        for m in re.finditer(r"translate\(\s*'((?:[^'\\]|\\.)*)'\s*\)", text):
            v = m.group(1).replace("\\'", "'")
            if v not in ids:
                ids.append(v)
    for kind, codes in CODES.items():
        for code in codes:
            if f"{kind}:{code}" not in ids:
                ids.append(f"{kind}:{code}")
    for v in EXTRA + ["(not classified)", "(unknown)", "%1$s (%2$s)"]:
        if v not in ids:
            ids.append(v)
    return ids


def core_translated():
    core = {}
    text = io.open(CORE_PO, encoding="utf-8").read()
    for m in re.finditer(r'msgid "((?:[^"\\]|\\.)*)"\nmsgstr "((?:[^"\\]|\\.)+)"', text):
        core[m.group(1).replace('\\"', '"')] = m.group(2)
    return core


def po_escape(s):
    return s.replace("\\", "\\\\").replace('"', '\\"')


def main():
    ids = msgids_in_code()
    core = core_translated()
    ours = [i for i in ids if i not in core]
    missing = [i for i in ours if i not in JA]
    clash = [i for i in JA if i in core]
    if missing or clash:
        for i in missing:
            print("MISSING translation:", repr(i), file=sys.stderr)
        for i in clash:
            print("ALREADY in Omeka core (remove from JA):", repr(i), core[i], file=sys.stderr)
        sys.exit(1)
    for i in ours:
        # Same placeholders in the same number (positional order may differ).
        def ph(s):
            return sorted(re.sub(r"%(\d+)\$", "%", p) for p in re.findall(r"%(?:\d+\$)?[sd]", s))
        if ph(i) != ph(JA[i]):
            print("PLACEHOLDER mismatch:", repr(i), file=sys.stderr)
            sys.exit(1)
    unused = [k for k in JA if k not in ids]
    lines = [
        "# Japanese translation of the SimilarItems module (usage-log screens).",
        "# Generated by language/build_ja.py; edit the JA table there, not this file.",
        'msgid ""',
        'msgstr ""',
        '"Project-Id-Version: SimilarItems\\n"',
        '"MIME-Version: 1.0\\n"',
        '"Content-Type: text/plain; charset=UTF-8\\n"',
        '"Content-Transfer-Encoding: 8bit\\n"',
        '"Language: ja\\n"',
        '"Plural-Forms: nplurals=1; plural=0;\\n"',
        "",
    ]
    for i in ours:
        if re.search(r"%(?:\d+\$)?[sd]", i):
            lines.append("#, php-format")
        lines.append(f'msgid "{po_escape(i)}"')
        lines.append(f'msgstr "{po_escape(JA[i])}"')
        lines.append("")
    io.open("language/ja.po", "w", encoding="utf-8").write("\n".join(lines))
    subprocess.run(["msgfmt", "--check", "-o", "language/ja.mo", "language/ja.po"], check=True)
    print(f"{len(ours)} strings translated, {len(ids) - len(ours)} left to Omeka core"
          + (f", {len(unused)} unused entries in JA: {unused}" if unused else ""))


if __name__ == "__main__":
    main()
