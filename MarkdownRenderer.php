<?php

namespace Omnidocs\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalink;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\StringContainerHelper;
use Omnidocs\Html\Conventions;
use Omnidocs\Markdown\Node\CodeTabs;
use Omnidocs\Markdown\Node\RawHtml;
use Omnidocs\Markdown\Renderer\CalloutRenderer;
use Omnidocs\Markdown\Renderer\CodeTabsRenderer;
use Omnidocs\Markdown\Renderer\FencedCodeRenderer;
use Omnidocs\Markdown\Renderer\RawHtmlRenderer;
use Omnidocs\Model\Heading as HeadingModel;
use Omnidocs\Model\Rendered;

/**
 * Markdown as a manual's page: CommonMark with GitHub's extensions (tables,
 * task lists, autolinks, strikethrough), a front matter, an id on every
 * heading, and symfony.com/doc's conventions written the way GitHub reads
 * them too:
 *
 *  - callouts: a quote whose first line is [!NOTE], [!TIP], [!IMPORTANT],
 *    [!WARNING], [!CAUTION], [!SEEALSO]; [!VERSIONADDED] 1.4 and
 *    [!DEPRECATED] 2.0 carry their version;
 *  - code in a frame that names its language (`frames`), with a caption:
 *    ```js caption="app.js"```; blocks that follow each other with a tab="..."
 *    each are shown in tabs;
 *  - a heading that ends with a version the manual knows ("Keys (1.4)",
 *    `versions`) gets a version badge in place of it - its anchor unchanged;
 *  - the first first-level heading is the page's title, printed by the page
 *    rather than in the prose.
 *
 * Raw HTML is escaped: a manual comes from a repository and is not trusted
 * to write a site's markup - but for the tags `inline_html` allows, written
 * bare (<kbd>, no attribute).
 *
 * $links rewrites each link and picture: it receives the address as written
 * and whether it is a picture, and answers the one to print, or null to keep it.
 */
final class MarkdownRenderer
{
    private readonly Conventions $conventions;

    /** @var array<string, true> */
    private readonly array $versions;

    /** @var list<string> */
    private readonly array $inlineHtml;

    /**
     * @param array<string, string> $labels     the callouts' labels (Conventions::LABELS' keys)
     * @param bool                  $frames     every code block in its frame; false: only those with a caption
     * @param list<string>          $inlineHtml the tags a page may write bare: ['kbd']
     * @param list<string>          $versions   the versions a heading may end with: ['1.4', '1.5.0']
     */
    public function __construct(
        array $labels = [],
        private readonly bool $frames = true,
        array $inlineHtml = [],
        array $versions = [],
    ) {
        $this->conventions = new Conventions($labels);
        $this->inlineHtml = array_values(array_map('strtolower', $inlineHtml));
        $this->versions = array_fill_keys($versions, true);
    }

    public function conventions(): Conventions
    {
        return $this->conventions;
    }

    /**
     * @param (callable(string $url, bool $image): ?string)|null $links
     */
    public function render(string $markdown, ?callable $links = null): Rendered
    {
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'heading_permalink' => ['symbol' => '', 'insert' => 'after', 'id_prefix' => '', 'fragment_prefix' => '', 'apply_id_to_heading' => true, 'html_class' => 'heading-anchor', 'aria_hidden' => true],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new FrontMatterExtension());
        $environment->addExtension(new HeadingPermalinkExtension());
        $environment->addRenderer(BlockQuote::class, new CalloutRenderer(), 10);
        $environment->addRenderer(FencedCode::class, new FencedCodeRenderer($this->conventions, $this->frames), 10);
        $environment->addRenderer(CodeTabs::class, new CodeTabsRenderer($this->conventions));
        $environment->addRenderer(RawHtml::class, new RawHtmlRenderer());

        $title = null;
        $headings = [];
        $text = '';
        $environment->addEventListener(DocumentParsedEvent::class, static function (DocumentParsedEvent $event) use ($links, &$title, &$text): void {
            $document = $event->getDocument();
            if (null !== $links) {
                foreach ($document->iterator() as $node) {
                    if ($node instanceof Link || $node instanceof Image) {
                        $url = $links($node->getUrl(), $node instanceof Image);
                        if (null !== $url) {
                            $node->setUrl($url);
                        }
                    }
                }
            }
            // The first first-level heading is the page's title: printed by the page, not in the prose.
            foreach ($document->children() as $child) {
                if ($child instanceof Heading && 1 === $child->getLevel()) {
                    $title = trim(StringContainerHelper::getChildText($child, [HeadingPermalink::class]));
                    $child->detach();
                    break;
                }
            }
            $text = self::plain($document);
        }, 10);

        // The callouts, the tabs, the allowed tags: on the document as parsed.
        $environment->addEventListener(DocumentParsedEvent::class, function (DocumentParsedEvent $event): void {
            $document = $event->getDocument();
            $quotes = [];
            $html = [];
            foreach ($document->iterator() as $node) {
                if ($node instanceof BlockQuote) {
                    $quotes[] = $node;
                } elseif ($node instanceof HtmlInline && [] !== $this->inlineHtml) {
                    $html[] = $node;
                }
            }
            foreach ($quotes as $quote) {
                $this->callout($quote);
            }
            foreach ($html as $node) {
                if (preg_match('#^</?([a-z][a-z0-9]*)\s*/?>$#i', $node->getLiteral(), $m) && \in_array(strtolower($m[1]), $this->inlineHtml, true)) {
                    $node->replaceWith(new RawHtml(strtolower($node->getLiteral())));
                }
            }
            $this->tabs($document);
        }, 0);

        // After the permalinks are given (their processor listens at -100): the headings with their ids,
        // then the version badges - the anchor keeps the version, as GitHub's does.
        $environment->addEventListener(DocumentParsedEvent::class, function (DocumentParsedEvent $event) use (&$headings): void {
            foreach ($event->getDocument()->iterator() as $node) {
                if (!$node instanceof Heading) {
                    continue;
                }
                $id = '';
                foreach ($node->children() as $child) {
                    if ($child instanceof HeadingPermalink) {
                        $id = $child->getSlug();
                    }
                }
                $this->badge($node);
                $headings[] = new HeadingModel($node->getLevel(), $id, trim(StringContainerHelper::getChildText($node, [HeadingPermalink::class])));
            }
        }, -200);

        $result = (new MarkdownConverter($environment))->convert($markdown);
        $meta = $result instanceof RenderedContentWithFrontMatter ? (array) $result->getFrontMatter() : [];

        /** @var array<string, mixed> $meta */
        return new Rendered($result->getContent(), $title, $headings, $meta, $text);
    }

    /**
     * `> [!NOTE]` on the quote's first line: the marker goes, the quote becomes a callout
     * (its class, its label - and its version: `> [!VERSIONADDED] 1.4`).
     */
    private function callout(BlockQuote $quote): void
    {
        $paragraph = $quote->firstChild();
        if (!$paragraph instanceof Paragraph) {
            return;
        }
        // The marker's characters may be split into several text nodes ("[", "!NOTE", "]").
        $line = '';
        $nodes = [];
        for ($node = $paragraph->firstChild(); $node instanceof Text; $node = $node->next()) {
            $line .= $node->getLiteral();
            $nodes[] = $node;
        }
        if (!preg_match('/^\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION|SEEALSO|VERSIONADDED|DEPRECATED)\](?:[ \t]+(v?\d[\w.+-]*))?[ \t]*/i', $line, $m)) {
            return;
        }
        $type = strtolower($m[1]);
        $version = isset($m[2]) && '' !== $m[2] && \in_array($type, ['versionadded', 'deprecated'], true) ? ltrim($m[2], 'v') : null;
        if (null === $version && isset($m[2]) && '' !== $m[2]) {
            $m[0] = substr($m[0], 0, (int) strpos($m[0], ']') + 1).' ';
        }
        $rest = ltrim(substr($line, \strlen($m[0])));
        $after = end($nodes)->next();
        foreach ($nodes as $i => $node) {
            if (0 === $i && '' !== $rest) {
                $node->setLiteral($rest);
            } else {
                $node->detach();
            }
        }
        if ('' === $rest && $after instanceof Newline) {
            $after->detach();
        }
        if (null === $paragraph->firstChild()) {
            $paragraph->detach();
        }
        $quote->data->set('attributes', $this->conventions->calloutAttributes($type, $version));
        $quote->data->set('omnidocs_callout', $type);
    }

    /** Fenced blocks that follow each other, each with a tab="...": one CodeTabs around them. */
    private function tabs(Document $document): void
    {
        $count = 0;
        $groups = [];
        $walk = static function (Node $parent) use (&$walk, &$groups): void {
            $run = [];
            foreach ($parent->children() as $child) {
                if ($child instanceof FencedCode && isset(FencedCodeRenderer::info($child->getInfo() ?? '')[1]['tab'])) {
                    $run[] = $child;
                    continue;
                }
                if (\count($run) > 1) {
                    $groups[] = $run;
                }
                $run = [];
                if ($child->hasChildren()) {
                    $walk($child);
                }
            }
            if (\count($run) > 1) {
                $groups[] = $run;
            }
        };
        $walk($document);
        foreach ($groups as $run) {
            $tabs = new CodeTabs('code-tabs-'.(++$count));
            $run[0]->insertBefore($tabs);
            foreach ($run as $code) {
                $tabs->appendChild($code);
            }
        }
    }

    /** "Keys (1.4)": the version, when the manual knows it, becomes a badge. */
    private function badge(Heading $heading): void
    {
        if ([] === $this->versions) {
            return;
        }
        $last = null;
        foreach ($heading->children() as $child) {
            if ($child instanceof Text) {
                $last = $child;
            } elseif (!$child instanceof HeadingPermalink) {
                $last = null;
            }
        }
        if (null === $last || !preg_match('/\s*\((\d+\.\d+(?:\.\d+)?)\)\s*$/', $last->getLiteral(), $m) || !isset($this->versions[$m[1]])) {
            return;
        }
        $last->setLiteral(substr($last->getLiteral(), 0, -\strlen($m[0])));
        $last->insertAfter(new RawHtml(' '.$this->conventions->versionBadge($m[1])));
    }

    /** The text a reader reads, for a description and a search index: the callouts' markers left out. */
    private static function plain(Node $document): string
    {
        $out = [];
        foreach ($document->iterator() as $node) {
            if ($node instanceof Text) {
                $out[] = $node->getLiteral();
            }
        }

        return (string) preg_replace('/\[!(?:NOTE|TIP|IMPORTANT|WARNING|CAUTION|SEEALSO|VERSIONADDED|DEPRECATED)\]\s*/', '', implode(' ', $out));
    }
}
