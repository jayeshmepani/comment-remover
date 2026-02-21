                                            

               
let x: number = 1;                  
let y: string = "hello";                  

                   
let z: boolean = true;

                   
const obj: {
                     
    name: string;
    age: number;                    
} = {
    name: "test",
    age: 30
};

            
interface MyInterface {
                        
    prop: string;                
}

             
type MyType = string | number;                

                
const arr: Array<string> = [];                   
const map: Map<string, number> = new Map();

                      
function test<T>(param: T): T {
                       
    return param;                  
}

                   
const fn = (x: number): number => x * 2;                 

                   
class MyClass<T> {
                    
    private field: T;                 
    
    constructor(value: T) {
                              
        this.field = value;
    }
    
             
    method(): T {
                         
        return this.field;
    }
}

              
let union: string | number = "test";                 

                     
type Intersection = A & B;                        

        
const tuple: [string, number] = ["a", 1];                 

       
enum Direction {
                   
    Up = "UP",
    Down = "DOWN",                  
}

                  
const config = {
                     
    url: "http://example.com"
} as const;

             
function isString(x: any): x is string {
                         
    return typeof x === "string";
}

                
type PartialType = Partial<MyInterface>;                   
type ReadonlyType = Readonly<MyInterface>;

                           
             
                          

            
namespace MyNamespace {
                        
    export const val = 1;
}

                           
import type { MyType } from "./module";                       
export type { MyInterface };                       

            
const asserted = {} as MyInterface;                     
const angled = <MyInterface>{};                  

                     
const nonNull = maybeNull!;                    

                               
const opt = obj?.prop;                    

                               
const satisfies = { a: 1 } satisfies Record<string, number>;

        
                      
