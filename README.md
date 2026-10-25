# omnidocs/markdown

A folder of Markdown files as an [omnidocs](https://github.com/glitchr-studio/omnidocs) manual, each
page rendered with league/commonmark as symfony.com/doc writes its pages - in a syntax GitHub reads too:

```md
> [!NOTE]
> Requires PHP 8.2.

> [!VERSIONADDED] 1.4
> An arrow shows a tall section's end.

```js caption="app.js"
StickyStops.attach(window, { keys: true });
```

```yaml tab="YAML"
flow: { keys: true }
```
```php tab="PHP"
$flow = ['keys' => true];
```

### Sideways (1.5)
```

- the folders are sections, the files pages, ordered by their front matter (`order`, `weight`,
  `sidebar_position`) or a number before their name (`10-install.md`), or by the `order` option;
- a page's title is its front matter's `title`, else its first first-level heading (taken out of the
  prose: the page prints it), else its name;
- the home is index.md or README.md (or the `home` option: the repository's README); `pages` adds
  other files (a CHANGELOG);
- links between pages go to their pages, links to other files of the repository and pictures to the
  linker's addresses (the commit read, on GitHub);
- callouts, code frames and tabs follow omnidocs' conventions; a heading ending with a version the
  manual knows (`versions`, e.g. `Changelog::read('CHANGELOG.md')->knownVersions()`) shows a badge;
- raw HTML is escaped - but for the tags `inline_html` allows, written bare (`<kbd>`).

```php
use Omnidocs\Markdown\{Changelog, MarkdownSourceFactory};

$source = (new MarkdownSourceFactory())->create([
    'root' => '/path/to/flowjs',
    'home' => 'README.md',
    'pages' => ['changelog' => 'CHANGELOG.md'],
    'order' => ['navigation', 'sticky', 'sortable', 'ajaxer', 'cookie'],
    'inline_html' => ['kbd'],
    'versions' => Changelog::read('/path/to/flowjs/CHANGELOG.md')->knownVersions(),
]);
```

`MarkdownRenderer` alone renders any Markdown text (a guide, a piece of news) to `Rendered`: HTML,
title, headings, front matter, plain text. `Changelog` reads a changelog's releases (version, date,
anchor). Options: [docs/index.md](docs/index.md).

## Verified, and not

- Verified (omnidocs' harness, PHP 8.4): omnidocs' conformance suite on flowjs' docs/, README and
  CHANGELOG (copied at the commit in Tests/Fixtures/flowjs/COMMIT) and on a folder written for the
  test (sections, numbers, front matter, every kind of link); the renderer's callouts, frames, tabs,
  badges, allowed tags; `BareTest`.
- Verified in use: the Omniscient site renders its guides, news and 153 package manuals with it - its
  static export the same as with its former renderer, byte for byte but the build's own version; the
  flowjs site its manual and changelog.
- Not verified: Markdown written for other generators' extensions (MkDocs admonitions `!!! note`,
  Docusaurus' `:::tip`) - shown as written.

Licence: MIT.
