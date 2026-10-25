<?php

namespace Omnidocs\Markdown;

use Omnidocs\Config;
use Omnidocs\SourceFactory;
use Omnidocs\SourceInterface;

/**
 * The "markdown" source:
 *
 *   root         the folder the paths are read from (a checkout)          required
 *   docs         the folder of the pages under root                       "docs"
 *   home         the home's file under root; false for none              index.md or README.md of docs
 *   pages        other files as pages, after the folder's: [path => file]
 *   order        the first pages and sections, by path, in their order
 *   exclude      files of docs left out (fnmatch patterns)
 *   repository   what root is in the repository ("docs" for a docs/ folder fetched alone)
 *   labels       the callouts' words (note, tip, versionadded...)
 *   frames       every code block in its frame (true)
 *   inline_html  the tags a page may write bare: [kbd]
 *   versions     the versions a heading may end with, for its badge
 */
final class MarkdownSourceFactory extends SourceFactory
{
    public function getName(): string
    {
        return 'markdown';
    }

    protected function populate(Config $config): void
    {
        $config->defaults([
            'docs' => 'docs',
            'home' => null,
            'pages' => [],
            'order' => [],
            'exclude' => [],
            'repository' => '',
            'labels' => [],
            'frames' => true,
            'inline_html' => [],
            'versions' => [],
            'omnidocs.required_options' => ['root'],
        ]);
    }

    protected function build(Config $config): SourceInterface
    {
        $home = $config->get('home');

        return new MarkdownSource(
            $config->directory('root', $this->getName()),
            (string) $config->string('docs'),
            false === $home || 'false' === $home ? false : $config->string('home'),
            $config->map('pages'),
            $config->list('order'),
            $config->list('exclude'),
            new MarkdownRenderer($config->map('labels'), $config->bool('frames', true), $config->list('inline_html'), $config->list('versions')),
            (string) $config->string('repository'),
        );
    }
}
