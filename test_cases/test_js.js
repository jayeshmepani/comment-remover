// Test file for JavaScript comment stripping - complex edge cases

// Simple comment
let x = 1; // inline comment

/* Block comment */
let y = 2;

/*
 * Multi-line block
 * Line 2
 */

// String with // inside
let s1 = "http://example.com";
let s2 = 'http://example.com';

// String with /* inside
let s3 = "/* not a comment */";
let s4 = '/* also not */';

// Template literal
let s5 = `template with // not comment`;
let s6 = `template with /* not comment */`;

// Template literal with expression
let name = "test";
let s7 = `Hello ${name} // not comment`;

// Regex literals
let r1 = /pattern/gi;
let r2 = /pattern\/with\/slashes/i;
let r3 = /pattern/; // regex comment

// Regex after operators
if (x === 1) {
    let match = x / /pattern/.test(x);
}

// Regex after return
function test() {
    return /pattern/gi; // return regex
}

// Regex after typeof
if (typeof x === /pattern/) {}

// Division vs regex
let div1 = a / b / 2;
let div2 = a / b / /pattern/;

// Nested quotes
let n1 = "He said 'hello // test' to me";
let n2 = 'She said "hello /* test */" to me';

// Comment with directive (should keep)
// eslint-disable-next-line
// istanbul ignore next
// @ts-ignore

// Unicode in strings
let u1 = "Hello 世界 // not comment";

// Escape sequences
let e1 = "test \"value\" // not comment";
let e2 = 'test \'value\' // not comment';

// Multi-line string
let ml = "line1 \
line2"; // comment

// Object with comment-like keys
let obj = {
    "key//1": "value",
    "key/*2*/": "value",
};

// JSX-style comments (for JSX files)
// { /* jsx comment */ }
// { // jsx line comment }

// Class
class MyClass {
    // class comment
    constructor() {
        // constructor comment
    }
}

// Arrow function
const fn = (x) => x * 2; // arrow comment

// Async/await
async function asyncFn() {
    // async comment
    await Promise.resolve();
}

// Destructuring
const { a, b } = obj; // destruct comment

// Spread operator
const arr = [...[1, 2, 3]]; // spread comment

// Optional chaining
const val = obj?.prop?.nested; // optional comment

// Nullish coalescing
const result = val ?? "default"; // nullish comment

// BigInt
const big = 123n; // bigint comment

// Private fields (ES2022)
class PrivateClass {
    #privateField = 1; // private comment
}

// Static blocks
class StaticClass {
    static {
        // static block comment
    }
}

// Top-level await
// await Promise.resolve();

// Export/Import
export const val = 1; // export comment
import mod from "module"; // import comment

// Decorators (proposal)
// @decorator
// class DecoratedClass {}

// Final comment
// end of file
