#!/usr/bin/env python3
"""Test file for Python comment stripping - complex edge cases."""

# Simple comment
x = 1  # inline comment

# String with # inside
s1 = "Hello # world"
s2 = 'Hello # world'

# Triple-quoted strings
s3 = """
This is a triple-quoted string
# This is NOT a comment
"""

s4 = '''
Another triple quoted
# Also NOT a comment
'''

# f-strings with #
name = "test"
f1 = f"Value: {name} # not a comment"

# Raw strings
r1 = r"C:\path\to\file # not a comment"

# Nested quotes
nested1 = "He said 'hello # test' to me"
nested2 = 'She said "hello # test" to me'

# Comment with directive (should keep)
# noqa: F401
# pylint: disable=unused-import
# type: ignore

# Multiple # in one line
# comment # with # hashes

# String containing escaped quotes
escaped = "test \"value\" # not comment"
escaped2 = 'test \'value\' # not comment'

# Unicode in strings
unicode_str = "Hello 世界 # not comment"

# Bytes literal
b1 = b"byte string # not comment"

# Complex expression
result = (a + b) / c  # final comment

# Hash in dict key
d = {"key#1": "value", "key#2": "value"}

# Walrus operator (Python 3.8+)
if (n := len(data)) > 10:  # walrus comment
    pass

# Multi-line with backslash
long_str = "line1 " \
           "line2"  # comment

# Comment at end of file without newline
final = "value"  # last comment