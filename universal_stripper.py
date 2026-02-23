#!/usr/bin/env python3
"""
universal_stripper.py
Comment stripper with behavior intentionally kept in sync with universal_stripper.php.
"""

import argparse
import fnmatch
import io
import json
import re
import subprocess
import sys
import tokenize
from pathlib import Path
from typing import Iterable, List, Optional, Pattern, Sequence

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

DEFAULT_EXCLUDES = [
    ".git/**",
    "node_modules/**",
    "vendor/**",
    "dist/**",
    "build/**",
    "public/build/**",
    "public/**",
    "coverage/**",
    ".next/**",
    ".nuxt/**",
    ".cache/**",
    "storage/**",
    "bootstrap/cache/**",
    "resources/js/actions/**",
    "venv/**",
    ".venv/**",
    "*.min.js",
    "**/*.min.js",
    "*.min.css",
    "**/*.min.css",
    ".idea/**",
    ".vscode/**",
    "tests/**",
]

BLADE_COMMENT_RE = re.compile(r"\{\{\-\-.*?\-\-\}\}", re.DOTALL)
HTML_COMMENT_RE = re.compile(r"<!--(?!\s*(?:\[if |<!\[endif\]))[\s\S]*?-->")
JINJA_COMMENT_RE = re.compile(r"\{#.*?#\}", re.DOTALL)
SCRIPT_BLOCK_RE = re.compile(
    r"(<script\b[^>]*>)(.*?)(</script\s*>)", re.IGNORECASE | re.DOTALL
)
STYLE_BLOCK_RE = re.compile(
    r"(<style\b[^>]*>)(.*?)(</style\s*>)", re.IGNORECASE | re.DOTALL
)


def ensure_trailing_newline(s: str) -> str:
    return s if s.endswith("\n") else (s + "\n")


def clean_whitespace(content: str, collapse_blank_lines: Optional[int]) -> str:
    lines = [ln.rstrip() for ln in content.splitlines()]

    if collapse_blank_lines is not None and collapse_blank_lines >= 0:
        new_lines: List[str] = []
        blank_count = 0
        for ln in lines:
            if not ln:
                blank_count += 1
                if blank_count <= (collapse_blank_lines + 1):
                    new_lines.append(ln)
            else:
                blank_count = 0
                new_lines.append(ln)
        lines = new_lines

    return ensure_trailing_newline("\n".join(lines))


def detect_ext(path: Path) -> str:
    name = path.name.lower()
    if name.endswith(".blade.php"):
        return ".blade.php"
    return path.suffix.lower()


def rel_posix(path: Path, base: Path) -> str:
    try:
        return path.relative_to(base).as_posix()
    except ValueError:
        return path.resolve().as_posix()


def is_excluded(
    rel_path: str, exclude_globs: Iterable[str], exclude_regexes: Iterable[Pattern[str]]
) -> bool:
    for pat in exclude_globs:
        if fnmatch.fnmatch(rel_path, pat):
            return True
        if pat.startswith("**/") and fnmatch.fnmatch(rel_path, pat[3:]):
            return True
    for rx in exclude_regexes:
        if rx.search(rel_path):
            return True
    return False


def strip_blade_html_comments(text: str) -> str:
    return HTML_COMMENT_RE.sub("", BLADE_COMMENT_RE.sub("", text))


def strip_jinja_twig_comments(text: str) -> str:
    return HTML_COMMENT_RE.sub("", JINJA_COMMENT_RE.sub("", text))


def strip_python_linewise_safe(text: str, keep_res: Sequence[Pattern[str]]) -> str:
    lines = text.splitlines(keepends=True)
    out: List[str] = []

    in_triple: Optional[str] = None
    for line in lines:
        i = 0
        in_sq = False
        in_dq = False
        escaped = False
        comment_idx: Optional[int] = None

        while i < len(line):
            ch = line[i]
            nxt3 = line[i : i + 3]

            if in_triple:
                if not escaped and nxt3 == in_triple:
                    in_triple = None
                    i += 3
                    continue
                escaped = (ch == "\\") and not escaped
                i += 1
                continue

            if escaped:
                escaped = False
                i += 1
                continue

            if ch == "\\" and (in_sq or in_dq):
                escaped = True
                i += 1
                continue

            if not in_sq and not in_dq and nxt3 in ("'''", '"""'):
                in_triple = nxt3
                i += 3
                continue

            if ch == "'" and not in_dq:
                in_sq = not in_sq
                i += 1
                continue
            if ch == '"' and not in_sq:
                in_dq = not in_dq
                i += 1
                continue

            if ch == "#" and not in_sq and not in_dq:
                comment_idx = i
                break
            i += 1

        if comment_idx is None:
            out.append(line)
            continue

        comment = line[comment_idx:]
        if keep_res and any(r.search(comment) for r in keep_res):
            out.append(line)
            continue

        out.append(line[:comment_idx].rstrip() + ("\n" if line.endswith("\n") else ""))

    return "".join(out)


def strip_python_tokenize(text: str, keep_res: Sequence[Pattern[str]]) -> str:
    out_tokens = []
    reader = io.StringIO(text).readline
    try:
        for tok in tokenize.generate_tokens(reader):
            if tok.type == tokenize.COMMENT:
                if keep_res and any(r.search(tok.string) for r in keep_res):
                    out_tokens.append(tok)
                else:
                    continue
            else:
                out_tokens.append(tok)
    except tokenize.TokenError:
        return strip_python_linewise_safe(text, keep_res)
    return tokenize.untokenize(out_tokens)


def strip_c_style_comments_safe(
    text: str,
    keep_res: Sequence[Pattern[str]],
    is_jsx: bool = False,
    allow_line_comments: bool = True,
    allow_hash_comments: bool = False,
) -> str:
    parts = []
    if is_jsx:
        parts.append(r"(\{\s*(?:\/\*[\s\S]*?\*\/|\/\/[^\n]*)\s*\})")
    else:
        parts.append(r"()")

    parts.append(r"(\'(?:\\.|[^\'\\])*\')")
    parts.append(r'("(?:\\.|[^"\\])*")')
    parts.append(r"(`(?:\\.|[^`\\])*`)")
    parts.append(r"(url\([^)]+\))")  # Protected unquoted CSS urls
    parts.append(
        r"((?:(?<=[=(,;:!&|?~^])|(?<=return)|(?<=typeof)|(?<=:))\s*\/(?![\/\*])(?:\\.|[^\/\\\n])+\/[gimsuy]*)"
    )
    parts.append(r"(\/\*[\s\S]*?\*\/)")
    parts.append(r"(\/\/.*$)" if allow_line_comments else r"()")
    parts.append(r"(\#.*$)" if allow_hash_comments else r"()")

    pattern = re.compile("|".join(parts), re.MULTILINE)

    def replacer(match: re.Match[str]) -> str:
        idx = 0
        val = ""
        for i in range(1, 9):
            grp = match.group(i)
            if grp is not None:
                idx = i
                val = grp
                break

        if idx == 0:
            return match.group(0)

        is_comment = idx in (7, 8, 9) or (idx == 1 and val.strip())
        if not is_comment:
            return val

        inner = val
        if idx == 1:
            inner = re.sub(r"^\{\s*|\s*\}$", "", val)
        if keep_res and any(r.search(inner) for r in keep_res):
            return val

        return re.sub(r"[^\n]", " ", val)

    return pattern.sub(replacer, text)


def strip_html_blade_content(
    text: str, ext: str, keep_res: Sequence[Pattern[str]], keep_patterns: Sequence[str]
) -> str:
    parts = []
    parts.append(r"(<script\b[^>]*>)(.*?)(</script\s*>)")
    parts.append(r"(<style\b[^>]*>)(.*?)(</style\s*>)")
    if ext == ".blade.php":
        parts.append(r"(@verbatim)(.*?)(@endverbatim)")
        parts.append(r"(@php)(.*?)(@endphp)")
        parts.append(r"(\{\{\-\-.*?\-\-\}\})")
    else:
        parts.append(r"()()()")
        parts.append(r"()()()")
        parts.append(r"()")
    parts.append(r"(<!--(?!\s*(?:\[if |<!\[endif\]))[\s\S]*?-->)")

    pattern = re.compile("|".join(parts), re.IGNORECASE | re.DOTALL)

    def replacer(match: re.Match[str]) -> str:
        # Indices and logic
        idx = 0
        for i in range(1, 15):
            if match.group(i) is not None:
                idx = i
                break

        if idx == 1:  # <script>
            o, inner, c = match.group(1), match.group(2), match.group(3)
            stripped = strip_c_style_comments_safe(
                inner,
                keep_res=keep_res,
                allow_line_comments=True,
                allow_hash_comments=False,
            )
            return f"{o}{stripped}{c}"
        elif idx == 4:  # <style>
            o, inner, c = match.group(4), match.group(5), match.group(6)
            stripped = strip_c_style_comments_safe(
                inner,
                keep_res=keep_res,
                allow_line_comments=False,
                allow_hash_comments=False,
            )
            return f"{o}{stripped}{c}"
        elif idx == 7:  # @verbatim
            return match.group(0)  # Preserve exactly
        elif idx == 10:  # @php
            o, inner, c = match.group(10), match.group(11), match.group(12)
            php_out = strip_php_tokens_via_php(inner, keep_patterns)
            if php_out is not None:
                stripped = php_out
            else:
                stripped = strip_c_style_comments_safe(
                    inner,
                    keep_res=keep_res,
                    allow_line_comments=True,
                    allow_hash_comments=True,
                )
            return f"{o}{stripped}{c}"
        elif idx == 13:  # blade comment
            return re.sub(r"[^\n]", " ", match.group(13))
        elif idx == 14:  # html comment
            return re.sub(r"[^\n]", " ", match.group(14))

        return match.group(0)

    return pattern.sub(replacer, text)


def strip_php_tokens_via_php(text: str, keep_patterns: Sequence[str]) -> Optional[str]:
    php_code = r"""$keep=json_decode($argv[1],true);if(!is_array($keep)){$keep=[];}
$raw=stream_get_contents(STDIN);$tokens=@token_get_all($raw);if(!is_array($tokens)){fwrite(STDERR,"tokenize failed\n");exit(1);}
$compiled=[];foreach($keep as $rx){$safe=str_replace("~","\\~",$rx);$pat="~{$safe}~u";if(@preg_match($pat,"")===false){fwrite(STDERR,"bad regex\n");exit(2);} $compiled[]=$pat;}
$out="";foreach($tokens as $t){if(is_array($t)){if($t[0]===T_COMMENT||$t[0]===T_DOC_COMMENT){$keepit=false;foreach($compiled as $p){if(preg_match($p,$t[1])){$keepit=true;break;}}if($keepit){$out.=$t[1];}else{$out.=preg_replace('/[^\n]/u',' ',$t[1]);}continue;}$out.=$t[1];}else{$out.=$t;}}
echo $out;"""

    cmd = ["php", "-r", php_code, json.dumps(list(keep_patterns))]
    try:
        proc = subprocess.run(
            cmd,
            input=text,
            text=True,
            capture_output=True,
            check=False,
        )
    except Exception:
        return None

    if proc.returncode != 0:
        return None
    return proc.stdout


def process_file(
    path: Path,
    collapse_blank_lines: Optional[int],
    keep_patterns: Sequence[str],
    keep_res: Sequence[Pattern[str]],
    dry_run: bool,
    no_whitespace: bool,
) -> bool:
    ext = detect_ext(path)
    if ext not in SUPPORTED_EXTS:
        return False

    original = path.read_text(encoding="utf-8", errors="surrogatepass")
    text = original

    if ext == ".py":
        text = strip_python_tokenize(text, keep_res)
    elif ext == ".php":
        php_out = strip_php_tokens_via_php(text, keep_patterns)
        if php_out is not None:
            text = php_out
        else:
            text = strip_c_style_comments_safe(
                text,
                keep_res=keep_res,
                allow_line_comments=True,
                allow_hash_comments=True,
            )
    elif ext in {".js", ".jsx", ".ts", ".tsx", ".scss", ".css"}:
        text = strip_c_style_comments_safe(
            text,
            keep_res=keep_res,
            is_jsx=(ext in {".jsx", ".tsx"}),
            allow_line_comments=(ext != ".css"),
            allow_hash_comments=False,
        )
    elif ext in {".html", ".htm", ".blade.php", ".jinja", ".jinja2", ".j2", ".twig"}:
        text = strip_html_blade_content(text, ext, keep_res, keep_patterns)
        if ext in {".jinja", ".jinja2", ".j2", ".twig"}:
            text = strip_jinja_twig_comments(text)

    if not no_whitespace:
        text = clean_whitespace(text, collapse_blank_lines)
    else:
        text = ensure_trailing_newline(text)

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
    exclude_globs: List[str],
    exclude_regexes: List[Pattern[str]],
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


def compile_regex_list(patterns: Sequence[str], option_name: str) -> List[Pattern[str]]:
    out: List[Pattern[str]] = []
    for pat in patterns:
        try:
            out.append(re.compile(pat))
        except re.error as e:
            print(f"Invalid {option_name} pattern '{pat}': {e}", file=sys.stderr)
            sys.exit(2)
    return out


def main() -> None:
    ap = argparse.ArgumentParser(description="Universal comment stripper.")
    ap.add_argument("target", help="File or directory")
    ap.add_argument("--dry-run", action="store_true", help="Do not write changes")
    ap.add_argument(
        "--collapse-blank-lines",
        type=int,
        default=2,
        help="Max allowed consecutive blank lines (default: 2). Use -1 to disable.",
    )
    ap.add_argument(
        "--no-whitespace",
        action="store_true",
        help="Do not trim trailing spaces or collapse blank lines",
    )
    ap.add_argument(
        "--keep-directive",
        action="append",
        default=[],
        help="Regex for comments to keep (repeatable)",
    )
    ap.add_argument(
        "--exclude",
        action="append",
        default=[],
        help="Glob pattern matched against relative path (repeatable)",
    )
    ap.add_argument(
        "--exclude-regex",
        action="append",
        default=[],
        help="Regex matched against relative path (repeatable)",
    )
    args = ap.parse_args()

    target = Path(args.target)
    if not target.exists():
        print(f"Invalid target: {target}", file=sys.stderr)
        sys.exit(2)

    collapse_blank_lines = (
        None if args.collapse_blank_lines < 0 else args.collapse_blank_lines
    )
    exclude_globs = DEFAULT_EXCLUDES + list(args.exclude or [])
    exclude_regexes = compile_regex_list(args.exclude_regex or [], "--exclude-regex")
    keep_res = compile_regex_list(args.keep_directive or [], "--keep-directive")
    keep_patterns = list(args.keep_directive or [])

    changed = 0
    for f in iter_files(
        target, exclude_globs=exclude_globs, exclude_regexes=exclude_regexes
    ):
        if process_file(
            f,
            collapse_blank_lines=collapse_blank_lines,
            keep_patterns=keep_patterns,
            keep_res=keep_res,
            dry_run=args.dry_run,
            no_whitespace=args.no_whitespace,
        ):
            changed += 1

    print(f"Done. Files changed: {changed}")


if __name__ == "__main__":
    main()
