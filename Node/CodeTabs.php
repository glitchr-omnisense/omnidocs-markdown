<?php

namespace Omnidocs\Markdown\Node;

use League\CommonMark\Node\Block\AbstractBlock;

/** Fenced code blocks that follow each other, each with its tab="..." - shown one at a time. */
final class CodeTabs extends AbstractBlock
{
    public function __construct(public readonly string $id)
    {
        parent::__construct();
    }
}
