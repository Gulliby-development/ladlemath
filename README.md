<p align="center"><img src="docs/logo.svg" width="96" alt="" /></p>

# ladlemath

ladlemath is an offline command-line tool for people who cook from plain-text recipes. It reads UTF-8 recipe files, then scales quantities, converts US and metric measures, combines ingredients into a shopping list, checks recipe files, or shows how ingredient lines were parsed.

All quantity arithmetic is exact rational arithmetic; the program does not use floating point or access the network. Kitchen rounding is applied when a command finishes quantities for display. The runtime has no Composer dependency.

[![CI](https://github.com/Gulliby-development/ladlemath/actions/workflows/ci.yml/badge.svg)](https://github.com/Gulliby-development/ladlemath/actions/workflows/ci.yml) [![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## Requirements and installation

You need PHP 8.2 or newer. The launcher uses only PHP's standard runtime and the project source; it does not need Composer or the `vendor/` directory.

Get the project:

```sh
git clone https://github.com/Gulliby-development/ladlemath.git
cd ladlemath
```

Run the application from the project directory with:

```text
php bin/ladlemath.php COMMAND [OPTIONS] FILE...
```

For brevity, command transcripts below use `ladlemath` to mean `php bin/ladlemath.php`. This is documentation shorthand, not a required shell alias.

To install the development tools used by the project checks (they need PHP 8.3 or newer, because Pint does), install Composer and run:

```text
composer install
```

Composer is only for development tools. It is not needed to run ladlemath. The same PHP launcher and project files are used on Windows, macOS, and Linux; no shell-specific alias or network connection is required at runtime.

## Commands

The command is the first argument. The available forms are:

```text
usage: ladlemath scale (--serves N | --factor Q) [--promote] [--glyphs] FILE
       ladlemath convert --to metric|us [--glyphs] FILE
       ladlemath merge [--glyphs] FILE...
       ladlemath check FILE...
       ladlemath parse FILE
```

The launcher prints that usage text exactly (five lines ending in LF). With no arguments it prints usage to standard error and exits 2. `--help` or `-h` as the first argument prints usage to standard output and exits 0. Here is the launcher's help output:

```text
$ php bin/ladlemath.php --help
usage: ladlemath scale (--serves N | --factor Q) [--promote] [--glyphs] FILE
       ladlemath convert --to metric|us [--glyphs] FILE
       ladlemath merge [--glyphs] FILE...
       ladlemath check FILE...
       ladlemath parse FILE
```

`--version` or `-V` as the first argument prints `ladlemath 0.2.0` and a line feed to standard output and exits 0; the number is the `version` field of `composer.json`, read when the launcher starts. Without that field the launcher prints `ladlemath: version is not known` to standard error and exits 2.

An input `FILE` can be a path or `-` for standard input. The commands are:

- **`scale`** multiplies each quantified ingredient by a factor or scales it to a new serving count. Use exactly one of `--factor Q` and `--serves N`. A factor may be an integer, decimal, or fraction, such as `1.5`, `.25`, or `3/2`. `--promote` moves a scaled measure to a larger suitable unit before rounding. `--glyphs` uses supported fraction glyphs in output. If scaling by servings, the recipe needs a valid `Serves:` line.
- **`convert`** requires `--to metric` or `--to us`. It converts supported measures and finishes the result with kitchen rounding. Counts and quantities already in the requested system are left as written. Use `--glyphs` for fraction glyphs.
- **`merge`** takes one or more recipe files and prints a shopping list. It combines quantities with the same normalized ingredient name and the same family (volume, mass, bare count, or unquantified), while preserving separate families. Notes and recipe metadata are not included. `--glyphs` enables fraction glyphs.
- **`check`** reads one or more recipes and reports all recipe problems with their file, line, column, code, and message. It exits 1 if it finds problems and 0 if all files are valid.
- **`parse`** takes one recipe and prints a column-aligned table showing each ingredient line's quantity, unit, name, and note.

An argument beginning with `-` is treated as an option, except for the single argument `-`, which means standard input. Use `--` to end option parsing when a file name begins with a hyphen. Options that take a value accept either `--name VALUE` or `--name=VALUE`; for example, `--to metric` and `--to=metric` are equivalent. Option syntax errors are reported before command-specific validation, operand-count errors, and input errors.

Successful command output goes to standard output. Errors go to standard error as `ladlemath: MESSAGE` followed by LF. Exit codes are:

| Code | Meaning |
| --- | --- |
| `0` | Success; for `check`, no recipe problems were found. |
| `1` | `check` found one or more recipe problems. |
| `2` | An input, option, or other command error occurred. |

For example, an unreadable file, invalid UTF-8 input, invalid command option, or a recipe problem encountered by a command other than `check` is an error with exit code 2.

## Recipe files

Recipe files are UTF-8 text. A leading byte-order mark is ignored. Lines may end with LF or CRLF, and the last line does not need a line ending. Blank lines and lines whose first non-blank character is `#` are ignored. Only spaces and tabs count as blanks.

Metadata lines begin at the start of the line:

- `Title:` sets the title to the remaining text after trimming spaces and tabs. The last `Title:` line wins; an empty title means there is no title.
- `Serves:` sets the serving count. It must be a number from 1 to 9999. The first valid serving count is used; another valid `Serves:` line is a problem.

Every other non-blank, non-comment line is an ingredient line in the form `[QUANTITY [UNIT]] NAME[, NOTE]`. A quantity is optional. If present, it can be an integer, fraction (`3/4`), decimal (`.5` or `1.25`), mixed number (`1 1/2` or `1-1/2`), or supported fraction glyph. A range uses two quantities joined by a hyphen, en dash, or the word `to`, for example `2-3 tbsp butter` or `1 to 2 cups rice`. Ranges must ascend; a range may start at zero. A single quantity of zero is invalid.

Units are optional and case-insensitive. The recognized words and canonical units are:

| Canonical unit | Accepted words |
| --- | --- |
| `tsp` | `tsp`, `tsps`, `teaspoon`, `teaspoons` |
| `tbsp` | `tbsp`, `tbsps`, `tbs`, `tablespoon`, `tablespoons` |
| `cup` | `c`, `cup`, `cups` |
| `ml` | `ml`, `milliliter`, `milliliters`, `millilitre`, `millilitres` |
| `l` | `l`, `liter`, `liters`, `litre`, `litres` |
| `g` | `g`, `gram`, `grams` |
| `kg` | `kg`, `kilogram`, `kilograms` |
| `oz` | `oz`, `ounce`, `ounces` |
| `lb` | `lb`, `lbs`, `pound`, `pounds` |

After a recognized unit, an optional `of` followed by blanks is skipped, as in `2 cups of flour`. The ingredient name is the text before the first comma, trimmed at both ends; text after the comma is the optional note. A quantity without a unit is a bare count (`2 eggs`). A line without a quantity is treated as its full trimmed text as the ingredient name. Tabs are allowed between tokens. Ingredient names and notes otherwise retain their text.

The examples use these two recipe files:

`a.txt`:

```text
Title: Pancakes
Serves: 4

1 1/2 cups all-purpose flour
2 tbsp sugar
½ tsp salt
2-3 tbsp butter, melted
1 large egg
250 ml milk
salt to taste
```

`bad.txt`:

```text
0 cups flour
3 to 2 cups x
2 cups
1/0 tsp z
Serves: x
Serves: 3
Serves: 4
```

To create both files and the `ladlemath` shorthand in a POSIX shell (on Windows use Git Bash), run this in the project directory:

```sh
ladlemath() { php bin/ladlemath.php "$@"; }
printf 'Title: Pancakes\nServes: 4\n\n1 1/2 cups all-purpose flour\n2 tbsp sugar\n\302\275 tsp salt\n2-3 tbsp butter, melted\n1 large egg\n250 ml milk\nsalt to taste\n' > a.txt
printf '0 cups flour\n3 to 2 cups x\n2 cups\n1/0 tsp z\nServes: x\nServes: 3\nServes: 4\n' > bad.txt
```

Recipe diagnostics use codes `LM001` through `LM008` for invalid quantities, descending ranges, missing ingredient names, invalid or duplicate serving counts, zero quantities, lines longer than 200 code points, and control characters. Their positions count Unicode code points from 1, not UTF-8 bytes. Commands other than `check` stop at the first problem in a file and include the file name, line, column, code, and message in the error.

## Worked command examples

The following transcripts use the `a.txt` and `bad.txt` files shown above. Output and spacing are preserved. In the glyph-enabled conversion, `½` is the fraction glyph.

Scale to six servings:

```text
$ ladlemath scale --serves 6 a.txt
Title: Pancakes
Serves: 6
2 1/4 cups all-purpose flour
3 tbsp sugar
3/4 tsp salt
3 to 4 1/2 tbsp butter, melted
1 1/2 large egg
380 ml milk
salt to taste
```

Scale by a factor of three, promoting measures first:

```text
$ ladlemath scale --factor 3 --promote a.txt
Title: Pancakes
Serves: 12
4 1/2 cups all-purpose flour
3/8 cup sugar
1 1/2 tsp salt
3/8 to 5/8 cup butter, melted
3 large egg
750 ml milk
salt to taste
```

Convert US measures to metric:

```text
$ ladlemath convert --to metric a.txt
Title: Pancakes
Serves: 4
360 ml all-purpose flour
30 ml sugar
3 ml salt
30 to 45 ml butter, melted
1 large egg
250 ml milk
salt to taste
```

Convert metric measures to US and use glyphs:

```text
$ ladlemath convert --to us --glyphs a.txt
Title: Pancakes
Serves: 4
1 1/2 cups all-purpose flour
2 tbsp sugar
½ tsp salt
2-3 tbsp butter, melted
1 large egg
1 cup milk
salt to taste
```

Merge a recipe with itself:

```text
$ ladlemath merge a.txt a.txt
3 cups all-purpose flour
1/4 to 3/8 cup butter
2 large egg
500 ml milk
1 tsp salt
salt to taste
1/4 cup sugar
```

Show the parsed ingredient table:

```text
$ ladlemath parse a.txt
LINE  QTY     UNIT  NAME               NOTE
4     1 1/2   cup   all-purpose flour  -
5     2       tbsp  sugar              -
6     1/2     tsp   salt               -
7     2 to 3  tbsp  butter             melted
8     1       -     large egg          -
9     250     ml    milk               -
10    -       -     salt to taste      -
```

Check a valid and an invalid recipe together:

```text
$ ladlemath check a.txt bad.txt
bad.txt:1:1: LM006 quantity is zero
bad.txt:2:1: LM002 range is not ascending
bad.txt:3:7: LM003 missing ingredient name
bad.txt:4:1: LM001 invalid quantity
bad.txt:5:8: LM004 invalid serves value "x"
bad.txt:7:1: LM005 serves is already set
6 problems in 1 file
exit: 1
```

Check invalid UTF-8 supplied on standard input:

```text
$ printf '2 cups flour\n\377\n' | ladlemath check -
stderr: ladlemath: <stdin>: input is not valid UTF-8
exit: 2
```

The standard-input bytes for this last example are `2 cups flour`, LF, the single raw byte `FF` (hexadecimal `0xFF`), then LF. The `FF` is invalid in UTF-8; the displayed word “byte” is explanatory and is not sent as input.

## Merge examples

Merge quantities in the same family by ingredient name; keep different families separate, preserve the first spelling of the name, and drop notes:

```text
"1 cup flour" / "2 cups flour" -> "3 cups flour"
"1 cup flour" / "100 g flour" -> "1 cup flour" / "100 g flour"
"2 tbsp butter" / "1 tsp butter" / "1/4 cup butter" -> "3/8 cup butter"
"Salt" / "salt" / "1 tsp salt" -> "1 tsp salt" / "Salt"
"2 eggs" / "3 eggs" / "1 egg" -> "1 egg" / "5 eggs"
```

## Development checks

After installing development tools with `composer install`, run these checks from the project root:

```text
php vendor/bin/pint --test
php vendor/bin/phpstan analyse --no-progress
php vendor/bin/phpunit
```

To format PHP files with Pint before checking them, run:

```text
php vendor/bin/pint
```

## License

MIT, see [LICENSE](LICENSE).

---

Built with BAKED
