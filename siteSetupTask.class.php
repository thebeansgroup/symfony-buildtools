<?php

class siteSetupTask extends sfBaseTask
{
  var $availableEnvironments;

  protected function configure()
  {

    $this->detailedDescription = <<<EOF
The [site:setup|INFO] task:
_ chowns the site to the apache user
_ calls the fix-perms symfony task
_ clears the symfony cache
_ fixes the file permissions on the lucene index
_ clears the cache and fixes the permissions for the sfMinifyTSPlugin

IMPORTANT!
In order for this task to work, the user must be able to launch:
_ sudo /bin/chown
_ sudo symfony
without being asked for a passwork

EOF;

    parent::configure();

    $this->availableEnvironments = array('dev', 'test', 'staging', 'prod');

    $availableEnvironmentsString = implode(', ', $this->availableEnvironments);

    $this->addArguments(array(
      new sfCommandArgument('environment', sfCommandArgument::REQUIRED, "The environment to run the command on. It must be one of these: $availableEnvironmentsString"),
    ));

    $this->namespace = 'site';
    $this->name = 'setup';
    $this->briefDescription = 'Setups up the correct files permissions and clears the caches.';
  }
 
  protected function execute($arguments = array(), $options = array())
  {
    $environment = $arguments['environment'];
    $apacheUser = TaskUtils::getApacheUser();

    if ( ($environment == 'staging') && (stripos(__FILE__, '/var/www/html/staging') === FALSE) )
    {
      throw new sfException("You must be in the staging area to run site:setup staging");
    }
    
    if (!in_array($environment, $this->availableEnvironments))
    {
      throw new sfCommandException(sprintf("The environment $environment is not available. Check the help page of this command."));
    }

    // we might as well try to clear the cache prior to chowning and chmodding
    echo "+++++ Clearing the Symfony cache... +++++\n";
    // The conventional method for clearing the cache doesn't always work (it should depend on the size of the content in the cache)
    // $clearCacheTask = new sfCacheClearTask($this->dispatcher, $this->formatter);
    // $clearCacheTask->run(array(), array());
    sfToolkit::clearGlob(sfConfig::get('sf_cache_dir') . DIRECTORY_SEPARATOR);    
    
    echo "+++++ Chowning the site to {$apacheUser}:{$apacheUser}... +++++\n";
    system("sudo chown -R {$apacheUser}:{$apacheUser} " . sfConfig::get('sf_root_dir'));
    
    echo "+++++ Chmodding the site to g+w... +++++\n";
    system("sudo chmod -R g+w " . sfConfig::get('sf_root_dir'));
    
    echo "+++++ Fixing general file permissions... +++++\n";
    system("sudo " . sfConfig::get('sf_symfony') . "/symfony fix-perms");

    echo "+++++ Clearing the Symfony cache... +++++\n";
    $clearCacheTask = new sfCacheClearTask($this->dispatcher, $this->formatter);
    $clearCacheTask->run(array(), array());

    if (is_dir('data/lucene')) 
    {
      echo "+++++ Fixing lucene file permissions... +++++\n";
      system("sudo chmod -R 770 " . sfConfig::get('sf_root_dir') . "/data/lucene");
      system("sudo chown -R {$apacheUser}:{$apacheUser} " . sfConfig::get('sf_root_dir') . "/data/lucene");
    }
  }
}
?>
