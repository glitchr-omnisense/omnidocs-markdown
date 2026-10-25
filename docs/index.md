# omnidocs/markdown

## Options of the "markdown" source

| Option | Default | Meaning |
|---|---|---|
| `root` | (required) | the folder the paths are read from: a checkout, or a docs/ folder fetched alone |
| `docs` | `docs` | the folder of the pages under `root` (`""`: `root` itself) |
| `home` | index.md or README.md of `docs` | the home page's file under `root`; `false`: none |
| `pages` | `[]` | other files as pages, after the folder's own: `['changelog' => 'CHANGELOG.md']` |
| `order` | `[]` | the first pages and sections, by path, in this order |
| `exclude` | `[]` | files of `docs` left out (fnmatch patterns) |
| `repository` | `""` | what `root` is in the repository (`docs` for a docs/ folder fetched alone): the "edit this page" file |
| `labels` | English | the callouts' words |
| `frames` | `true` | every code block in its frame; `false`: only those with a caption or in tabs |
| `inline_html` | `[]` | the tags a page may write bare: `['kbd']` |
| `versions` | `[]` | the versions a heading may end with, for its badge |

## Errors

A folder that is not there is `Omnidocs\Exception\InvalidConfigException` when the source is built.
