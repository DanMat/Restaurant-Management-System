<?php

declare(strict_types=1);

namespace DanMat\Restaurant;

use Nimbus\Site\PageContext;
use Nimbus\Site\ViewDataContributor;

/**
 * Feeds the public homepage a handful of live "featured dishes" from the menu
 * collection, via the view-data hinge (ADR 0027). Registered by the plugin through
 * PluginContext::viewData(); the theme renders `contrib['danmat.restaurant']
 * ['featured']`, escaping every value (this returns DATA, not HTML).
 *
 * Only the home page gets data — every other page kind returns []. The payload is
 * a small, deterministic, visitor-independent list (see {@see MenuSource::featured}),
 * so it is safe to bake into the by-path page cache: it depends on no cookie,
 * session, or current user.
 */
final class HomeViewData implements ViewDataContributor
{
    public function __construct(private MenuSource $menu, private int $limit = 3)
    {
    }

    /** @return array<string,mixed> */
    public function data(PageContext $page): array
    {
        if ($page->kind !== 'home') {
            return [];
        }
        $featured = $this->menu->featured($this->limit);
        return $featured === [] ? [] : ['featured' => $featured];
    }
}
