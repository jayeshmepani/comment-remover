                      
"""Test file for Python comment stripping - complex edge cases."""

                
x = 1                  

                      
s1 = "Hello # world"
s2 = 'Hello # world'

                       
s3 = """
This is a triple-quoted string
# This is NOT a comment
"""

s4 = '''
Another triple quoted
# Also NOT a comment
'''

                  
name = "test"
f1 = f"Value: {name} # not a comment"

             
r1 = r"C:\path\to\file # not a comment"

               
nested1 = "He said 'hello # test' to me"
nested2 = 'She said "hello # test" to me'

                                      
            
                               
              

                        
                         

                                  
escaped = "test \"value\" # not comment"
escaped2 = 'test \'value\' # not comment'

                    
unicode_str = "Hello 世界 # not comment"

               
b1 = b"byte string # not comment"

                    
result = (a + b) / c                 

                  
d = {"key#1": "value", "key#2": "value"}

                               
if (n := len(data)) > 10:                  
    pass

                           
long_str = "line1 " \
           "line2"           

                                        
final = "value"                
