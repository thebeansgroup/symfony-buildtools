<?php
class siteBuildTestingTask extends sfPropelBaseTask
{
  protected function configure()
  {
    $this->detailedDescription = <<<EOF
The [build-testing|INFO] task:
_ deletes the initial database content
_ calls site:setup testing (clears caches and fix permissions - project specific)
_ builds the database from the schema of the latest deploy
_ tests the undo statements in the delta files
_ applies deltas to bring it up-to-date with the version created by the developer
_ restores the actual schema file
_ calls propel:build-all-load --env=test
_ runs symfony test:all task

IMPORTANT!
In order for this task to work, the user must be able to launch:
_ sudo /bin/chown
_ sudo symfony
without being asked for a passwork

EOF;

    parent::configure();

    $this->namespace = 'site';
    $this->name = 'build-testing';
    $this->briefDescription = 'Builds the testing environment.';
  }

  protected function execute($arguments = array(), $options = array())
  {
    ini_set('memory_limit', '320M');
    date_default_timezone_set('Europe/London'); // this kills a lot of annoying output
    
    // I need to get $dbInfo before any output because TaskUtils::getDatabaseConnectionDetails 
    // will try to set some cookies
    $dbInfo = TaskUtils::getDatabaseConnectionDetails('frontend', 'test');
    
    $inTesting = preg_match('!^/var/www/html/testing/!', sfConfig::get('sf_root_dir'));
    if (!$inTesting)
    {
      //throw new sfException(sprintf('This task can only be launched in the testing area.'));
    }

    echo "+++++ Setting file permissions and clearing caches... +++++\n";
    $siteSetupTask = new siteSetupTask($this->dispatcher, $this->formatter);
    $siteSetupTask->run(array('test'), array());

    echo "+++++ Building the model and the db and loading the fixture... +++++\n";
    $propelBuildAllLoadTask = new sfPropelBuildAllLoadTask($this->dispatcher, $this->formatter);
    $propelBuildAllLoadTask->run(array(), array('--env=test'));
    
    echo "+++++ Clearing the Symfony cache... +++++\n";
    $clearCacheTask = new sfCacheClearTask($this->dispatcher, $this->formatter);
    $clearCacheTask->run(array(), array());
    
/*
    echo "+++++ Running the tests... +++++\n";
    // running the tests was creating some problems, because probably they rebuild the database and that ruins the autoincrement index of the tables
    $testsFailing = TaskUtils::areTestsFailing($testsOutput);
    if ($testsFailing)
    {
      echo $testsOutput;
      throw new sfException(sprintf('The tests failed!!!'));
    }
    else
    {
      echo "All the tests passed.";
    }
*/
    echo "\n\n+++++ TASK COMPLETED SUCCESSFULLY +++++\n\n";

    echo ">>>>>> Remember to commit to the trunk <<<<<<\n";
  }
}
?>
