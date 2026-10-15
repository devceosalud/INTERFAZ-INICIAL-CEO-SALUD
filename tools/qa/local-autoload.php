<?php
// QA-local fallback: manifests/constraints and production settings are not rewritten.
require 'phar://C:/ProgramData/ComposerSetup/bin/composer.phar/vendor/autoload.php';
if(PHP_SAPI!=='cli'||file_exists(__DIR__.'/../../.env'))throw new RuntimeException('Isolated checkout only');
$composer=Composer\Factory::create(new Composer\IO\NullIO(),null,true,true);
$config=$composer->getConfig();$config->merge(['config'=>['optimize-autoloader'=>true,'classmap-authoritative'=>false]]);
$generator=$composer->getAutoloadGenerator();$generator->setDevMode(true);$generator->setRunScripts(false);
$count=$generator->dump($config,$composer->getRepositoryManager()->getLocalRepository(),$composer->getPackage(),$composer->getInstallationManager(),'composer',true,null,$composer->getLocker());
echo 'Optimized local loader generated via Composer API; CLI completion is recorded separately.'.PHP_EOL;