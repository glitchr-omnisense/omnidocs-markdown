<?php

namespace Omnidocs\Markdown\Renderer;

use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Renderer\Block\BlockQuoteRenderer;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * A quote that is a callout: its element carries the callout's class and
 * label, its first paragraph opens at once (<blockquote ...><p>); any other
 * quote as CommonMark writes it.
 *
 * @internal
 */
final class CalloutRenderer implements NodeRendererInterface
{
    private readonly BlockQuoteRenderer $quote;

    public function __construct()
    {
        $this->quote = new BlockQuoteRenderer();
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        BlockQuote::assertInstanceOf($node);
        if (!$node->data->has('omnidocs_callout')) {
            return $this->quote->render($node, $childRenderer);
        }
        /** @var array<string, string> $attributes */
        $attributes = $node->data->get('attributes');

        return new HtmlElement('blockquote', $attributes, $childRenderer->renderNodes($node->children()).$childRenderer->getInnerSeparator());
    }
}
