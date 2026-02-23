# Universal Comment Stripper

A high-parity universal comment stripper available in both **Python** and **PHP**.

It handles file types robustly and natively without altering code integrity, correctly handling edge cases like internet-explorer conditional comments, literal strings, nested templating tags, and regular expressions.

## Features

- **Extensive Language Support**: Precisely strips comments in PHP, Blade (`.blade.php`), JavaScript, JSX, TypeScript, TSX, CSS, SCSS, Python, HTML, Jinja2, and Twig.
- **Literal Safety**: Safe string (`"`, `'`, `` ` ``) and regex literal (`/regex/`) handling to avoid false positives. It natively distinguishes `//` inside a URL (`http://`) versus an actual line comment.
- **Native Tokenization**: Uses Python's native `tokenize` for `.py` files and PHP's native `token_get_all()` for `.php` files to ensure zero false positives parsing complex syntax.
- **Embedded Block Support**: Seamlessly processes script headers, style blocks, and Blade `@php` inclusions in HTML structures (`<script>`, `<style>`). Honors exact contents of `@verbatim`.
- **100% Parity**: Python and PHP versions behave identically and output exact byte-for-byte structures.
- **Zero Dependencies**: Entirely self-contained requiring no external requirements or libraries (No `tree_sitter` required).

## Requirements

- Python script (`universal_stripper.py`): Python 3.6+
- PHP script (`universal_stripper.php`): PHP 8.0+

> **Note on Cross-Language Capabilities:**
> For 100% syntactical safety, when the Python script encounters a `.php` file, it uses `subprocess` to call `php -r` for PHP's native tokenizer. Similarly, when the PHP script encounters a `.py` file, it calls `python3 -c` for Python's native tokenizer. If the corresponding CLI is missing, it falls back to a highly reliable C-style regex-based replacement mechanism.

## Usage

Both scripts expose identical arguments.

### Python

```bash
python universal_stripper.py <target_path> [OPTIONS]
```

### PHP

```bash
php universal_stripper.php <target_path> [OPTIONS]
```

## Arguments Reference

| Argument | Description |
| --- | --- |
| `<target_path>` | **(Required)** The root file or directory to process. If a directory, the stripper recursively scans all supported extension files. |
| `--dry-run` | Prints which files *would* be updated, but makes absolutely zero modifications to the actual files. |
| `--collapse-blank-lines <int>` | **Default: 2**. The maximum number of consecutive blank lines allowed. Use `-1` to disable collapsing blank lines. (Reduces bloated whitespace typically left behind when removing huge multi-line documentation comments). |
| `--no-whitespace` | Disables trimming trailing spaces and disables collapsing blank lines entirely. Structural empty spaces where comments used to be will remain completely untouched. |
| `--keep-directive <regex>` | Specify a comment body regex to *preserve*. For example, use `--keep-directive .*eslint-disable.*` to avoid stripping linter directives. Can be used multiple times. |
| `--exclude <glob>` | Globs matched against the relative path. Use this to exclude custom folders. *Note: `node_modules`, `vendor`, `.git`, `coverage`, and `.venv` are ignored by default automatically!* |
| `--exclude-regex <regex>` | Provides exclusion mapping using actual Regular Expressions instead of simple globs for complex exclusions. |

## Examples

### 1. Basic Processing (Required `<target_path>`)

Process a single file or an entire directory safely.

**Python:**

```bash
python universal_stripper.py resources/views/
```

**PHP:**

```bash
php universal_stripper.php resources/views/
```

### 2. Dry Run (`--dry-run`)

Check which files would be modified without actually changing them.

**Python:**

```bash
python universal_stripper.py app/Models/User.php --dry-run
```

**PHP:**

```bash
php universal_stripper.php app/Models/User.php --dry-run
```

### 3. Collapse Blank Lines (`--collapse-blank-lines <int>`)
Change the maximum consecutive blank lines left behind (default is 2). Set to 0 to heavily compress vertical space.

**Python:**

```bash
python universal_stripper.py src/ --collapse-blank-lines 1
```

**PHP:**

```bash
php universal_stripper.php src/ --collapse-blank-lines 0
```

### 4. No Whitespace Cleanup (`--no-whitespace`)
Strips comments but leaves all resulting empty lines and spaces perfectly intact (useful for debugging line mapping).

**Python:**

```bash
python universal_stripper.py public/js/app.js --no-whitespace
```

**PHP:**

```bash
php universal_stripper.php public/js/app.js --no-whitespace
```

### 5. Keep Directives (`--keep-directive <regex>`)
Protect specific comments like linter directives or type annotations. Can be passed multiple times.

**Python:**

```bash
python universal_stripper.py resources/js --keep-directive ".*eslint-disable.*" --keep-directive ".*@ts-expect-error.*"
```

**PHP:**

```bash
php universal_stripper.php resources/js --keep-directive ".*eslint-disable.*" --keep-directive ".*@ts-expect-error.*"
```

### 6. Exclude Globs (`--exclude <glob>`)
Exclude specific paths using glob patterns (appended to default ignores like `vendor/`, `node_modules/`).

**Python:**

```bash
python universal_stripper.py ./ --exclude "tests/Feature/**" --exclude "*.min.js"
```

**PHP:**

```bash
php universal_stripper.php ./ --exclude "tests/Feature/**" --exclude "*.min.js"
```

### 7. Exclude Regex (`--exclude-regex <regex>`)
Exclude specific paths using complex regular expressions.

**Python:**

```bash
python universal_stripper.py ./ --exclude-regex "^tests\/Fixtures\/.*"
```

**PHP:**

```bash
php universal_stripper.php ./ --exclude-regex "^tests\/Fixtures\/.*"
```
