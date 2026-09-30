<?php

/**
 * @file
 * Shared setup for the Similar Items block templates.
 *
 * Included (not rendered as a partial) by the block templates so they can use
 * the variables it defines: $item, $itemId, $siteSlug, $endpointUrl, $isJa,
 * $heading, $bubbleText, $moduleLimit. Also queues the shared loader, which
 * fetches once for every block on the page.
 *
 * @var \Laminas\View\Renderer\PhpRenderer $this
 */

$item = $this->vars()->offsetExists('item')
    ? $this->vars()->offsetGet('item')
    : ($this->vars()->offsetExists('resource') ? $this->vars()->offsetGet('resource') : null);
$itemId = ($item && method_exists($item, 'id')) ? (int) $item->id() : 0;

// Site scope. `currentSite` is a view helper, so it is called, not probed.
$site = null;
try {
    $site = $this->currentSite();
} catch (\Throwable $e) {
    $site = null;
}
if (!$site && $this->vars()->offsetExists('site')) {
    $site = $this->vars()->offsetGet('site');
}
$siteSlug = '';
try {
    if ($site && method_exists($site, 'slug')) {
        $siteSlug = (string) $site->slug();
    }
} catch (\Throwable $e) {
    $siteSlug = '';
}

// Prefer the site-aware endpoint so the site's theme (and any list override)
// applies when the list is rendered server-side.
$endpointUrl = '';
try {
    if ($siteSlug !== '') {
        $endpointUrl = (string) $this->url('similaritems-recommend-site', ['site-slug' => $siteSlug]);
    }
} catch (\Throwable $e) {
    $endpointUrl = '';
}
if ($endpointUrl === '') {
    try {
        $endpointUrl = (string) $this->url('similaritems-recommend');
    } catch (\Throwable $e) {
        $endpointUrl = '/similar-items/recommend';
    }
}

// Number of results: theme setting when the theme provides one, otherwise the
// module setting.
$moduleLimit = 0;
try {
    $moduleLimit = (int) ($this->themeSetting('similar_items_max') ?: 0);
} catch (\Throwable $e) {
    $moduleLimit = 0;
}
if ($moduleLimit <= 0) {
    try {
        $moduleLimit = (int) ($this->setting('similaritems.limit') ?? 6);
    } catch (\Throwable $e) {
        $moduleLimit = 6;
    }
}
if ($moduleLimit <= 0) {
    $moduleLimit = 6;
}

$lang = '';
try {
    $lang = (string) $this->lang();
} catch (\Throwable $e) {
    $lang = '';
}
$isJa = (substr($lang, 0, 2) === 'ja');

// Wording: the theme's settings for the sidebar block when it has them, so the
// three blocks speak with one voice.
$themeText = function (string $key): string {
    try {
        return trim((string) ($this->themeSetting($key) ?: ''));
    } catch (\Throwable $e) {
        return '';
    }
};
$heading = $themeText($isJa ? 'similar_items_title_ja' : 'similar_items_title_en');
if ($heading === '') {
    $heading = $isJa ? '類似アイテム' : 'Similar items';
}
$bubbleText = $themeText($isJa ? 'similar_items_bubble_ja' : 'similar_items_bubble_en');
if ($bubbleText === '') {
    $bubbleText = $isJa ? 'こちらもどうぞ' : 'You may also like';
}
$loadingText = $isJa ? '検索しています…' : 'Searching…';
$emptyText = $isJa ? '該当するおすすめはありません。' : 'No recommendations found.';

$this->headScript()->appendFile(
    $this->assetUrl('js/similar-items-blocks.js', 'SimilarItems'),
    'text/javascript',
    ['defer' => 'defer']
);
