<?php

namespace Omnidocs\Markdown;

use Omnidocs\LinkerInterface;
use Omnidocs\Model\Node;
use Omnidocs\Model\Page;
use Omnidocs\SourceInterface;
use Omnidocs\Support\Path;

/**
 * A folder of Markdown files as a manual, as a repository keeps it:
 *
 *  - the folders are sections, the files pages, ordered by their front
 *    matter's `order` (or `weight`, `sidebar_position`), else by a number
 *    before their name ("10-getting-started.md", the number out of the
 *    address), then by name - or as `order` lists them;
 *  - a page's title is its front matter's `title`, else its first
 *    first-level heading, else its name;
 *  - the home is index.md or README.md at the folder's root (or `home`, a
 *    file anywhere in the repository: its README);
 *  - `pages` adds files from elsewhere in the repository after the folder's
 *    own (a CHANGELOG): ['changelog' => 'CHANGELOG.md'].
 *
 * Links between pages go to their pages (with or without ".md"), to any other
 * file of the repository to that file (the linker's file()), pictures to the
 * linker's image(), paths read from the folder of the file they are written in.
 *
 *   new MarkdownSource('/path/to/checkout', 'docs', home: 'README.md');
 */
final class MarkdownSource implements SourceInterface
{
    private const HOMES = ['index.md', 'README.md', 'readme.md'];

    private readonly string $root;

    private readonly string $docs;

    /** @var list<Node>|null */
    private ?array $tree = null;

    /** @var array<string, Node>|null by file */
    private ?array $byFile = null;

    /** @var array<string, string> a section's folder in the repository => its first page's path */
    private array $folders = [];

    /**
     * @param string                $root       the folder on disk the paths are read from: a checkout, or a docs/ folder fetched alone
     * @param string                $docs       the folder of the pages under $root ("docs"; "" for $root itself)
     * @param string|false|null     $home       the home page's file under $root; null: index.md or README.md of $docs; false: none
     * @param array<string, string> $pages      pages from other files under $root, after the folder's own: [path => file]
     * @param list<string>          $order      the first pages and sections in this order, by path; the rest after
     * @param list<string>          $exclude    files and folders of $docs left out (fnmatch patterns, relative to $docs)
     * @param string                $repository what $root is in the repository ("" its root; "docs" for a docs/ folder fetched alone)
     */
    public function __construct(
        string $root,
        string $docs = 'docs',
        private readonly string|false|null $home = null,
        private readonly array $pages = [],
        private readonly array $order = [],
        private readonly array $exclude = [],
        private readonly ?MarkdownRenderer $renderer = null,
        private readonly string $repository = '',
    ) {
        $this->root = rtrim($root, '/');
        $this->docs = trim($docs, '/');
    }

    public function home(): ?Node
    {
        if (false === $this->home) {
            return null;
        }
        $candidates = null !== $this->home ? [$this->home] : array_map(fn (string $name): string => $this->inDocs($name), self::HOMES);
        foreach ($candidates as $file) {
            if (is_file($this->root.'/'.$file)) {
                return new Node('', $this->titleOf($this->root.'/'.$file, 'Home'), $this->inRepository($file), 0);
            }
        }

        return null;
    }

    public function tree(): array
    {
        if (null === $this->tree) {
            $tree = is_dir($this->folder()) ? $this->scan('') : [];
            $extra = 0;
            foreach ($this->pages as $path => $file) {
                if (is_file($this->root.'/'.$file)) {
                    $tree[] = new Node((string) $path, $this->titleOf($this->root.'/'.$file, Path::humanise(basename((string) $path))), $this->inRepository($file), 1000 + (++$extra));
                }
            }
            $this->tree = $this->ordered($tree);
        }

        return $this->tree;
    }

    public function page(string $path, ?LinkerInterface $links = null): ?Page
    {
        $node = null;
        foreach ($this->nodes() as $one) {
            if ($one->path === $path) {
                $node = $one;
                break;
            }
        }
        if (null === $node || null === $node->source) {
            return null;
        }
        $file = $this->onDisk($node->source);
        $rendered = ($this->renderer ?? new MarkdownRenderer())->render((string) file_get_contents($file), null !== $links ? $this->linker($node->source, $links) : null);
        $title = $rendered->meta['title'] ?? $rendered->title ?? $node->title;

        return new Page($path, \is_scalar($title) ? (string) $title : $node->title, $rendered, $node->source);
    }

    public function symbols(): array
    {
        return [];
    }

    /** @return list<Node> the home and every page */
    private function nodes(): array
    {
        $home = $this->home();

        return [...(null !== $home ? [$home] : []), ...Node::flatten($this->tree())];
    }

    /**
     * Each link of a page read in the repository: a page, a file, a picture.
     *
     * @return callable(string, bool): ?string
     */
    private function linker(string $source, LinkerInterface $links): callable
    {
        if (null === $this->byFile) {
            $this->byFile = [];
            foreach ($this->nodes() as $node) {
                if (null !== $node->source) {
                    $this->byFile[$node->source] = $node;
                }
            }
        }
        $byFile = $this->byFile;
        $folders = $this->folders;

        return static function (string $url, bool $image) use ($source, $links, $byFile, $folders): ?string {
            // An address of its own, or an anchor of the page: as written. "/docs/x.md" is read from the
            // repository's root, as GitHub reads it.
            $rooted = str_starts_with($url, '/') && !str_starts_with($url, '//');
            if (!$rooted && Path::isAbsolute($url)) {
                return null;
            }
            [$target, $fragment] = Path::split($url);
            $file = $rooted ? Path::normalise($target) : Path::resolve($source, $target);
            if (null === $file) {
                return null; // out of the repository
            }
            if ($image) {
                return $links->image($file);
            }
            foreach ([$file, $file.'.md', rtrim($file, '/').'/index.md', rtrim($file, '/').'/README.md'] as $candidate) {
                if (isset($byFile[$candidate])) {
                    return $links->page($byFile[$candidate]->path, $fragment);
                }
            }
            // A folder of pages without a page of its own: its section, which opens on its first page.
            if (isset($folders[rtrim($file, '/')])) {
                return $links->page($folders[rtrim($file, '/')], $fragment);
            }

            return $links->file('' === $file ? '.' : $file, $fragment);
        };
    }

    /** @return list<Node> */
    private function scan(string $relative): array
    {
        $nodes = [];
        $dir = $this->folder().('' !== $relative ? '/'.$relative : '');
        foreach (scandir($dir) ?: [] as $entry) {
            if ('.' === $entry[0]) {
                continue;
            }
            $file = ('' !== $relative ? $relative.'/' : '').$entry;
            if ($this->excluded($file)) {
                continue;
            }
            $full = $dir.'/'.$entry;
            [$order, $name] = Path::order(pathinfo($entry, \PATHINFO_FILENAME));
            $slug = Path::slug($name);
            $path = ('' !== $relative ? self::slugPath($relative).'/' : '').$slug;
            if (is_dir($full)) {
                $children = $this->scan($file);
                if ([] !== $children) {
                    $nodes[] = Node::section($path, Path::humanise($slug), $children, $order);
                    $first = Node::flatten($children)[0] ?? null;
                    if (null !== $first) {
                        $this->folders[$this->inRepository($this->inDocs($file))] = $first->path;
                    }
                }
            } elseif ('md' === strtolower(pathinfo($entry, \PATHINFO_EXTENSION)) && !('' === $relative && \in_array(strtolower($entry), ['index.md', 'readme.md'], true))) {
                $nodes[] = new Node($path, $this->titleOf($full, Path::humanise($slug)), $this->inRepository($this->inDocs($file)), $this->frontOrder($full) ?? $order);
            }
        }
        usort($nodes, static fn (Node $a, Node $b): int => [$a->order, $a->path] <=> [$b->order, $b->path]);

        return $nodes;
    }

    /**
     * @param list<Node> $tree
     *
     * @return list<Node>
     */
    private function ordered(array $tree): array
    {
        if ([] === $this->order) {
            return $tree;
        }
        $rank = array_flip($this->order);
        $keyed = [];
        foreach ($tree as $i => $node) {
            $keyed[] = [$rank[$node->path] ?? \count($rank) + $i, $node];
        }
        usort($keyed, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return array_map(static fn (array $pair): Node => $pair[1], $keyed);
    }

    private function excluded(string $file): bool
    {
        foreach ($this->exclude as $pattern) {
            if (fnmatch($pattern, $file)) {
                return true;
            }
        }

        return false;
    }

    private function folder(): string
    {
        return $this->root.('' !== $this->docs ? '/'.$this->docs : '');
    }

    /** A file of the pages' folder, relative to $root. */
    private function inDocs(string $file): string
    {
        return ('' !== $this->docs ? $this->docs.'/' : '').$file;
    }

    /** A file relative to $root, as the repository names it. */
    private function inRepository(string $file): string
    {
        return (string) Path::normalise(('' !== $this->repository ? trim($this->repository, '/').'/' : '').$file);
    }

    /** A file the repository names, on disk. */
    private function onDisk(string $source): string
    {
        $prefix = '' !== $this->repository ? trim($this->repository, '/').'/' : '';

        return $this->root.'/'.('' !== $prefix && str_starts_with($source, $prefix) ? substr($source, \strlen($prefix)) : $source);
    }

    private static function slugPath(string $relative): string
    {
        return implode('/', array_map(static fn (string $part): string => Path::slug(Path::order($part)[1]), explode('/', $relative)));
    }

    private function titleOf(string $file, string $fallback): string
    {
        $head = (string) file_get_contents($file, false, null, 0, 8192);
        if (preg_match('/\A---\R(.*?)\R---/s', $head, $front) && preg_match('/^title:\s*["\']?(.+?)["\']?\s*$/m', $front[1], $m)) {
            return $m[1];
        }
        if (preg_match('/^#\s+(.+?)\s*#*\s*$/m', (string) preg_replace('/^```.*?^```/ms', '', $head), $m)) {
            return trim(strip_tags((string) preg_replace(['/`([^`]*)`/', '/\[([^\]]*)\]\([^)]*\)/', '/[*_]{1,2}([^*_]+)[*_]{1,2}/'], '$1', $m[1])));
        }

        return $fallback;
    }

    private function frontOrder(string $file): ?int
    {
        $head = (string) file_get_contents($file, false, null, 0, 2048);

        return preg_match('/\A---\R(.*?)\R---/s', $head, $front) && preg_match('/^(?:order|weight|sidebar_position):\s*(\d+)/m', $front[1], $m) ? (int) $m[1] : null;
    }
}
