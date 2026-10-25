<?php

namespace Omnidocs\Markdown\Tests;

use Omnidocs\Markdown\MarkdownRenderer;
use PHPUnit\Framework\TestCase;

final class MarkdownRendererTest extends TestCase
{
    public function testTheTitleIsTakenOutAndTheHeadingsListedWithTheirIds(): void
    {
        $rendered = (new MarkdownRenderer())->render("---\ndescription: Hi\n---\n# Flow\n\nIntro.\n\n## Use it\n\n### `npm`\n");

        self::assertSame('Flow', $rendered->title);
        self::assertSame(['description' => 'Hi'], $rendered->meta);
        self::assertSame([[2, 'use-it', 'Use it'], [3, 'npm', 'npm']], array_map(static fn ($h) => [$h->level, $h->id, $h->text], $rendered->headings));
        self::assertStringNotContainsString('<h1', $rendered->html);
        self::assertStringContainsString('<h2 id="use-it">Use it<a href="#use-it" class="heading-anchor" aria-hidden="true" tabindex="-1" title="Permalink"></a></h2>', $rendered->html);
        self::assertSame('Intro. Use it', $rendered->text);
    }

    public function testCalloutsAreWrittenAsGitHubWritesThem(): void
    {
        $html = (new MarkdownRenderer(['note' => 'Remarque']))->render("> [!NOTE]\n> Requires PHP 8.2.\n\n> [!TIP] Hold Shift.\n\n> [!VERSIONADDED] 1.4\n> The ends.\n\n> [!DEPRECATED] v2.0 Gone.\n\n> A quote.\n")->html;

        self::assertStringContainsString("<blockquote class=\"doc-callout doc-callout-note\" data-label=\"Remarque\"><p>Requires PHP 8.2.</p>\n</blockquote>", $html);
        self::assertStringContainsString('<blockquote class="doc-callout doc-callout-tip" data-label="Tip"><p>Hold Shift.</p>', $html);
        self::assertStringContainsString('<blockquote class="doc-callout doc-callout-versionadded" data-label="New in version 1.4" data-version="1.4"><p>The ends.</p>', $html);
        self::assertStringContainsString('<blockquote class="doc-callout doc-callout-deprecated" data-label="Deprecated since version 2.0" data-version="2.0"><p>Gone.</p>', $html);
        self::assertStringContainsString("<blockquote>\n<p>A quote.</p>\n</blockquote>", $html);
    }

    public function testCodeHasItsFrameItsCaptionAndItsTabs(): void
    {
        $markdown = "```sh caption=\"Terminal\"\nnpm i\n```\n\n```yaml tab=\"YAML\"\na: 1\n```\n\n```php tab=\"PHP\"\n\$a = 1;\n```\n";
        $html = (new MarkdownRenderer())->render($markdown)->html;

        self::assertStringContainsString('<div class="doc-code" data-lang="sh"><div class="doc-code-caption">Terminal</div><pre class="notranslate"><code class="language-sh">npm i</code></pre></div>', $html);
        self::assertStringContainsString('<div class="doc-tabs" data-doc-tabs><div class="doc-tabs-list" role="tablist"><button type="button" role="tab" id="code-tabs-1-tab-0" aria-controls="code-tabs-1-panel-0" aria-selected="true">YAML</button>', $html);
        self::assertStringContainsString('data-tab="PHP"><div class="doc-code" data-lang="php"><pre class="notranslate"><code class="language-php">$a = 1;</code></pre></div></div>', $html);
    }

    public function testWithoutFramesCodeIsAsCommonMarkWritesIt(): void
    {
        self::assertSame("<pre><code class=\"language-js\">a &lt; b\n</code></pre>\n", (new MarkdownRenderer(frames: false))->render("```js\na < b\n```\n")->html);
    }

    public function testHtmlIsEscapedButTheTagsAllowed(): void
    {
        $html = (new MarkdownRenderer(inlineHtml: ['kbd']))->render('Press <kbd>↓</kbd> or <kbd class="x">x</kbd>, <script>alert(1)</script> and `<kbd>`.')->html;

        self::assertSame('<p>Press <kbd>↓</kbd> or &lt;kbd class="x"&gt;x</kbd>, &lt;script&gt;alert(1)&lt;/script&gt; and <code>&lt;kbd&gt;</code>.</p>'."\n", $html);
    }

    public function testAHeadingThatEndsWithAKnownVersionGetsItsBadge(): void
    {
        $rendered = (new MarkdownRenderer(versions: ['1.1']))->render("### A way: `hold` (1.1)\n\n### Not (9.9)\n");

        self::assertStringContainsString('<h3 id="a-way-hold-11">A way: <code>hold</code> <span class="doc-version doc-version-versionadded" data-version="1.1" title="New in version 1.1">1.1</span><a href="#a-way-hold-11"', $rendered->html);
        self::assertSame('A way: hold', $rendered->headings[0]->text);
        self::assertSame('Not (9.9)', $rendered->headings[1]->text);
    }

    public function testLinksAndPicturesGoWhereTheSiteSays(): void
    {
        $seen = [];
        $html = (new MarkdownRenderer())->render('[a](a.md) ![p](p.png) [b](https://example.org)', static function (string $url, bool $image) use (&$seen): ?string {
            $seen[] = [$url, $image];

            return str_starts_with($url, 'https:') ? null : '/x/'.$url;
        })->html;

        self::assertSame('<p><a href="/x/a.md">a</a> <img src="/x/p.png" alt="p" /> <a href="https://example.org">b</a></p>'."\n", $html);
        self::assertSame([['a.md', false], ['p.png', true], ['https://example.org', false]], $seen);
    }
}
