<?php

namespace Omnidocs\Markdown\Node;

use League\CommonMark\Node\Inline\AbstractInline;

/**
 * HTML the renderer itself writes into a page: a tag of the allowed list
 * (<kbd>), a version badge. Not a string container: a heading's text, a
 * search index's, does not read it.
 */
final class RawHtml extends AbstractInline
{
    public function __construct(public readonly string $html)
    {
        parent::__construct();
    }
}
