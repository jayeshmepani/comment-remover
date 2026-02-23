# Universal Comment Stripper

A high-parity universal comment stripper available in both Python and PHP.

It handles:

- Language-specific stripping for: PHP, Blade, JS, JSX, TS, TSX, CSS, SCSS, Python, HTML, Jinja2, and Twig.
- Safe string and regex literal handling to avoid false positives.
- Native tokenization where applicable (Python `tokenize`, PHP `token_get_all`).
- 100% parity across Python and PHP versions natively, with **no external dependencies** (no `tree_sitter` required).
- Command-line interface with support for `--dry-run`, `--no-whitespace`, and `--keep-directive`.

## Usage

### Python

```bash
python universal_stripper.py <target_path> [--dry-run] [--no-whitespace]
```

### PHP

```bash
php universal_stripper.php <target_path> [--dry-run] [--no-whitespace]
```

