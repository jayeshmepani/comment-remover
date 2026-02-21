# Edge Case Test Results

**Parity: ✅ 100%** — `diff -r` returned zero differences across all 10 files.

## Issues Found (ordered by severity)

### 🔴 HIGH

#### 1. SCSS: Unquoted `url(http://...)` corrupted by `//` line-comment stripping

**Both scripts.** SCSS supports `//` line comments, so the regex `(\/\/.*$)` is active. But it also matches `//` inside `url()`.

```
INPUT:  background: url(https://example.com/img3.png);  // UNQUOTED - tricky!
OUTPUT: background: url(https:                                              
```

The `//example.com/img3.png);  // UNQUOTED - tricky!` is treated as a line comment.

Quoted URLs `url("...")` and `url('...')` are safe (captured by string groups first). Only unquoted `url()` is affected.

---

#### 2. Python: Verbose regex (`re.VERBOSE`) internal comments stripped

**Both scripts.** The `tokenize` module treats `#` inside `re.VERBOSE` regex strings as code comments and strips them, because they *are* `COMMENT` tokens to `tokenize`.

```python
# INPUT:
pattern2 = re.compile(r"""
    ^         # start
    \d+       # digits
    $         # end
""", re.VERBOSE)

# OUTPUT:
pattern2 = re.compile(
    r"""
    ^         # start
    \d+       # digits
    $         # end
""",
    re.VERBOSE,
)
```

Wait — the comments are actually preserved here because they're inside a triple-quoted string (a raw string argument), not standalone comment tokens. `tokenize` sees the `# start` etc. as part of the string literal `r"""..."""`, NOT as comments. **Verified: This is actually working correctly.** ✅

---

### 🟡 MEDIUM

#### 3. Blade: `{{-- comment --}}` → `{}` residue left behind

**Both scripts.** After stripping `{{-- ... --}}` with the regex `\{--.*?--\}`, the outer `{ }` braces remain since the regex pattern is `\{--` not `\{\{--`.

```
INPUT:  {{-- Blade comment --}}
OUTPUT: {}
```

Wait — the regex is `\{\-\-.*?\-\-\}` (DOTALL). Blade syntax is `{{-- --}}`. The regex matches `{--...--}` (one brace each side), so from `{{-- comment --}}` it matches `{-- comment --}`, leaving one `{` and one `}` → `{}`.

**Root cause:** The regex should be `\{\{--.*?--\}\}` to match the full Blade double-brace syntax.

---

#### 4. Blade: `@php` block comments not stripped

**Both scripts.** Inside `@php ... @endphp`, PHP comments (`//`, `#`, `/* */`) are NOT stripped. The script treats `.blade.php` as HTML-like and only runs `strip_embedded_blocks` (for `<script>` / `<style>`) — it doesn't know about `@php` blocks.

```
INPUT:
@php
    $x = 1; // PHP comment in blade
    $y = 2; # hash comment
    /* block comment */
@endphp

OUTPUT: (identical — comments NOT stripped)
```

---

#### 5. Blade: `@verbatim` content has `{{--` stripped

**Both scripts.** `@verbatim` blocks should preserve literal Blade syntax, but the comment regex runs on the whole file first (before considering `@verbatim`), so `{{-- --}}` inside `@verbatim` is still stripped.

```
INPUT:
@verbatim
    {{-- This should stay as-is --}}
@endverbatim

OUTPUT:
@verbatim
    {}
@endverbatim
```

---

#### 6. HTML: `<!-- -->` inside template literal in `<script>` is stripped

**Both scripts.** Inside a `<script>` block, the `strip_c_style_comments_safe` regex doesn't know about HTML comment syntax — BUT the template literal content `<!-- not a real HTML comment -->` is inside backticks, so Group 4 captures it. **Actually this is working correctly** ✅ — the template literal is preserved and its content is untouched. Let me re-check the output...

The output shows:
```html
<script>
const tpl = `
    <div>
        
        <p>${"hello"}</p>
    </div>
`;
</script>
```

The `<!-- not a real HTML comment in template literal -->` was stripped! But it's inside a template literal... Let me trace: the `strip_embedded_blocks` runs `strip_c_style_comments_safe` on the inner content of `<script>`. The template literal regex is `` `(?:\\.|[^`\\])*` `` — this is a single-line match. But the template literal spans multiple lines. The regex can't match the whole multi-line template literal because `[^`\\]` doesn't include newlines by default.

**Root cause:** Multi-line template literals are NOT captured by Group 4 (backtick string group). The regex only matches single-line backtick strings. So the `<!-- -->` inside the template literal is visible to the HTML comment pass.

Wait — actually the `<script>` block is processed by `strip_c_style_comments_safe` which doesn't strip `<!-- -->`. The HTML comment stripping happens in `strip_blade_html_comments` / `strip_jinja_twig_comments` which runs on the WHOLE file first (line 333-336 in Python). So the `<!-- -->` inside the template literal in a `<script>` block gets stripped by the HTML comment regex because it's applied to the entire file before any script-block-aware processing.

**This IS a bug.** The HTML comment regex `<!--.*?-->` runs on the entire file indiscriminately, including inside `<script>` blocks and strings.

Actually wait — this file is `test.html`, not `test.blade.php`. For `.html`, the process_file runs:
1. `strip_blade_html_comments(text)` (line 333-334) — strips ALL `<!-- -->` from the whole file
2. `strip_embedded_blocks(text, keep_res)` (line 359-360) — strips JS/CSS comments in `<script>`/`<style>`

Step 1 strips `<!-- -->` from inside the template literal because it doesn't know about JavaScript strings. **This is a real issue.**

---

#### 7. HTML: IE conditional comments stripped

**Both scripts.** `<!--[if IE]>...<![endif]-->` is stripped by the `<!--.*?-->` regex.

```
INPUT:  <!--[if IE]><p>IE only</p><![endif]-->
OUTPUT: (removed entirely)
```

These are functional comments — they control rendering in IE. Stripping them changes behavior.

---

### 🟢 LOW

#### 8. CSS: `@media` comment between rule and block removed but leaves space

**Both scripts.** Works correctly — the `/* tablet */` comment after `@media screen and (min-width: 768px)` is replaced with spaces, leaving `@media screen and (min-width: 768px)              {`. Cosmetic only.

---

#### 9. Python: `tokenize.untokenize` reformats some code

**Both scripts.** The `tokenize` → `untokenize` round-trip can subtly reformat code (e.g., adding spaces around operators, changing quoting). This is inherent to Python's tokenizer.

Example: `s7 = "escaped \" quote"` → becomes `s7 = 'escaped " quote'` (switched from double to single quotes).
Also: `s4 = '''triple single with # hash'''` → becomes `s4 = """triple single with # hash"""`

This is semantically correct but changes the source formatting.

---

#### 10. CSS/HTML/Blade/Jinja: Stripped comments leave whitespace/blank lines

**All file types, both scripts.** When comments are replaced with spaces, empty lines remain. This is by design (preserves line numbers). When run WITH `--collapse-blank-lines` (default), these get cleaned up. With `--no-whitespace`, they stay.

Not a bug — working as intended.

---

## Summary Table

| # | Issue | Severity | File Types | Both? |
|---|-------|----------|------------|-------|
| 1 | SCSS unquoted `url()` corrupted | 🔴 HIGH | `.scss` | Yes |
| 3 | Blade `{{-- --}}` → `{}` residue | 🟡 MED | `.blade.php` | Yes |
| 4 | Blade `@php` comments not stripped | 🟡 MED | `.blade.php` | Yes |
| 5 | `@verbatim` content has `{{--` stripped | 🟡 MED | `.blade.php` | Yes |
| 6 | HTML comment in JS template literal stripped | 🟡 MED | `.html`, `.blade.php`, `.jinja` | Yes |
| 7 | IE conditional comments stripped | 🟡 MED | `.html` | Yes |
| 9 | Python `tokenize` reformats code | 🟢 LOW | `.py` | Yes |