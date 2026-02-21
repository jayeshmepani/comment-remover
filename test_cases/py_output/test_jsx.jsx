                                     

import React from 'react';

                   
function MyComponent() {
                              
    return (
        <div>
                                     
                
                          
                           
               
                                   
            
                                   
                                         
                                       
             
            
                                                                    
                   
            
                                  
                                     */}
            
            <span>Hello</span>
            <span>                       </span>
            <span>World</span>
        </div>
    );
}

                                   
const s1 = "/* not a comment */";
const s2 = '// not a comment';
const s3 = `/* template */`;

                       
function ComponentWithExpr({ value }) {
    return (
        <div>
                                             
            {value                     }
                                 
            
                                        
            {a          + b         }
        </div>
    );
}

           
const FragmentComp = () => (
    <>
                                
        <div>Content</div>
    </>
);

                        
const Conditional = ({ show }) => (
    <div>
        {show && <span>                           </span>}
        {show ? <span>                       </span> : null}
    </div>
);

                
const List = ({ items }) => (
    <ul>
        {items.map(item => (
            <li key={item.id}>
                                   
                {item.name}
            </li>
        ))}
    </ul>
);

                 
const WithEvents = () => (
    <button
        onClick={() => {
                              
            console.log('clicked');
        }}
    >
                              
        Click
    </button>
);

                          
const styled = {
    color: 'red',               
                           
};

        
export default MyComponent;
              
