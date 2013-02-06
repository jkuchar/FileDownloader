<?php

/**
 * My Application bootstrap file.
 *
 * @copyright  Copyright (c) 2009 John Doe
 * @package    MyApplication
 */


// Step 1: Load Nette Framework
// this allows load Nette Framework classes automatically so that
// you don't have to litter your code with 'require' statements
require LIBS_DIR . '/nette/loader.php';


// Step 2: Configure environment
// 2a) enable Nette\Debug for better exception and error visualisation
\Nette\Diagnostics\Debugger::enable(\Nette\Diagnostics\Debugger::DEVELOPMENT, APP_DIR."/log");

// 2b) load configuration from config.ini file
//Nette\Environment::loadConfig();



// Step 3: Configure application
// 3a) get and setup a front controller
$application = Nette\Environment::getApplication();
$application->errorPresenter = 'Error';
//$application->catchExceptions = TRUE;


$loader = new \Nette\Loaders\RobotLoader();
$loader->setCacheStorage(Nette\Environment::getContext()->cacheStorage);
$loader->addDirectory(APP_DIR);
$loader->addDirectory(LIBS_DIR);
$loader->addDirectory(LIBS_DIR."/../FileDownloader");
$loader->register();


use \Nette\Application\Routers\Route;

// Step 4: Setup application router
$router = $application->getRouter();

$router[] = new Route('index.php', array(
	'presenter' => 'Download',
	'action' => 'default',
), Route::ONE_WAY);

$router[] = new Route('<presenter>/<action>/<id>', array(
	'presenter' => 'Download',
	'action' => 'default',
	'id' => NULL,
));



// Step 5: Run the application!
$application->run();
