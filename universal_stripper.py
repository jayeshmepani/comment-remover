#!/usr/bin/env python3
"""
universal_stripper.py
Universal comment stripper with:
- Tree-sitter parsing (best accuracy) when available
- Safe fallbacks (Python tokenize; basic CSS/HTML/PHP regex)
- Exclude support (glob + regex)
- Keep-directive support (for Python tokenize path; tree-sitter path removes all comments by default)

Install (recommended):
  pip install tree_sitter tree_sitter_languages

Usage:
  python3 universal_stripper.py ./project --dry-run
  python3 universal_stripper.py ./project --exclude "node_modules/**" --exclude "**/*.min.js"
"""

import argparse
import fnmatch
import io
import os
import re
import sys
from pathlib import Path
from typing import Iterable

# -------------------------
# Optional: tree-sitter
# -------------------------
try:
    from tree_sitter_languages import (
        get_parser,
    )  # pip install tree_sitter tree_sitter_languages
except Exception:
    get_parser = None

# -------------------------
# Python tokenize fallback
# -------------------------
import tokenize


SUPPORTED_EXTS = {
    ".php",
    ".blade.php",
    ".js",
    ".jsx",
    ".ts",
    ".tsx",
    ".css",
    ".scss",
    ".py",
    ".html",
    ".htm",
    ".jinja",
    ".jinja2",
    ".j2",
    ".twig",
}

LANG_BY_EXT = {
    ".js": "javascript",
    ".jsx": "javascript",
    ".ts": "typescript",
    ".tsx": "tsx",
    ".css": "css",
    ".scss": "scss",  # if not present, we fallback to css
    ".html": "html",
    ".htm": "html",
    ".php": "php",
    ".py": "python",
    ".jinja": "html",
    ".jinja2": "html",
    ".j2": "html",
    ".twig": "html",
}

BLADE_COMMENT_RE = re.compile(r"\{\-\-.*?\-\-\}", re.DOTALL)
HTML_COMMENT_RE = re.compile(r"<!--.*?-->", re.DOTALL)
JINJA_COMMENT_RE = re.compile(r"\{#.*?#\}", re.DOTALL)


def _ensure_trailing_newline(s: str) -> str:
    return s if s.endswith("\n") else (s + "\n")


def clean_whitespace(content: str, collapse_blank_lines: int | None) -> str:
    # 1. Remove trailing whitespace from each line
    lines = [ln.rstrip() for ln in content.splitlines()]

    # 2. Collapse blank runs if requested
    if collapse_blank_lines is not None and collapse_blank_lines >= 0:
        new_lines = []
        blank_count = 0
        for ln in lines:
            if not ln:  # effectively blank
                blank_count += 1
                if blank_count <= (collapse_blank_lines + 1):
                    new_lines.append(ln)
            else:
                blank_count = 0
                new_lines.append(ln)
        lines = new_lines

    out = "\n".join(lines)
    return _ensure_trailing_newline(out)


def strip_blade_html_comments(text: str) -> str:
    text = BLADE_COMMENT_RE.sub("", text)
    text = HTML_COMMENT_RE.sub("", text)
    return text


def strip_jinja_twig_comments(text: str) -> str:
    text = JINJA_COMMENT_RE.sub("", text)
    text = HTML_COMMENT_RE.sub("", text)
    return text


def strip_python_tokenize(text: str, keep_directives: tuple[str, ...] = ()) -> str:
    """
    Removes Python # comments using tokenize (reliable).
    Does NOT remove docstrings (intentionally).
    keep_directives: list of regex patterns; matching comments are kept.
    """
    keep_res = [re.compile(p) for p in keep_directives] if keep_directives else []

    out_tokens = []
    reader = io.StringIO(text).readline

    for tok in tokenize.generate_tokens(reader):
        if tok.type == tokenize.COMMENT:
            if keep_res and any(r.search(tok.string) for r in keep_res):
                out_tokens.append(tok)
            else:
                continue  # drop comment
        else:
            out_tokens.append(tok)

    return tokenize.untokenize(out_tokens)


def strip_with_treesitter(
    text: str,
    language: str,
    keep_line_comment_directives: tuple[str, ...] = (),
) -> str | None:
    """
    Removes comment nodes using tree-sitter if available; returns None if unavailable.
    Preserves newlines (replaces comment chars with spaces to keep line numbers stable).

    keep_line_comment_directives:
      If provided, we will keep line comments whose TEXT matches any regex.
      Note: This requires extracting the comment text; applies to both line/block comments.
    """
    if get_parser is None:
        return None

    lang_try = [language]
    if language == "scss":
        lang_try.append("css")

    parser = None
    chosen_lang = language
    for lang in lang_try:
        try:
            parser = get_parser(lang)
            chosen_lang = lang
            break
        except Exception:
            continue

    if parser is None:
        return None

    keep_res = (
        [re.compile(p) for p in keep_line_comment_directives]
        if keep_line_comment_directives
        else []
    )

    data = text.encode("utf-8", errors="surrogatepass")
    tree = parser.parse(data)
    root = tree.root_node

    spans_set: set[tuple[int, int]] = set()

    def collect(node):
        # Many grammars use: comment, line_comment, block_comment, etc.
        if "comment" in node.type:
            if keep_res:
                comment_text = data[node.start_byte : node.end_byte].decode(
                    "utf-8", errors="surrogatepass"
                )
                if any(r.search(comment_text) for r in keep_res):
                    return  # keep this comment

            # Special handling for JSX: { /* comment */ }
            # If the comment is the only thing inside a jsx_expression, remove the whole expression (including braces).
            parent = node.parent
            if parent and parent.type == "jsx_expression":
                is_empty_jsx = True
                for c in parent.children:
                    # A jsx_expression normally has '{', some content, and '}'
                    if c.type not in (
                        "{",
                        "}",
                        "comment",
                        "line_comment",
                        "block_comment",
                    ):
                        is_empty_jsx = False
                        break
                if is_empty_jsx:
                    spans_set.add((parent.start_byte, parent.end_byte))
                    return

            spans_set.add((node.start_byte, node.end_byte))
            return
        for ch in node.children:
            collect(ch)

    collect(root)

    if not spans_set:
        return text

    spans = sorted(list(spans_set), reverse=True)
    b = bytearray(data)
    for start, end in spans:
        for i in range(start, end):
            if b[i] != 10:  # \n
                b[i] = 32  # space

    return b.decode("utf-8", errors="surrogatepass")


def detect_ext(path: Path) -> str:
    name = path.name.lower()
    if name.endswith(".blade.php"):
        return ".blade.php"
    return path.suffix.lower()


def rel_posix(path: Path, base: Path) -> str:
    """Return path relative to base, normalized to forward slashes."""
    try:
        return path.relative_to(base).as_posix()
    except ValueError:
        return path.resolve().as_posix()


def is_excluded(
    rel_path: str, exclude_globs: Iterable[str], exclude_regexes: Iterable[re.Pattern]
) -> bool:
    for pat in exclude_globs:
        if fnmatch.fnmatch(rel_path, pat):
            return True
    for rx in exclude_regexes:
        if rx.search(rel_path):
            return True
    return False


def process_file(
    path: Path,
    collapse_blank_lines: int | None,
    keep_directives: tuple[str, ...],
    dry_run: bool,
    no_whitespace: bool,
) -> bool:
    ext = detect_ext(path)
    if ext not in SUPPORTED_EXTS:
        return False

    original = path.read_text(encoding="utf-8", errors="surrogatepass")
    text = original

    # Blade/HTML comments can exist inside blade/html
    if ext in {".blade.php", ".html", ".htm"}:
        text = strip_blade_html_comments(text)
    elif ext in {".jinja", ".jinja2", ".j2", ".twig"}:
        text = strip_jinja_twig_comments(text)

    # Decide language
    lang = LANG_BY_EXT.get(ext)
    if ext == ".blade.php":
        lang = "html"

    # 1. Tree-sitter pass (best for JS/TS/PHP/CSS/HTML/PY)
    ts_out = None
    if lang:
        ts_out = strip_with_treesitter(
            text, lang, keep_line_comment_directives=keep_directives
        )

    if ts_out is not None:
        text = ts_out

    # 2. Supplemental / Fallback passes
    if ext == ".py" and ts_out is None:
        text = strip_python_tokenize(text, keep_directives=keep_directives)
    elif ext in {".css", ".scss"}:
        if ts_out is None:
            text = re.sub(r"/\*.*?\*/", "", text, flags=re.DOTALL)
        if ext == ".scss":
            # Always run line-comment removal side-pass for SCSS
            text = re.sub(r"(?<!:)\/\/.*$", "", text, flags=re.MULTILINE)
    elif ext in {".html", ".htm", ".blade.php"}:
        # In HTML/Blade, we often have JS/PHP embedded.
        text = re.sub(r"(?<!:)\/\/.*$", "", text, flags=re.MULTILINE)
        text = re.sub(r"/\*.*?\*/", "", text, flags=re.DOTALL)
        if ext == ".blade.php":
            text = strip_blade_html_comments(text)
    elif ext in {".jinja", ".jinja2", ".j2", ".twig"}:
        # Jinja2/Twig: strip any embedded JS/CSS comments
        text = re.sub(r"(?<!:)\/\/.*$", "", text, flags=re.MULTILINE)
        text = re.sub(r"/\*.*?\*/", "", text, flags=re.DOTALL)
        text = strip_jinja_twig_comments(text)
    elif ext in {".php"}:
        if ts_out is None:
            text = re.sub(r"/\*.*?\*/", "", text, flags=re.DOTALL)
            text = re.sub(r"//.*?$", "", text, flags=re.MULTILINE)

    # Whitespace cleanup unless disabled
    if not no_whitespace:
        text = clean_whitespace(text, collapse_blank_lines)
    else:
        text = _ensure_trailing_newline(text)

    if text != original:
        if dry_run:
            print(f"[DRY] Would update: {path}")
        else:
            path.write_text(text, encoding="utf-8", errors="surrogatepass")
            print(f"Updated: {path}")
        return True

    return False


def iter_files(
    target: Path,
    exclude_globs: list[str],
    exclude_regexes: list[re.Pattern],
):
    base = target if target.is_dir() else target.parent

    if target.is_file():
        rp = rel_posix(target, base)
        if not is_excluded(rp, exclude_globs, exclude_regexes):
            yield target
        return

    for p in target.rglob("*"):
        if not p.is_file():
            continue

        ext = detect_ext(p)
        if ext not in SUPPORTED_EXTS:
            continue

        rp = rel_posix(p, base)
        if is_excluded(rp, exclude_globs, exclude_regexes):
            continue

        yield p


def main():
    ap = argparse.ArgumentParser(
        description="Universal comment stripper (tree-sitter first, safe fallbacks)."
    )
    ap.add_argument("target", help="File or directory")
    ap.add_argument(
        "--dry-run", action="store_true", help="Do not write changes, only report."
    )
    ap.add_argument(
        "--collapse-blank-lines",
        type=int,
        default=2,
        help="Max allowed consecutive blank lines (default: 2). Use -1 to disable collapsing.",
    )
    ap.add_argument(
        "--no-whitespace",
        action="store_true",
        help="Do not trim trailing spaces or collapse blank lines (only comment removal).",
    )
    ap.add_argument(
        "--keep-directive",
        action="append",
        default=[],
        help="Regex for comments to keep (repeatable). Example: 'noqa', 'eslint', 'istanbul', 'pylint:'",
    )
    ap.add_argument(
        "--exclude",
        action="append",
        default=[],
        help="Glob pattern (repeatable) matched against relative path. Example: 'node_modules/**', '**/*.min.js'",
    )
    ap.add_argument(
        "--exclude-regex",
        action="append",
        default=[],
        help="Regex (repeatable) matched against relative path. Example: r'(^|/)storage(/|$)'",
    )
    args = ap.parse_args()

    target = Path(args.target)
    if not target.exists():
        print(f"Invalid target: {target}", file=sys.stderr)
        sys.exit(2)

    collapse_blank_lines = (
        None if args.collapse_blank_lines < 0 else args.collapse_blank_lines
    )

    # Default excludes (common heavy/derived dirs). Remove/adjust if you want.
    default_excludes = [
        ".git/**",
        "node_modules/**",
        "vendor/**",
        "dist/**",
        "build/**",
        "public/build/**",
        "coverage/**",
        ".next/**",
        ".nuxt/**",
        ".cache/**",
        "storage/**",
        "bootstrap/cache/**",
        "resources/js/actions/**",
        "venv/**",
        ".venv/**",
        "**/*.min.js",
        "**/*.min.css",
        ".idea/**",
        ".vscode/**",
    ]

    exclude_globs = default_excludes + (args.exclude or [])
    exclude_regexes = [re.compile(x) for x in (args.exclude_regex or [])]

    changed = 0
    for f in iter_files(
        target, exclude_globs=exclude_globs, exclude_regexes=exclude_regexes
    ):
        if process_file(
            f,
            collapse_blank_lines=collapse_blank_lines,
            keep_directives=tuple(args.keep_directive),
            dry_run=args.dry_run,
            no_whitespace=args.no_whitespace,
        ):
            changed += 1

    print(f"Done. Files changed: {changed}")


if __name__ == "__main__":
    main()
