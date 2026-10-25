<?php

namespace Omnidocs\Markdown\Renderer;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Omnidocs\Markdown\Node\RawHtml;

/** @internal */
final class RawHtmlRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        RawHtml::assertInstanceOf($node);
        \assert($node instanceof RawHtml);

        return $node->html;
    }
}
