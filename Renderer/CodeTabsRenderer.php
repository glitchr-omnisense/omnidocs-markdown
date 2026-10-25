<?php

namespace Omnidocs\Markdown\Renderer;

use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Omnidocs\Html\Conventions;
use Omnidocs\Markdown\Node\CodeTabs;

/** @internal */
final class CodeTabsRenderer implements NodeRendererInterface
{
    public function __construct(private readonly Conventions $conventions)
    {
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        CodeTabs::assertInstanceOf($node);
        \assert($node instanceof CodeTabs);
        $panels = [];
        foreach ($node->children() as $code) {
            if ($code instanceof FencedCode) {
                $title = FencedCodeRenderer::info($code->getInfo() ?? '')[1]['tab'] ?? (string) (\count($panels) + 1);
                $panels[$title] = $childRenderer->renderNodes([$code]);
            }
        }

        return $this->conventions->tabs($panels, $node->id);
    }
}
