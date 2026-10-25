<?php

namespace Omnidocs\Markdown\Renderer;

use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Renderer\Block\FencedCodeRenderer as CommonMarkRenderer;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\Xml;
use Omnidocs\Html\Conventions;

/**
 * A fenced code block in its frame (omnidocs' conventions): its language
 * named, its caption above it - ```js caption="app.js"``` (or title="...").
 * With frames off, a block without a caption is written as CommonMark
 * writes it (<pre><code class="language-js">).
 *
 * @internal
 */
final class FencedCodeRenderer implements NodeRendererInterface
{
    private readonly CommonMarkRenderer $plain;

    public function __construct(private readonly Conventions $conventions, private readonly bool $frames)
    {
        $this->plain = new CommonMarkRenderer();
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable|string
    {
        FencedCode::assertInstanceOf($node);
        \assert($node instanceof FencedCode);
        [$language, $attributes] = self::info($node->getInfo() ?? '');
        $caption = $attributes['caption'] ?? $attributes['title'] ?? null;
        if (!$this->frames && null === $caption && !$node->parent() instanceof \Omnidocs\Markdown\Node\CodeTabs) {
            return $this->plain->render($node, $childRenderer);
        }

        return $this->conventions->code(Xml::escape(rtrim($node->getLiteral(), "\n")), $language, $node->parent() instanceof \Omnidocs\Markdown\Node\CodeTabs ? null : $caption);
    }

    /**
     * "js caption=\"app.js\" tab=npm" → ["js", ["caption" => "app.js", "tab" => "npm"]].
     *
     * @return array{?string, array<string, string>}
     */
    public static function info(string $info): array
    {
        $info = trim($info);
        $language = null;
        if (preg_match('/^([^\s=]+)(?:\s|$)/', $info, $m)) {
            $language = strtolower($m[1]);
            $info = substr($info, \strlen($m[0]));
        }
        $attributes = [];
        if (preg_match_all('/([a-z][\w-]*)=(?:"([^"]*)"|\'([^\']*)\'|(\S+))/i', $info, $all, \PREG_SET_ORDER)) {
            foreach ($all as $a) {
                $attributes[strtolower($a[1])] = ($a[2] ?? '') !== '' ? $a[2] : (($a[3] ?? '') !== '' ? $a[3] : ($a[4] ?? ''));
            }
        }

        return [$language, $attributes];
    }
}
