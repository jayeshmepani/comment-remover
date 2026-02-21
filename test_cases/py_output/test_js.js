                                                                  

                 
let x = 1;                  

                   
let y = 2;

  
                   
         
   

                        
let s1 = "http://example.com";
let s2 = 'http://example.com';

                        
let s3 = "/* not a comment */";
let s4 = '/* also not */';

                   
let s5 = `template with // not comment`;
let s6 = `template with /* not comment */`;

                                   
let name = "test";
let s7 = `Hello ${name} // not comment`;

                 
let r1 = /pattern/gi;
let r2 = /pattern\/with\/slashes/i;
let r3 = /pattern/;                 

                        
if (x === 1) {
    let match = x / /pattern/.test(x);
}

                     
function test() {
    return /pattern/gi;                
}

                     
if (typeof x === /pattern/) {}

                    
let div1 = a / b / 2;
let div2 = a / b / /pattern/;

                
let n1 = "He said 'hello // test' to me";
let n2 = 'She said "hello /* test */" to me';

                                       
                           
                       
             

                     
let u1 = "Hello 世界 // not comment";

                   
let e1 = "test \"value\" // not comment";
let e2 = 'test \'value\' // not comment';

                    
let ml = "line1 \
line2"; // comment

// Object with comment-like keys
let obj = {
    "key              
    "key/*2*/": "value",
};

                                     
                        
                          

        
class MyClass {
                    
    constructor() {
                              
    }
}

                 
const fn = (x) => x * 2;                 

              
async function asyncFn() {
                    
    await Promise.resolve();
}

                
const { a, b } = obj;                    

                  
const arr = [...[1, 2, 3]];                  

                    
const val = obj?.prop?.nested;                    

                     
const result = val ?? "default";                   

         
const big = 123n;                  

                          
class PrivateClass {
    #privateField = 1;                   
}

                
class StaticClass {
    static {
                               
    }
}

                  
                           

                
export const val = 1;                  
import mod from "module";                  

                        
             
                          

                
              
