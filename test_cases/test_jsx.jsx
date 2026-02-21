// JSX Test file - complex edge cases

import React from 'react';

// Simple component
function MyComponent() {
    // comment inside function
    return (
        <div>
            {/* JSX block comment */}
            {/* 
                Multi-line
                JSX comment
            */}
            {/* Single line JSX */}
            
            {/* JSX with braces */}
            { /* comment with braces */ }
            { // line comment in braces
            }
            
            {/* Empty JSX expression - should remove whole thing */}
            {/* */}
            
            {/* Nested looking */}
            {/* { /* not nested */ } */}
            
            <span>Hello</span>
            <span>{/* comment between */}</span>
            <span>World</span>
        </div>
    );
}

// String with comment-like content
const s1 = "/* not a comment */";
const s2 = '// not a comment';
const s3 = `/* template */`;

// JSX with expressions
function ComponentWithExpr({ value }) {
    return (
        <div>
            {/* Comment before expression */}
            {value /* inline comment */}
            {/* Comment after */}
            
            {/* Multiple expressions */}
            {a /* c1 */ + b /* c2 */}
        </div>
    );
}

// Fragment
const FragmentComp = () => (
    <>
        {/* Fragment comment */}
        <div>Content</div>
    </>
);

// Conditional rendering
const Conditional = ({ show }) => (
    <div>
        {show && <span>{/* conditional comment */}</span>}
        {show ? <span>{/* ternary comment */}</span> : null}
    </div>
);

// Map rendering
const List = ({ items }) => (
    <ul>
        {items.map(item => (
            <li key={item.id}>
                {/* Map comment */}
                {item.name}
            </li>
        ))}
    </ul>
);

// Event handlers
const WithEvents = () => (
    <button
        onClick={() => {
            // Handler comment
            console.log('clicked');
        }}
    >
        {/* Button comment */}
        Click
    </button>
);

// Styled components style
const styled = {
    color: 'red', // CSS comment
    /* block css comment */
};

// Final
export default MyComponent;
// EOF comment
