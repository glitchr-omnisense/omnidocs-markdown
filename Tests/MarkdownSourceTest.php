<?php

namespace Omnidocs\Markdown\Tests;

use Omnidocs\Markdown\MarkdownSourceFactory;
use Omnidocs\Model\Node;
use Omnidocs\SourceInterface;
use Omnidocs\Testing\RecordingLinker;
use Omnidocs\Testing\SourceContractTests;
use PHPUnit\Framework\TestCase;

/** A folder written for this test: sections, numbers, front matter, links of every kind. */
final class MarkdownSourceTest extends TestCase
{
    use SourceContractTests;

    protected function source(): SourceInterface
    {
        return (new MarkdownSourceFactory())->create(['root' => __DIR__.'/Fixtures/manual', 'docs' => '', 'repository' => 'docs', 'inline_html' => ['kbd'], 'versions' => ['1.4']]);
    }

    public function testTheFoldersAreSectionsAndTheFilesPagesInTheirOrder(): void
    {
        $source = $this->source();
        $tree = $source->tree();

        self::assertSame('Acme widget', $source->home()?->title);
        self::assertSame('docs/index.md', $source->home()?->source);
        self::assertSame(['install', 'guides'], array_map(static fn (Node $n): string => $n->path, $tree));
        self::assertSame('Installing the widget', $tree[0]->title);
        self::assertSame('docs/10-install.md', $tree[0]->source);
        self::assertTrue($tree[1]->isSection());
        self::assertSame('Guides', $tree[1]->title);
        self::assertSame([['guides/overview', 'Overview', 1], ['guides/keys', 'Keys', 2]], array_map(static fn (Node $n): array => [$n->path, $n->title, $n->order], $tree[1]->children));
    }

    public function testEveryLinkGoesToItsPageItsFileOrItsPicture(): void
    {
        $source = $this->source();
        $links = new RecordingLinker();
        $home = $source->page('', $links);
        $install = $source->page('install', $links);
        $keys = $source->page('guides/keys', $links);

        self::assertStringContainsString('<a href="/install">installing</a>', (string) $home?->html());
        self::assertStringContainsString('<img src="img:docs/img/flow.png" alt="The flow" />', (string) $home?->html());
        self::assertStringContainsString('<a href="/guides/keys#arrows">the keys</a>', (string) $install?->html());
        self::assertStringContainsString('<a href="repo:src/Widget.php">source</a>', (string) $install?->html());
        self::assertStringContainsString('<a href="https://github.com/acme/widget">GitHub</a>', (string) $install?->html());
        self::assertStringContainsString('<a href="/">the home</a>', (string) $keys?->html());
        self::assertStringContainsString('<a href="/install">install</a>', (string) $keys?->html());
        self::assertSame('Installing the widget', $install?->title);
        self::assertSame('docs/guides/keys.md', $keys?->source);
        self::assertStringContainsString('<kbd>↓</kbd>', (string) $keys?->html());
        self::assertStringContainsString('&lt;script&gt;', (string) $keys?->html());
        self::assertStringContainsString('data-version="1.4"', (string) $keys?->html());
        self::assertStringContainsString('<a href="/guides/overview">the guides</a>', (string) $home?->html());
        self::assertSame(['src/Widget.php'], $links->files);
    }
}
