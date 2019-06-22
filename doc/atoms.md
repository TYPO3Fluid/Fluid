# Atoms
Atoms is a new term introuduced in Fluid 3.0. It remove Partials, Templates and Layouts concept into one concept which is called Atoms. It can be referenced by a name like `Partial/MyPartial` which then behaves like Partials.
It is completely on the developer to have multiple templete types. Atoms can also use `Partial,Layout,Templates 
and Pragmas` as the atomRootPath can be defined as parent folder of templates, partials and layouts.

## ways to use it

An atom can either be _rendered_ or _imported_. An imported atom becomes part of the syntax tree whereas a rendered is first resolved and then can be rendered as f:render does today and passes arguments. In fact, the f:render is likely to be deprecated.

An atom can carry required parameter definitions and can be termed as a `component`.
An atom can be automatically downloaded. An assistant ViewHelper can be created to allow placing usage examples that will NOT be parsed And NOT parsing the body of a tag is just one of the new features of the `Sequencer` (a new sequential processor introduced in Fluid 3.0).


> {info}// TODO concerete examples with paths and how to use them would be the next lines of this article.