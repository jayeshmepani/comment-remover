<?php

$SUPPORTED_EXTS = [
    'php', 'blade.php', 'js', 'jsx', 'ts', 'tsx', 'css', 'scss', 'py', 'html', 'htm',
    'jinja', 'jinja2', 'j2', 'twig'
];

$DEFAULT_EXCLUDES = [
    '.git/**', 'node_modules/**', 'vendor/**', 'dist/**', 'build/**', 'public/build/**',
    'coverage/**', '.next/**', '.nuxt/**', '.cache/**', 'storage/**', 'bootstrap/cache/**',
    'resources/js/actions/**', 'venv/**', '.venv/**', '*.min.js', '**/*.min.js', '*.min.css', '**/*.min.css',
    '.idea/**', '.vscode/**'
];

function ensure_trailing_newline($s) {
    return str_ends_with($s, "\n") ? $s : ($s . "\n");
}

function clean_whitespace($content, $collapse_limit) {
    if (str_ends_with($content, "\n") || str_ends_with($content, "\r")) {
        $content = rtrim($content, "\r\n");
    }

    $lines = preg_split('/\R/', $content);
    $newlines = [];
    $blank_count = 0;

    foreach ($lines as $ln) {
        $r_ln = rtrim($ln);
        if ($r_ln === '') {
            $blank_count++;
            if ($collapse_limit === null || $blank_count <= ($collapse_limit + 1)) {
                $newlines[] = $r_ln;
            }
        } else {
            $blank_count = 0;
            $newlines[] = $r_ln;
        }
    }

    return ensure_trailing_newline(implode("\n", $newlines));
}

function rel_posix($target_path, $base_path) {
    $base_real = realpath($base_path);
    $path_real = realpath($target_path);

    if ($base_real !== false && $path_real !== false) {
        $base = rtrim(str_replace('\\', '/', $base_real), '/');
        $path = str_replace('\\', '/', $path_real);
        if (str_starts_with($path, $base)) {
            return ltrim(substr($path, strlen($base)), '/');
        }
    }

    return ltrim(str_replace('\\', '/', $target_path), '/');
}

function is_excluded($rel_path, $exclude_globs, $exclude_regexes) {
    foreach ($exclude_globs as $pat) {
        if (fnmatch($pat, $rel_path)) {
            return true;
        }
        if (str_starts_with($pat, '**/') && fnmatch(substr($pat, 3), $rel_path)) {
            return true;
        }
    }

    foreach ($exclude_regexes as $rx) {
        if (preg_match($rx, $rel_path)) {
            return true;
        }
    }

    return false;
}

function detect_ext($filename) {
    $lower = strtolower($filename);
    if (str_ends_with($lower, '.blade.php')) {
        return 'blade.php';
    }

    $pos = strrpos($lower, '.');
    if ($pos === false) {
        return '';
    }
    return substr($lower, $pos + 1);
}

function strip_blade_html_comments($text) {
    $text = preg_replace('/\{\{\-\-.*?\-\-\}\}/s', '', $text) ?? $text;
    $text = preg_replace('/<!--(?!\s*(?:\[if |<!\[endif\]))[\s\S]*?-->/', '', $text) ?? $text;
    return $text;
}

function strip_jinja_twig_comments($text) {
    $text = preg_replace('/\{#.*?#\}/s', '', $text) ?? $text;
    $text = preg_replace('/<!--(?!\s*(?:\[if |<!\[endif\]))[\s\S]*?-->/', '', $text) ?? $text;
    return $text;
}

function matches_keep_directive($comment_text, $keep_res) {
    foreach ($keep_res as $r) {
        if (preg_match($r, $comment_text)) {
            return true;
        }
    }
    return false;
}

function build_user_regexes($patterns, $option_name) {
    $compiled = [];
    foreach ($patterns as $rx) {
        $safe = str_replace('~', '\\~', $rx);
        $wrapped = "~{$safe}~";
        if (@preg_match($wrapped, '') === false) {
            fwrite(STDERR, "Invalid {$option_name} pattern '{$rx}'\n");
            exit(2);
        }
        $compiled[] = $wrapped;
    }
    return $compiled;
}

function strip_php_tokens_native($text, $keep_res) {
    $tokens = @token_get_all($text);
    if (!is_array($tokens)) {
        return $text;
    }

    $out = '';
    foreach ($tokens as $token) {
        if (is_array($token)) {
            if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                if ($keep_res && matches_keep_directive($token[1], $keep_res)) {
                    $out .= $token[1];
                } else {
                    $out .= preg_replace('/[^\n]/', ' ', $token[1]);
                }
                continue;
            }
            $out .= $token[1];
        } else {
            $out .= $token;
        }
    }

    return $out;
}

function strip_python_with_python_cli($text, $keep_patterns) {
    $py_code = <<<'PYCODE'
import io, json, re, sys, tokenize
keep_patterns = json.loads(sys.argv[1]) if len(sys.argv) > 1 else []
keep_res = [re.compile(p) for p in keep_patterns]
text = sys.stdin.read()

def linewise_safe(s):
    lines = s.splitlines(keepends=True)
    out = []
    in_triple = None
    for line in lines:
        i = 0
        in_sq = False
        in_dq = False
        escaped = False
        comment_idx = None
        while i < len(line):
            ch = line[i]
            nxt3 = line[i:i+3]
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
        if any(r.search(comment) for r in keep_res):
            out.append(line)
            continue

        out.append(line[:comment_idx].rstrip() + ("\n" if line.endswith("\n") else ""))
    return "".join(out)

reader = io.StringIO(text).readline
out_tokens = []
try:
    for tok in tokenize.generate_tokens(reader):
        if tok.type == tokenize.COMMENT:
            if keep_res and any(r.search(tok.string) for r in keep_res):
                out_tokens.append(tok)
            else:
                continue
        else:
            out_tokens.append(tok)
    sys.stdout.write(tokenize.untokenize(out_tokens))
except tokenize.TokenError:
    sys.stdout.write(linewise_safe(text))
PYCODE;

    $cmd = 'python3 -c ' . escapeshellarg($py_code) . ' ' . escapeshellarg(json_encode(array_values($keep_patterns)));

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $proc = @proc_open($cmd, $descriptors, $pipes);
    if (!is_resource($proc)) {
        return null;
    }

    fwrite($pipes[0], $text);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);

    if ($exit !== 0) {
        return null;
    }
    return $stdout;
}

function strip_c_style_comments_safe($text, $keep_res, $is_jsx = false, $allow_line_comments = true, $allow_hash_comments = false, $allow_regex_literals = true) {
    $parts = [];

    if ($is_jsx) {
        $parts[] = '(\{\s*(?:\/\*[\s\S]*?\*\/|\/\/[^\n]*)\s*\})';
    } else {
        $parts[] = '()';
    }

    $parts[] = "('(?:\\\\.|[^'\\\\])*')";
    $parts[] = '("(?:\\\\.|[^"\\\\])*")';
    $parts[] = '(`(?:\\\\.|[^`\\\\])*`)';
    $parts[] = '(url\([^)]+\))'; // Protected unquoted CSS urls
    if ($allow_regex_literals) {
        $parts[] = '((?:(?<=[=(,;:!&|?~^])|(?<=return)|(?<=typeof)|(?<=:))\s*\/(?![\/\*])(?:\\\\.|[^\/\\\\\n])+\/[gimsuy]*)';
    } else {
        $parts[] = '()';
    }
    $parts[] = '(\/\*[\s\S]*?\*\/)';
    $parts[] = $allow_line_comments ? '(\/\/.*$)' : '()';
    $parts[] = $allow_hash_comments ? '(\#.*$)' : '()';

    $regex = '/' . implode('|', $parts) . '/m';
    $processed = @preg_replace_callback($regex, function ($matches) use ($keep_res) {
        $idx = 0;
        $val = '';
        for ($i = 1; $i <= 8; $i++) {
            if (array_key_exists($i, $matches) && $matches[$i] !== '') {
                $idx = $i;
                $val = $matches[$i];
                break;
            }
        }

        if ($idx === 0) {
            return $matches[0];
        }

        $is_comment = in_array($idx, [7, 8, 9], true) || ($idx === 1 && trim($val) !== '');
        if (!$is_comment) {
            return $val;
        }

        $inner = $val;
        if ($idx === 1) {
            $inner = preg_replace('/^\{\s*|\s*\}$/', '', $val) ?? $val;
        }

        if ($keep_res && matches_keep_directive($inner, $keep_res)) {
            return $val;
        }

        return preg_replace('/[^\n]/', ' ', $val);
    }, $text);

    return $processed !== null ? $processed : $text;
}

function strip_html_blade_content($text, $ext, $keep_res, $keep_patterns) {
    global $SUPPORTED_EXTS;

    $parts = [];
    $parts[] = '(<script\b[^>]*>)(.*?)(<\/script\s*>)';
    $parts[] = '(<style\b[^>]*>)(.*?)(<\/style\s*>)';
    if ($ext === 'blade.php') {
        $parts[] = '(@verbatim)(.*?)(@endverbatim)';
        $parts[] = '(@php)(.*?)(@endphp)';
        $parts[] = '(\{\{\-\-.*?\-\-\}\})';
    } else {
        $parts[] = '()()()';
        $parts[] = '()()()';
        $parts[] = '()';
    }
    $parts[] = '(<!--(?!\s*(?:\[if |<!\[endif\]))[\s\S]*?-->)';

    $regex = '/' . implode('|', $parts) . '/is';

    $text = preg_replace_callback($regex, function ($m) use ($keep_res, $keep_patterns) {
        $idx = 0;
        for ($i = 1; $i <= 14; $i++) {
            if (array_key_exists($i, $m) && $m[$i] !== '') {
                $idx = $i;
                break;
            }
        }

        if ($idx >= 1 && $idx <= 3 && isset($m[1], $m[2], $m[3])) {
            $inner = strip_c_style_comments_safe($m[2], $keep_res, false, true, false, true);
            return $m[1] . $inner . $m[3];
        } elseif ($idx >= 4 && $idx <= 6 && isset($m[4], $m[5], $m[6])) {
            $inner = strip_c_style_comments_safe($m[5], $keep_res, false, false, false, false);
            return $m[4] . $inner . $m[6];
        } elseif ($idx >= 7 && $idx <= 9 && isset($m[7], $m[8], $m[9])) {
            return $m[0]; // verbatim
        } elseif ($idx >= 10 && $idx <= 12 && isset($m[10], $m[11], $m[12])) {
            $inner = strip_php_tokens_native($m[11], $keep_res);
            return $m[10] . $inner . $m[12];
        } elseif ($idx === 13 && isset($m[13])) {
            return preg_replace('/[^\n]/', ' ', $m[13]);
        } elseif ($idx === 14 && isset($m[14])) {
            return preg_replace('/[^\n]/', ' ', $m[14]);
        }

        return $m[0];
    }, $text) ?? $text;

    return $text;
}

function process_file($path, $collapse_limit, $dry_run, $no_whitespace, $keep_patterns, $keep_res) {
    global $SUPPORTED_EXTS;

    $ext = detect_ext(basename($path));
    if (!in_array($ext, $SUPPORTED_EXTS, true)) {
        return false;
    }

    $original = @file_get_contents($path);
    if ($original === false) {
        return false;
    }
    $text = $original;

    if ($ext === 'php') {
        $text = strip_php_tokens_native($text, $keep_res);
    } elseif ($ext === 'py') {
        $py_out = strip_python_with_python_cli($text, $keep_patterns);
        if ($py_out !== null) {
            $text = $py_out;
        }
    } elseif (in_array($ext, ['js', 'jsx', 'ts', 'tsx', 'scss', 'css'], true)) {
        $text = strip_c_style_comments_safe(
            $text,
            $keep_res,
            in_array($ext, ['jsx', 'tsx'], true),
            $ext !== 'css',
            false,
            $ext !== 'css'
        );
    } elseif (in_array($ext, ['html', 'htm', 'blade.php', 'jinja', 'jinja2', 'j2', 'twig'], true)) {
        $text = strip_html_blade_content($text, $ext, $keep_res, $keep_patterns);
        if (in_array($ext, ['jinja', 'jinja2', 'j2', 'twig'], true)) {
            $text = strip_jinja_twig_comments($text);
        }
    }

    if (!$no_whitespace) {
        $text = clean_whitespace($text, $collapse_limit);
    } else {
        $text = ensure_trailing_newline($text);
    }

    if ($text !== $original) {
        if ($dry_run) {
            echo "[DRY] Would update: {$path}\n";
        } else {
            file_put_contents($path, $text);
            echo "Updated: {$path}\n";
        }
        return true;
    }
    return false;
}

$args = [
    'target' => null,
    'dry_run' => false,
    'collapse_blank_lines' => 2,
    'no_whitespace' => false,
    'keep_directive' => [],
    'exclude' => [],
    'exclude_regex' => []
];

$cli_args = array_slice($argv, 1);
for ($i = 0; $i < count($cli_args); $i++) {
    $arg = $cli_args[$i];

    if ($arg === '--dry-run') {
        $args['dry_run'] = true;
    } elseif ($arg === '--no-whitespace') {
        $args['no_whitespace'] = true;
    } elseif (str_starts_with($arg, '--collapse-blank-lines=')) {
        $args['collapse_blank_lines'] = (int)explode('=', $arg, 2)[1];
    } elseif ($arg === '--collapse-blank-lines') {
        $args['collapse_blank_lines'] = (int)$cli_args[++$i];
    } elseif (str_starts_with($arg, '--keep-directive=')) {
        $args['keep_directive'][] = explode('=', $arg, 2)[1];
    } elseif ($arg === '--keep-directive') {
        $args['keep_directive'][] = $cli_args[++$i];
    } elseif (str_starts_with($arg, '--exclude=')) {
        $args['exclude'][] = explode('=', $arg, 2)[1];
    } elseif ($arg === '--exclude') {
        $args['exclude'][] = $cli_args[++$i];
    } elseif (str_starts_with($arg, '--exclude-regex=')) {
        $args['exclude_regex'][] = explode('=', $arg, 2)[1];
    } elseif ($arg === '--exclude-regex') {
        $args['exclude_regex'][] = $cli_args[++$i];
    } elseif (!str_starts_with($arg, '-') && $args['target'] === null) {
        $args['target'] = $arg;
    }
}

if ($args['target'] === null) {
    echo "Usage: php universal_stripper.php <target> [--dry-run] [--no-whitespace] [--collapse-blank-lines 2] [--keep-directive regex] [--exclude glob] [--exclude-regex regex]\n";
    exit(2);
}

$target_path = realpath($args['target']);
if ($target_path === false) {
    echo "Invalid target: {$args['target']}\n";
    exit(2);
}

$collapse_limit = $args['collapse_blank_lines'] < 0 ? null : $args['collapse_blank_lines'];
$exclude_globs = array_merge($DEFAULT_EXCLUDES, $args['exclude']);
$exclude_regexes = build_user_regexes($args['exclude_regex'], '--exclude-regex');
$keep_res = build_user_regexes($args['keep_directive'], '--keep-directive');
$keep_patterns = $args['keep_directive'];

$changed = 0;

if (is_file($target_path)) {
    $base = dirname($target_path);
    $rel = rel_posix($target_path, $base);
    if (!is_excluded($rel, $exclude_globs, $exclude_regexes)) {
        if (process_file($target_path, $collapse_limit, $args['dry_run'], $args['no_whitespace'], $keep_patterns, $keep_res)) {
            $changed++;
        }
    }
} else {
    $dir_iterator = new RecursiveDirectoryIterator($target_path, RecursiveDirectoryIterator::SKIP_DOTS);
    $iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::LEAVES_ONLY);

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $path = $file->getPathname();
        $ext = detect_ext(basename($path));
        if (!in_array($ext, $SUPPORTED_EXTS, true)) {
            continue;
        }

        $rel_path = rel_posix($path, $target_path);
        if (is_excluded($rel_path, $exclude_globs, $exclude_regexes)) {
            continue;
        }

        if (process_file($path, $collapse_limit, $args['dry_run'], $args['no_whitespace'], $keep_patterns, $keep_res)) {
            $changed++;
        }
    }
}

echo "Done. Files changed: {$changed}\n";
