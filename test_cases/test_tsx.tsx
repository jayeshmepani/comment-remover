// TSX Test file - TypeScript + JSX

import React from 'react';

// Typed component
interface Props {
    // props interface
    name: string;
    count?: number; // optional prop
}

// Function component with types
function MyComponent({ name, count = 0 }: Props): JSX.Element {
    // component comment
    return (
        <div>
            {/* JSX block comment */}
            <h1>{name /* inline comment */}</h1>
            {/* Conditional */}
            {count > 0 && <span>{count}</span>}
        </div>
    );
}

// Typed state
const TypedState: React.FC = () => {
    // State with type
    const [count, setCount] = React.useState<number>(0); // state comment
    
    return (
        <div>
            {/* Render comment */}
            <button onClick={() => setCount(c => c + 1)}>
                {/* Button content */}
                Increment
            </button>
        </div>
    );
};

// Generic component
interface ListProps<T> {
    items: T[];
    render: (item: T) => React.ReactNode;
}

function GenericList<T>({ items, render }: ListProps<T>): JSX.Element {
    // generic component comment
    return (
        <ul>
            {items.map((item, i) => (
                <li key={i}>
                    {/* Map item comment */}
                    {render(item)}
                </li>
            ))}
        </ul>
    );
}

// Forward ref with types
const ForwardRef = React.forwardRef<HTMLDivElement, Props>(
    ({ name }, ref) => {
        // forward ref comment
        return (
            <div ref={ref}>
                {/* Forwarded ref comment */}
                {name}
            </div>
        );
    }
);

// Memo with types
const Memoized = React.memo<MyComponent>(MyComponent);

// CSS-in-JS style
const styles = {
    // styles object
    container: {
        display: 'flex', // flex comment
        /* block style comment */
    }
} as const;

// Type assertion in JSX
const asserted = <div>{/* comment */}</div> as JSX.Element;

// Final
export default MyComponent;
// EOF
