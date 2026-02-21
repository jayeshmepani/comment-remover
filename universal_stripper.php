<?php

/**
 * universal_stripper.php
 * Universal comment stripper (Native PHP version).
 * - Safe native tokenizer for PHP files.
 * - Robust Regex state-machine for JS/TS/CSS/HTML to safely ignore strings.
 * - Same CLI API as universal_stripper.py (exclude, exclude-regex, keep-directive).
 */

$SUPPORTED_EXTS = [
    'php', 'blade.php', 'js', 'jsx', 'ts', 'tsx', 'css', 'scss', 'py', 'html', 'htm',
    'jinja', 'jinja2', 'j2', 'twig'
];

$DEFAULT_EXCLUDES = [
    '.git/**', 'node_modules/**', 'vendor/**', 'dist/**', 'build/**', 'public/build/**',
    'coverage/**', '.next/**', '.nuxt/**', '.cache/**', 'storage/**', 'bootstrap/cache/**',
    'resources/js/actions/**', 'venv/**', '.venv/**', '**/*.min.js', '**/*.min.css',
    '.idea/**', '.vscode/**'
];

function rel_posix($target_path, $base_path) {
    // Return relative path with Unix slashes
    $base = rtrim(str_replace('\\', '/', realpath($base_path)), '/');
    $path = str_replace('\\', '/', realpath($target_path));
    
    if (str_starts_with($path, $base)) {
        $rel = substr($path, strlen($base));
        return ltrim($rel, '/');
    }
    return ltrim(str_replace('\\', '/', $target_path), '/');
}

function is_excluded($rel_path, $exclude_globs, $exclude_regexes) {
    foreach ($exclude_globs as $pat) {
        if (fnmatch($pat, $rel_path) || fnmatch("*/" . $pat, $rel_path)) {
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
    if (str_ends_with($lower, '.blade.php')) return 'blade.php';
    
    $pos = strrpos($lower, '.');
    if ($pos !== false) {
        return substr($lower, $pos + 1);
    }
    return '';
}

function ensure_trailing_newline($s) {
    return str_ends_with($s, "\n") ? $s : ($s . "\n");
}

function clean_whitespace($content, $collapse_limit) {
    if (str_ends_with($content, "\n")) {
        $content = substr($content, 0, -1);
    }
    $lines = explode("\n", $content);
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

function strip_blade_html_comments($text) {
    $result = preg_replace('/\{\-\-.*?\-\-\}/s', '', $text);
    if ($result !== null) $text = $result;
    $result = preg_replace('/<!--.*?-->/s', '', $text);
    if ($result !== null) $text = $result;
    return $text;
}

function strip_jinja_twig_comments($text) {
    $result = preg_replace('/\{#.*?#\}/s', '', $text);
    if ($result !== null) $text = $result;
    $result = preg_replace('/<!--.*?-->/s', '', $text);
    if ($result !== null) $text = $result;
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

function strip_php_tokens($text, $keep_res) {
    $tokens = @token_get_all($text);
    $out = '';
    
    foreach ($tokens as $token) {
        if (is_array($token)) {
            if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                if ($keep_res && matches_keep_directive($token[1], $keep_res)) {
                    $out .= $token[1];
                    continue;
                }
                
                // Pad with spaces like tree-sitter (replace non-newline chars with space)
                $out .= preg_replace('/[^\n]/', ' ', $token[1]);
                continue;
            }
            $out .= $token[1];
        } else {
            $out .= $token;
        }
    }
    return $out;
}

function strip_python_regex($text, $keep_res) {
    // Regex state machine for Python: Ignore strings, kill # comments (except keep_res)
    // Process line by line, tracking string state across lines for triple-quoted strings
    $lines = explode("\n", $text);
    $result = [];
    $in_triple_dq = false; // inside triple double-quote string
    $in_triple_sq = false; // inside triple single-quote string
    
    foreach ($lines as $line) {
        // If inside a triple-quoted string, look for closing
        if ($in_triple_dq) {
            $pos = strpos($line, '"""');
            if ($pos !== false) {
                $in_triple_dq = false;
                $result[] = $line; // keep the whole line, no stripping
            } else {
                $result[] = $line;
            }
            continue;
        }
        if ($in_triple_sq) {
            $pos = strpos($line, "'''");
            if ($pos !== false) {
                $in_triple_sq = false;
                $result[] = $line;
            } else {
                $result[] = $line;
            }
            continue;
        }
        
        // Check if this line starts a triple-quoted string
        if (preg_match('/"""/', $line)) {
            // Count occurrences - if odd, we're entering a triple-quote
            $count = substr_count($line, '"""');
            if ($count % 2 == 1) {
                $in_triple_dq = true;
            }
            $result[] = $line; // keep line as-is
            continue;
        }
        if (preg_match("/'''/", $line)) {
            $count = substr_count($line, "'''");
            if ($count % 2 == 1) {
                $in_triple_sq = true;
            }
            $result[] = $line;
            continue;
        }
        
        // For normal lines: find `#` that isn't inside a string
        $stripped = strip_python_line_comment($line, $keep_res);
        $result[] = $stripped;
    }
    
    return implode("\n", $result);
}

function strip_python_line_comment($line, $keep_res) {
    // Walk character by character to find a # that is outside of strings
    $len = strlen($line);
    $in_sq = false;
    $in_dq = false;
    $i = 0;
    
    while ($i < $len) {
        $ch = $line[$i];
        
        if ($ch === '\\' && ($in_sq || $in_dq)) {
            $i += 2; // skip escaped char
            continue;
        }
        
        if ($ch === '"' && !$in_sq) {
            // Check for raw strings: r"..." or r'...'
            $in_dq = !$in_dq;
            $i++;
            continue;
        }
        
        if ($ch === "'" && !$in_dq) {
            $in_sq = !$in_sq;
            $i++;
            continue;
        }
        
        if ($ch === '#' && !$in_sq && !$in_dq) {
            // Found a real comment
            $comment = substr($line, $i);
            if ($keep_res && matches_keep_directive($comment, $keep_res)) {
                return $line; // keep the whole line
            }
            return substr($line, 0, $i); // strip from # onward
        }
        
        $i++;
    }
    
    return $line; // no comment found
}

function strip_js_ts_css_regex($text, $is_jsx, $keep_res) {
    // Build regex parts
    $parts = [];

    // Group 1: JSX comment expression { /* ... */ } or { // ... }
    if ($is_jsx) {
        $parts[] = '(\{\s*(?:\/\*[\s\S]*?\*\/|\/\/[^\n]*)\s*\})';
    } else {
        $parts[] = '()'; // placeholder
    }

    // Group 2-4: Strings and template literals
    $parts[] = "('(?:\\\\.|[^'\\\\])*')";
    $parts[] = '("(?:\\\\.|[^"\\\\])*")';
    $parts[] = '(`(?:\\\\.|[^`\\\\])*`)';
    // Group 5: regex literal
    $parts[] = '((?<=[=(,;:!&|?~^]|return)\s*\/(?![\/\*])(?:\\\\.|[^\/\\\\\n])+\/[gimsuy]*)';
    // Group 6: block comment
    $parts[] = '(\/\*[\s\S]*?\*\/)';
    // Group 7: line comment
    $parts[] = '(\/\/.*$)';

    $regex = '/' . implode('|', $parts) . '/m';

    // Process in chunks to avoid PCRE JIT stack overflow on large files
    $lines = explode("\n", $text);
    $chunk_size = 200; // lines per chunk
    $result_parts = [];

    for ($i = 0; $i < count($lines); $i += $chunk_size) {
        $chunk = implode("\n", array_slice($lines, $i, $chunk_size));

        $processed = @preg_replace_callback($regex, function($matches) use ($keep_res) {
            for ($idx = 1; $idx <= 7; $idx++) {
                if (isset($matches[$idx]) && $matches[$idx] !== '') break;
            }
            if ($idx > 7) return $matches[0];

            $val = $matches[$idx];
            $trim_val = trim($val);
            $is_comment = str_starts_with($trim_val, '/*')
                       || str_starts_with($trim_val, '//')
                       || str_starts_with($trim_val, '{');

            if ($is_comment) {
                if ($keep_res) {
                    $inner = $val;
                    if (str_starts_with($trim_val, '{')) {
                        $inner = preg_replace('/^\{\s*|\s*\}$/', '', $val);
                    }
                    if (matches_keep_directive($inner, $keep_res)) {
                        return $val;
                    }
                }
                return preg_replace('/[^\n]/', ' ', $val);
            }

            return $val;
        }, $chunk);

        // CRITICAL: If regex fails (JIT overflow), keep original chunk unchanged
        if ($processed === null) {
            $result_parts[] = $chunk;
        } else {
            $result_parts[] = $processed;
        }
    }

    return implode("\n", $result_parts);
}


function process_file($path, $collapse_limit, $dry_run, $no_whitespace, $keep_res) {
    global $SUPPORTED_EXTS;

    $ext = detect_ext(basename($path));
    if (!in_array($ext, $SUPPORTED_EXTS)) return false;

    $original = @file_get_contents($path);
    if ($original === false) return false;
    $text = $original;

    // Blade/HTML comments
    if (in_array($ext, ['blade.php', 'html', 'htm'])) {
        $text = strip_blade_html_comments($text);
    } else if (in_array($ext, ['jinja', 'jinja2', 'j2', 'twig'])) {
        $text = strip_jinja_twig_comments($text);
    }

    // Main language-specific stripping
    if ($ext === 'php') {
        $text = strip_php_tokens($text, $keep_res);
    } else if (in_array($ext, ['js', 'jsx', 'ts', 'tsx', 'css'])) {
        $is_jsx = in_array($ext, ['jsx', 'tsx']);
        $text = strip_js_ts_css_regex($text, $is_jsx, $keep_res);
    } else if ($ext === 'scss') {
        // Use smart regex first for block comments
        $text = strip_js_ts_css_regex($text, false, $keep_res);
        // Then apply Python's same raw SCSS line-comment regex for exact parity
        $r = preg_replace('/(?<!:)\/\/.*$/m', '', $text); if ($r !== null) $text = $r;
    } else if ($ext === 'py') {
        $text = strip_python_regex($text, $keep_res);
    } else if (in_array($ext, ['html', 'htm'])) {
        // Match Python: tree-sitter(html) strips CSS/JS comments inside <style>/<script>
        // We replicate this with our regex state machine
        $text = strip_js_ts_css_regex($text, false, $keep_res);
    }

    // Supplemental passes for HTML/Blade (matching Python's raw regex fallback)
    if (in_array($ext, ['html', 'htm', 'blade.php'])) {
        // Python applies these raw regexes AFTER tree-sitter, blindly matching // and /* */
        $r = preg_replace('/(?<!:)\/\/.*$/m', '', $text); if ($r !== null) $text = $r;
        $r = preg_replace('/\/\*.*?\*\//s', '', $text); if ($r !== null) $text = $r;
        if ($ext === 'blade.php') {
            $text = strip_blade_html_comments($text);
        }
    } else if (in_array($ext, ['jinja', 'jinja2', 'j2', 'twig'])) {
        // Jinja2/Twig: strip any embedded JS/CSS comments
        $r = preg_replace('/(?<!:)\/\/.*$/m', '', $text); if ($r !== null) $text = $r;
        $r = preg_replace('/\/\*.*?\*\//s', '', $text); if ($r !== null) $text = $r;
        $text = strip_jinja_twig_comments($text);
    }

    if (!$no_whitespace) {
        $text = clean_whitespace($text, $collapse_limit);
    } else {
        $text = ensure_trailing_newline($text);
    }

    if ($text !== $original) {
        if ($dry_run) {
            echo "[DRY] Would update: $path\n";
        } else {
            file_put_contents($path, $text);
            echo "Updated: $path\n";
        }
        return true;
    }

    return false;
}

// ---------------------------------------------------------
// CLI Argument Parsing (matching Python argparse structure)
// ---------------------------------------------------------
$args = array(
    'target' => null,
    'dry_run' => false,
    'collapse_blank_lines' => 2,
    'no_whitespace' => false,
    'keep_directive' => [],
    'exclude' => [],
    'exclude_regex' => []
);

$cli_args = array_slice($argv, 1);
for ($i = 0; $i < count($cli_args); $i++) {
    $arg = $cli_args[$i];
    
    if ($arg === '--dry-run') {
        $args['dry_run'] = true;
    } else if ($arg === '--no-whitespace') {
        $args['no_whitespace'] = true;
    } else if (str_starts_with($arg, '--collapse-blank-lines=')) {
        $args['collapse_blank_lines'] = (int)explode('=', $arg)[1];
    } else if ($arg === '--collapse-blank-lines') {
        $args['collapse_blank_lines'] = (int)$cli_args[++$i];
    } else if (str_starts_with($arg, '--keep-directive=')) {
        $args['keep_directive'][] = explode('=', $arg)[1];
    } else if ($arg === '--keep-directive') {
        $args['keep_directive'][] = $cli_args[++$i];
    } else if (str_starts_with($arg, '--exclude=')) {
        $args['exclude'][] = explode('=', $arg)[1];
    } else if ($arg === '--exclude') {
        $args['exclude'][] = $cli_args[++$i];
    } else if (str_starts_with($arg, '--exclude-regex=')) {
        $args['exclude_regex'][] = explode('=', $arg)[1];
    } else if ($arg === '--exclude-regex') {
        $args['exclude_regex'][] = $cli_args[++$i];
    } else if (!str_starts_with($arg, '-')) {
        // First positional argument is target
        if ($args['target'] === null) {
            $args['target'] = $arg;
        }
    }
}

if ($args['target'] === null) {
    echo "Usage: php universal_stripper.php <target> [--dry-run] [--no-whitespace] [--collapse-blank-lines 2] [--keep-directive regex] [--exclude glob] [--exclude-regex regex]\n";
    exit(2); // Match python exit code
}

$target_path = realpath($args['target']);
if ($target_path === false) {
    echo "Invalid target: " . $args['target'] . "\n";
    exit(2);
}

$collapse_limit = $args['collapse_blank_lines'] < 0 ? null : $args['collapse_blank_lines'];

$exclude_globs = array_merge($DEFAULT_EXCLUDES, $args['exclude']);
$exclude_regexes = array_map(function($rx) { return '/' . str_replace('/', '\\/', $rx) . '/'; }, $args['exclude_regex']);

$keep_res = array_map(function($rx) { return '/' . str_replace('/', '\\/', $rx) . '/'; }, $args['keep_directive']);

$changed = 0;

if (is_file($target_path)) {
    if (process_file($target_path, $collapse_limit, $args['dry_run'], $args['no_whitespace'], $keep_res)) {
        $changed++;
    }
} else {
    $dir_iterator = new RecursiveDirectoryIterator($target_path, RecursiveDirectoryIterator::SKIP_DOTS);
    $iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST);

    foreach ($iterator as $file) {
        if (!$file->isFile()) continue;
        $path = $file->getPathname();
        
        $rel_path = rel_posix($path, $target_path);
        
        if (is_excluded($rel_path, $exclude_globs, $exclude_regexes)) {
            continue;
        }

        if (process_file($path, $collapse_limit, $args['dry_run'], $args['no_whitespace'], $keep_res)) {
            $changed++;
        }
    }
}

echo "Done. Files changed: $changed\n";
