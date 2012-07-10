<?php
/**
 * Allows developers to get up-to-date copies of assets on a site
 */
class siteAcquireAssetsTask extends sfBaseTask
{
  protected function configure()
  {
    $this->detailedDescription = <<<EOF
The [acquire-assets|INFO] task copies assets from the vbox into
the right web directory in the working copy
EOF;

    parent::configure();

    $this->namespace = 'site';
    $this->name = 'acquire-assets';
    $this->aliases = array('acquire-assets');
    $this->briefDescription = 'Copies assets into the working copy';

    $this->addArguments(
      array(
        new sfCommandArgument('module', sfCommandArgument::REQUIRED,
          'The asset module directory to import'),
      )
    );
  }
 
  protected function execute($arguments = array(), $options = array())
  {
    $sourcePath = 'http://vbox.beans/assetpackages/' . TaskUtils::getProjectName() . "/modules/{$arguments['module']}/";

    $destPath = sfConfig::get('sf_web_dir') . '/modules/' . $arguments['module'];

    if (!is_dir($destPath) || !is_writeable($destPath))
    {
      throw new sfException("The module '{$arguments['module']}' doesn't exist or its directory is not writeable.");
    }

    echo "Acquiring assets for the {$arguments['module']} module... ";
    
    shell_exec("wget -nH -P $destPath -r -np -nc --cut-dirs=4 $sourcePath");

    echo "done\n+++++ Acquisition complete. +++++\n";
  }
}
?>