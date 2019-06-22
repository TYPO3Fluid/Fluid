# Fluid for Template Designers
In Fluid template consists of two parts: a layout and a template file. Layouts build the HTML 'skeleton' of your page (the part between the `body` tag) and defines the dynamic part with 'sections'. The representation for the backend of those sections and any additional fields are defined in templates. To give you a better idea about that here's an example:

Layout file `typo3conf/ext/myextension/Resources/Private/Layouts/Foo.html`
```xml
<f:layout name="Foo" />

<div id="page" class="{settings.pageClass}">
    <div id="sidebar">
        <f:render section="Sidebar" />
    </div>
    <div id="content">
        <f:render section="Content" />
    </div>
</div>
```

Template file `typo3conf/ext/myextension/Resources/Private/Templates/Page/Foo.html`
```xml
<div xmlns="http://www.w3.org/1999/xhtml" lang="en"
      xmlns:f="http://typo3.org/ns/TYPO3/Fluid/ViewHelpers"
      xmlns:flux="http://typo3.org/ns/FluidTYPO3/Flux/ViewHelpers"
      xmlns:v="http://typo3.org/ns/FluidTYPO3/Vhs/ViewHelpers">

<f:layout name="Foo" />

<f:section name="Configuration">

    <flux:form id="foo">

        <!-- Input field for Fluid variable 'pageClass' -->
        <flux:field.input name="settings.pageClass" default="some-css-class" />

        <!-- Backend layout grid (TYPO3 6.x and greater only) -->
        <flux:grid>
            <flux:grid.row>
                <flux:grid.column colPos="1" name="Sidebar" style="width: 25%" />
                <flux:grid.column colPos="0" name="Content" style="width: 75%" />
            </flux:grid.row>
        </flux:grid>

    </flux:form>

</f:section>

<f:section name="Content">
    <!-- Render colPos=0 in this section -->
    <v:content.render column="0" />
</f:section>

<f:section name="Sidebar">
    <!-- Render colPos=1 in this section -->
    <v:content.render column="1" />
</f:section>

</div>
```
## Explanation
We implement a page template named Foo. To 'connect' template and layout they are equally named `Foo.html` and both declare `<f:layout name="Foo" />`.

> {info} The div containers' only purpose is to enable code completion in your favorite IDE and will not be output.

The layout contains some simple HTML structure with two content areas and the outer div container's CSS class is controlled by a Fluid variable `{settings.pageClass}`. The variable is prefixed `settings.` which is not required by configuration but very useful. This will become clear at a later stage.

> {info} Per convention layouts have to define a <f:section name="Main" /> which is the section that will finally get rendered.

The template defines the backend representation of this layout by providing a flexform and a backend layout grid (only available in TYPO3 6.x and greater). This flexform is defined with `flux` viewhelpers which makes that part really simple.

> {info} The bare minimum for a page layout file is to define a section named Configuration containing a flexform with at least an id to make it selectable in the backend.


In our example we add an input field for the CSS class which is then available in the layout as a Fluid variable of the same name:

```xml
<flux:form id="foo">

    <!-- Input field for Fluid variable 'pageClass' -->
    <flux:field.input name="settings.pageClass" default="some-css-class" />

    [...]

</flux:form>
```

and a grid that will be used as the backend layout:
```xml
<flux:form id="foo">

    [...]

    <!-- Backend layout grid (TYPO3 6.x and greater only) -->
    <flux:grid>
        <flux:grid.row>
            <flux:grid.column colPos="1" name="Sidebar" style="width: 25%" />
            <flux:grid.column colPos="0" name="Content" style="width: 75%" />
        </flux:grid.row>
    </flux:grid>

</flux:form>
```
The sections Content and Sidebar make use of a `vhs` viewhelper to render the content of those columns.

> {info} All available viewhelpers and their arguments can be looked up in the reference on fluidtypo3.org

You should now be able to select the page layout in the backend by editing a page's properties after clearing all caches. `fluidpages` includes some fine inheritance feature that enables you to select the page template not only for the current page but also for its children and the chain of inheritance can be interrupted at any level.