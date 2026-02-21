<?php
   
                                                           
   

                 
$x = 1;                  

                   
$y = 2;

  
                           
         
         
   

                       
$s1 = "Hello # world";
$s2 = 'Hello # world';

                        
$s3 = "http://example.com # not comment";
$s4 = 'http://example.com';

                        
$s5 = "/* not a comment */";
$s6 = '/* also not */';

          
$heredoc = <<<EOT
This is heredoc
// Not a comment
/* Not a comment */
# Not a comment
EOT;

         
$nowdoc = <<<'EOT'
This is nowdoc
// Not a comment
/* Not a comment */
EOT;

                
$nested1 = "He said 'hello // test' to me";
$nested2 = 'She said "hello /* test */" to me';

                                       
                           
                       
                   

                                 
$regex1 = "/pattern/";
$regex2 = '#pattern#';

                
$url = "https://example.com/path?param=value#anchor";

                      
$div = $a / $b / 2;

                     
$result = ($a + $b) / $c;                 

                               
$arr = [
    "key//1" => "value",
    "key/*2*/" => "value",
];

                    
?>
<!-- HTML comment -->
<?php

                   
$z = 3;

                                 
   
                   
              
   
function test($x) {
    return (int)$x;
}

                    
namespace Test;                     
use Foo\Bar;               

        
trait TestTrait {
                    
}

                  
$anon = new class {
                              
};

                            
$fn = fn($x) => $x * 2;                 

                      
#[Attribute]
class MyAttribute {
                        
}

                            
$result = match($x) {
    1 => 'one',                 
    default => 'other'
};

                             
$value = $obj?->method();                    

                  
func(name: "value");                     

                       
function test2(int|string $x): void {
                         
}

                      
                
