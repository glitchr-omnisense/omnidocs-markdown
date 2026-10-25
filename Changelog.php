<?php

namespace Omnidocs\Markdown;

use Omnidocs\Model\Rendered;

/**
 * A changelog in Markdown read as releases: each second-level heading that
 * starts with a version - "## 1.5.3 - 2026-10-25", "## [1.2.0] - 2026-10-11",
 * "## v2.0.0 (2026-01-05)" - is a release, its date when the heading gives
 * one, its anchor in the rendered page. "## Unreleased" is not a release.
 *
 *   $changelog = Changelog::read('CHANGELOG.md');
 *   $changelog->versions();          // ['1.5.3', '1.5.2', ...], newest first, as written
 *   $changelog->release('1.4.0');    // ['version' => '1.4.0', 'date' => '2026-10-17', 'id' => '140---2026-10-17']
 */
final class Changelog
{
    /** @var list<array{version: string, date: ?string, id: string}> */
    private readonly array $releases;

    public function __construct(public readonly Rendered $rendered)
    {
        $releases = [];
        foreach ($rendered->headings as $heading) {
            if (2 === $heading->level && preg_match('/^\[?v?(\d+\.\d+(?:\.\d+)?(?:-[\w.]+)?)\]?(?:\s*[-–—(]\s*(\d{4}-\d{2}-\d{2})\)?)?/u', $heading->text, $m)) {
                $releases[] = ['version' => $m[1], 'date' => $m[2] ?? null, 'id' => $heading->id];
            }
        }
        $this->releases = $releases;
    }

    /** @param (callable(string $url, bool $image): ?string)|null $links */
    public static function read(string $file, ?MarkdownRenderer $renderer = null, ?callable $links = null): self
    {
        return new self(($renderer ?? new MarkdownRenderer())->render((string) file_get_contents($file), $links));
    }

    /** @return list<array{version: string, date: ?string, id: string}> newest first, as written */
    public function releases(): array
    {
        return $this->releases;
    }

    /** @return list<string> */
    public function versions(): array
    {
        return array_column($this->releases, 'version');
    }

    /**
     * Every version, and its minor too ("1.5.3" also gives "1.5"): what a
     * heading of the manual may say a feature came with.
     *
     * @return list<string>
     */
    public function knownVersions(): array
    {
        $known = [];
        foreach ($this->versions() as $version) {
            $known[$version] = true;
            if (preg_match('/^(\d+\.\d+)\.\d+$/', $version, $m)) {
                $known[$m[1]] = true;
            }
        }

        return array_keys($known);
    }

    /** @return array{version: string, date: ?string, id: string}|null */
    public function release(string $version): ?array
    {
        foreach ($this->releases as $release) {
            if ($release['version'] === $version) {
                return $release;
            }
        }

        return null;
    }

    /** The first release of a minor version ("1.4" → the 1.4.0 entry, or the oldest 1.4.x). */
    public function firstOf(string $minor): ?array
    {
        $found = null;
        foreach ($this->releases as $release) {
            if ($release['version'] === $minor || str_starts_with($release['version'], $minor.'.')) {
                $found = $release;
            }
        }

        return $found;
    }
}
