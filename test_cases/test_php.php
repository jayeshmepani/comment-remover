<?php
/**
 * Test file for PHP comment stripping - complex edge cases
 */

// Simple comment
$x = 1; // inline comment

/* Block comment */
$y = 2;

/*
 * Multi-line block comment
 * Line 2
 * Line 3
 */

// String with # inside
$s1 = "Hello # world";
$s2 = 'Hello # world';

// String with // inside
$s3 = "http://example.com # not comment";
$s4 = 'http://example.com';

// String with /* inside
$s5 = "/* not a comment */";
$s6 = '/* also not */';

// Heredoc
$heredoc = <<<EOT
This is heredoc
// Not a comment
/* Not a comment */
# Not a comment
EOT;

// Nowdoc
$nowdoc = <<<'EOT'
This is nowdoc
// Not a comment
/* Not a comment */
EOT;

// Nested quotes
$nested1 = "He said 'hello // test' to me";
$nested2 = 'She said "hello /* test */" to me';

// Comment with directive (should keep)
// eslint-disable-next-line
// istanbul ignore next
// @var string $var

// Regex-like patterns in strings
$regex1 = "/pattern/";
$regex2 = '#pattern#';

// URL in string
$url = "https://example.com/path?param=value#anchor";

// Division vs comment
$div = $a / $b / 2;

// Complex expression
$result = ($a + $b) / $c; // final comment

// Array with comment-like keys
$arr = [
    "key//1" => "value",
    "key/*2*/" => "value",
];

// Inline HTML mixed
?>
<!-- HTML comment -->
<?php

// After PHP reopen
$z = 3;

// DocComment (should be removed)
/**
 * @param string $x
 * @return int
 */
function test($x) {
    return (int)$x;
}

// Namespace and use
namespace Test; // namespace comment
use Foo\Bar; // use comment

// Trait
trait TestTrait {
    // trait comment
}

// Anonymous class
$anon = new class {
    // anonymous class comment
};

// Arrow function (PHP 7.4+)
$fn = fn($x) => $x * 2; // arrow comment

// Attributes (PHP 8+)
#[Attribute]
class MyAttribute {
    // attribute comment
}

// Match expression (PHP 8+)
$result = match($x) {
    1 => 'one', // match comment
    default => 'other'
};

// Nullsafe operator (PHP 8+)
$value = $obj?->method(); // nullsafe comment

// Named arguments
func(name: "value"); // named arg comment

// Union types (PHP 8+)
function test2(int|string $x): void {
    // union type comment
}

// End of file comment
// final comment