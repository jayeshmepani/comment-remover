                                   

import React from 'react';

                  
interface Props {
                      
    name: string;
    count?: number;                 
}

                                
function MyComponent({ name, count = 0 }: Props): JSX.Element {
                        
    return (
        <div>
                                     
            <h1>{name                     }</h1>
                               
            {count > 0 && <span>{count}</span>}
        </div>
    );
}

              
const TypedState: React.FC = () => {
                      
    const [count, setCount] = React.useState<number>(0);                 
    
    return (
        <div>
                                  
            <button onClick={() => setCount(c => c + 1)}>
                                      
                Increment
            </button>
        </div>
    );
};

                    
interface ListProps<T> {
    items: T[];
    render: (item: T) => React.ReactNode;
}

function GenericList<T>({ items, render }: ListProps<T>): JSX.Element {
                                
    return (
        <ul>
            {items.map((item, i) => (
                <li key={i}>
                                            
                    {render(item)}
                </li>
            ))}
        </ul>
    );
}

                         
const ForwardRef = React.forwardRef<HTMLDivElement, Props>(
    ({ name }, ref) => {
                              
        return (
            <div ref={ref}>
                                             
                {name}
            </div>
        );
    }
);

                  
const Memoized = React.memo<MyComponent>(MyComponent);

                  
const styles = {
                    
    container: {
        display: 'flex',                
                                 
    }
} as const;

                        
const asserted = <div>               </div> as JSX.Element;

        
export default MyComponent;
      
