<?php

declare(strict_types=1);

namespace DanMat\Restaurant\Tests;

use DanMat\Restaurant\HomeViewData;
use DanMat\Restaurant\MenuSource;
use Nimbus\Site\PageContext;
use PHPUnit\Framework\TestCase;

/**
 * The homepage view-data contributor (ADR 0027): it feeds featured dishes to the
 * theme ONLY on the home page, and returns data (not HTML) that the theme escapes.
 * A fake {@see MenuSource} supplies the menu, so this needs no collection or DB.
 */
final class HomeViewDataTest extends TestCase
{
    /** @param list<array{name:string,price:string,category:?string,blurb:string}> $featured */
    private function menu(array $featured): MenuSource
    {
        return new class ($featured) implements MenuSource {
            /** @param list<array{name:string,price:string,category:?string,blurb:string}> $featured */
            public function __construct(private array $featured)
            {
            }

            public function items(): array
            {
                return [];
            }

            public function featured(int $limit = 3): array
            {
                return array_slice($this->featured, 0, $limit);
            }
        };
    }

    private function context(string $kind): PageContext
    {
        return new PageContext($kind, 'https://x.test/', 'T', 'Site', 'nonce');
    }

    public function test_it_contributes_featured_dishes_on_the_home_page(): void
    {
        $menu = $this->menu([
            ['name' => 'Grilled Salmon', 'price' => '9.99', 'category' => 'Main Course', 'blurb' => 'Seasonal greens.'],
        ]);
        $data = (new HomeViewData($menu))->data($this->context('home'));

        self::assertArrayHasKey('featured', $data);
        self::assertSame('Grilled Salmon', $data['featured'][0]['name']);
    }

    public function test_it_contributes_nothing_off_the_home_page(): void
    {
        $menu = $this->menu([
            ['name' => 'Grilled Salmon', 'price' => '9.99', 'category' => 'Main Course', 'blurb' => 'x'],
        ]);
        $contributor = new HomeViewData($menu);

        self::assertSame([], $contributor->data($this->context('collection')), 'no data on a collection page');
        self::assertSame([], $contributor->data($this->context('entry')), 'no data on an entry page');
    }

    public function test_an_empty_menu_contributes_nothing_even_on_home(): void
    {
        $data = (new HomeViewData($this->menu([])))->data($this->context('home'));
        self::assertSame([], $data, 'no featured key when there is nothing to feature');
    }

    public function test_the_limit_is_honoured(): void
    {
        $items = [];
        foreach (['A', 'B', 'C', 'D', 'E'] as $n) {
            $items[] = ['name' => $n, 'price' => '1.00', 'category' => null, 'blurb' => ''];
        }
        $data = (new HomeViewData($this->menu($items), 3))->data($this->context('home'));
        self::assertCount(3, $data['featured']);
    }
}
