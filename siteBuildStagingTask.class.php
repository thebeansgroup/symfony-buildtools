<?php
class siteBuildStagingTask extends sfPropelBaseTask
{
  protected function configure()
  {
    $this->detailedDescription = <<<EOF
The [build-staging|INFO] task:
_ manages a lock file to apply undo statements in the case the staging was already built
_ calls site:setup staging (clears caches and fix permissions - project specific)
_ applies deltas
_ runs symfony test:all task
_ if all the tests pass, rsync from web1 to web2

IMPORTANT!
In order for this task to work, the user must be able to launch:
_ sudo /bin/chown
_ sudo symfony
without being asked for a password

EOF;

    parent::configure();

    $this->namespace = 'site';
    $this->name = 'build-staging';
    $this->briefDescription = 'Builds the staging environment.';
  }
 
  protected function execute($arguments = array(), $options = array())
  {
    date_default_timezone_set('Europe/London'); // this kills a lot of annoying output
    
    $inStaging = preg_match('!^/var/www/html/staging/!', sfConfig::get('sf_root_dir'));
    if (!$inStaging)
    {
      throw new sfException(sprintf('This task can only be launched in the staging area.'));
    }

    $revisionStart = exec ("cat " . TaskUtils::getLastDeployRevisionNumberPath());


    if (TaskUtils::lockFileExist('builtStaging')) // the staging was already built
    {
      echo "+++++ Applying undo statements... +++++\n";
      $undoSQLFromDeltas = TaskUtils::getDeltaFilesQuery($revisionStart, true);
      TaskUtils::applyCustomSQLWithoutReferentialIntegrity($undoSQLFromDeltas, 'frontend', 'staging');
    }

    echo "+++++ Setting file permissions and clearing caches... +++++\n";
    $siteSetupTask = new siteSetupTask($this->dispatcher, $this->formatter);
    $siteSetupTask->run(array('staging'), array());


    echo "+++++ Applying delta files... +++++\n";
    $SQLFromDeltas = TaskUtils::getDeltaFilesQuery($revisionStart);
    echo "The following SQL will be applied: \n$SQLFromDeltas\n";
    TaskUtils::applyCustomSQLWithoutReferentialIntegrity($SQLFromDeltas, 'frontend', 'staging');

    echo "+++++ Creating lock file... +++++\n";
    TaskUtils::createLockFile('builtStaging');


    // if all the tests pass, we rsync
    echo "+++++ Rsyncing from web1 to web2... +++++\n";
    exec("sudo /usr/local/bin/rsync" . ucfirst(TaskUtils::getProjectName()) . "Staging.sh");

    echo "+++++ Staging build completed successfully. +++++\n";
  }
}
?>
