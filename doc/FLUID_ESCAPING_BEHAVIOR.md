Fluid Escaping Behavior
=======================

User input MUST be escaped to protect against cross-site-script attacks. 
Fluid automatically applies escaping based on the following principles:

every simple variable used inside tag content MUST be escaped by default.
the developer MAY choose to disable escaping of a variable through ```f:format.raw()```.
simple variables used as viewHelper arguments MUST NOT be escaped by default.
the developer MAY choose to enable escaping for specific arguments by 
calling ```setEscaping(true)``` on the argumentDefinition.

complex variables used inside tag content MUST NOT be escaped by default, 
because fluid can not guess what the correct conversion into a string should be.
complex variables used as viewHelper arguments MUST NOT be escaped, 
even if ```setEscaping``` is enable on the argumentDefinition.

viewHelper arguments that are an "alternative" to nested viewHelper tags, like for example
the ```then/else``` arguments/viewHelpers MUST behave the same either way. To enable the same
behavior on a viewHelper argument as a nested viewHelper tag the developer SHALL set
```setEscaping(true)``` on the argumentDefinition of that argument.

the developer MAY disable ```$escapeChildren``` inside a specific viewHelper to disable
the escaping on children when calling ```renderChildren()```.

the developer MAY enable ```$escapeOut``` inside a specific viewHelper to enable
escaping the return value of that specific viewHelper.

# Definitions

- **simple variable** is a variable that is returned by the VariableProvider that is not an Array or Object without a __toString method
- **complex variable** is a variable that is either an Array or Object without __toString method
- **tag content** is anything between an opening and closing tag
- **viewHelper argument** is a argument that is passed to a fluid viewHelper 

The key words “MUST”, “MUST NOT”, “REQUIRED”, “SHALL”, “SHALL NOT”, “SHOULD”, “SHOULD NOT”, 
“RECOMMENDED”, “MAY”, and “OPTIONAL” in this document are to be interpreted as 
described in [RFC 2119](https://tools.ietf.org/html/rfc2119).