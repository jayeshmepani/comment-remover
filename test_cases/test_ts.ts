// TypeScript test file - complex edge cases

// Simple types
let x: number = 1; // inline comment
let y: string = "hello"; // string comment

/* Block comment */
let z: boolean = true;

// Type annotations
const obj: {
    // object comment
    name: string;
    age: number; // property comment
} = {
    name: "test",
    age: 30
};

// Interface
interface MyInterface {
    // interface comment
    prop: string; // prop comment
}

// Type alias
type MyType = string | number; // type comment

// Generic types
const arr: Array<string> = []; // generic comment
const map: Map<string, number> = new Map();

// Function with types
function test<T>(param: T): T {
    // function comment
    return param; // return comment
}

// Arrow with types
const fn = (x: number): number => x * 2; // arrow comment

// Class with types
class MyClass<T> {
    // class comment
    private field: T; // field comment
    
    constructor(value: T) {
        // constructor comment
        this.field = value;
    }
    
    // Method
    method(): T {
        // method comment
        return this.field;
    }
}

// Union types
let union: string | number = "test"; // union comment

// Intersection types
type Intersection = A & B; // intersection comment

// Tuple
const tuple: [string, number] = ["a", 1]; // tuple comment

// Enum
enum Direction {
    // enum comment
    Up = "UP",
    Down = "DOWN", // member comment
}

// Const assertion
const config = {
    // config comment
    url: "http://example.com"
} as const;

// Type guard
function isString(x: any): x is string {
    // type guard comment
    return typeof x === "string";
}

// Utility types
type PartialType = Partial<MyInterface>; // utility comment
type ReadonlyType = Readonly<MyInterface>;

// Decorator (experimental)
// @decorator
// class DecoratedClass {}

// Namespace
namespace MyNamespace {
    // namespace comment
    export const val = 1;
}

// Import/Export with types
import type { MyType } from "./module"; // import type comment
export type { MyInterface }; // export type comment

// Assertion
const asserted = {} as MyInterface; // assertion comment
const angled = <MyInterface>{}; // angled comment

// Non-null assertion
const nonNull = maybeNull!; // non-null comment

// Optional chaining with types
const opt = obj?.prop; // optional comment

// Satisfies operator (TS 4.9+)
const satisfies = { a: 1 } satisfies Record<string, number>;

// Final
// end of file comment
