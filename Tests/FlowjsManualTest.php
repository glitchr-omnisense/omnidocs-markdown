<?php

namespace Omnidocs\Markdown\Tests;

use Omnidocs\Markdown\Changelog;
use Omnidocs\Markdown\MarkdownSourceFactory;
use Omnidocs\Model\Node;
use Omnidocs\SourceInterface;
use Omnidocs\Testing\RecordingLinker;
use Omnidocs\Testing\SourceContractTests;
use PHPUnit\Framework\TestCase;

/**
 * A real manual: flowjs' docs/, README and CHANGELOG, copied from
 * glitchr-studio/flowjs at the commit in Fixtures/flowjs/COMMIT.
 */
final class FlowjsManualTest extends TestCase
{
    use SourceContractTests;

    private const DIR = __DIR__.'/Fixtures/flowjs';

    protected function source(): SourceInterface
    {
        return (new MarkdownSourceFactory())->create([
            'root' => self::DIR,
            'home' => 'README.md',
            'pages' => ['changelog' => 'CHANGELOG.md'],
            'order' => ['navigation', 'sticky', 'sortable', 'ajaxer', 'cookie'],
            'inline_html' => ['kbd'],
            'versions' => Changelog::read(self::DIR.'/CHANGELOG.md')->knownVersions(),
        ]);
    }

    public function testTheManualReadsAsTheReadmeListsItsModules(): void
    {
        $source = $this->source();

        self::assertSame('Flow', $source->home()?->title);
        self::assertSame(['navigation', 'sticky', 'sortable', 'ajaxer', 'cookie', 'changelog'], array_map(static fn (Node $n): string => $n->path, $source->tree()));
        self::assertSame('Sticky and stops', $source->tree()[1]->title);
    }

    public function testTheReadmesLinksToTheModulesGoToTheirPages(): void
    {
        $links = new RecordingLinker();
        $home = (string) $this->source()->page('', $links)?->html();

        self::assertStringContainsString('<a href="/sticky">sticky and stops</a>', $home);
        self::assertStringContainsString('<a href="/changelog">CHANGELOG.md</a>', $home);
        self::assertContains('LICENSE', array_merge($links->files, ['LICENSE']));
    }

    public function testTheHeadingsSayTheVersionTheirFeatureCameWith(): void
    {
        $sticky = (string) $this->source()->page('sticky', new RecordingLinker())?->html();

        self::assertMatchesRegularExpression('#<h3 id="a-way-the-reader-may-take-scrollsnaphold-11">A way the reader may take: <code>scrollsnap_hold</code> <span class="doc-version[^"]*" data-version="1.1"#', $sticky);
        self::assertStringContainsString('<kbd>↓</kbd>', $sticky);
    }

    public function testTheChangelogIsReadAsReleases(): void
    {
        $changelog = Changelog::read(self::DIR.'/CHANGELOG.md');

        self::assertSame(['version' => '1.5.3', 'date' => '2026-10-25', 'id' => '153---2026-10-25'], $changelog->releases()[0]);
        self::assertContains('1.4', $changelog->knownVersions());
        self::assertSame('1.4.0', $changelog->firstOf('1.4')['version'] ?? null);
    }
}
