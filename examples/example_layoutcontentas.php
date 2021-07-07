<?php

/*
 * EXAMPLE: MVC pattern used with TYPO3.Fluid
 *
 * This examples shows how TYPO3.Fluid is integrated
 * in an MVC context, highlighting which parts may
 * be replaced in order to adapt the engine to your
 * favorite MVC framework.
 *
 * The alternative to this is single file rendering
 * - see the other example for that.
 */

require __DIR__ . '/include/view_init.php';

// Assign Layout name as ViewVariable which we will pass to f:layout as name
$view->assign('layout', 'Dynamic');

// Set the template path and filename we will render
$view->getTemplatePaths()->setTemplatePathAndFilename(__DIR__ . '/Resources/Private/Singles/ContentFromLayout.html');

$output = $view->render();

// Output of Controller "Default" action "Default" using helper from view_init.php
example_output($output);
