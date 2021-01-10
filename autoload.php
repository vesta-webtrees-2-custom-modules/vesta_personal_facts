<?php

use Composer\Autoload\ClassLoader;

$loader = new ClassLoader();
$loader->addPsr4('Cissee\\Webtrees\\Module\\PersonalFacts\\', __DIR__);
$loader->addPsr4('Cissee\\WebtreesExt\\', __DIR__ . "/patchedWebtrees");
$loader->addPsr4('Cissee\\WebtreesExt\\Module\\', __DIR__ . "/patchedWebtrees/Module");
$loader->addPsr4('Cissee\\WebtreesExt\\Functions\\', __DIR__ . "/patchedWebtrees/Functions");
$loader->addPsr4('Cissee\\WebtreesExt\\GedcomCode\\', __DIR__ . "/patchedWebtrees/GedcomCode");
$loader->register();
