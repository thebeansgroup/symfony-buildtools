<?php
class siteBuildProductionCriticalSectionTask extends sfPropelBaseTask
{
  protected function configure()
  {
    $this->detailedDescription = <<<EOF
This task must be ONLY called by the site:build-production task. This is a critical section because we need to make sure it is executed atomically.
EOF;

    parent::configure();

    $this->addArguments(array(
      new sfCommandArgument('newDeployAbsolutePath', sfCommandArgument::REQUIRED, "The absolute URL for the new release directory"),
    ));

    $this->namespace = 'site';
    $this->name = 'build-production-critical-section';
    $this->briefDescription = 'This task must be ONLY called by the site:build-production task.';
  }

  protected function execute($arguments = array(), $options = array())
  {
    date_default_timezone_set('Europe/London'); // this kills a lot of annoying output

    $this->checkCalledByBuildProductionTask();

    $newDeployAbsolutePath = $arguments['newDeployAbsolutePath'];

    // this task can only be run from a staging directory
    TaskUtils::checkInStaging();

    $projectName = TaskUtils::getProjectName();
    $apacheUser = TaskUtils::getApacheUser();


    echo "+++++ Applying delta files to production db... +++++\n";
    $revisionStart = exec ("cat " . TaskUtils::getLastDeployRevisionNumberPath());
    $SQLFromDeltas = TaskUtils::getDeltaFilesQuery($revisionStart);

    TaskUtils::applyCustomSQLWithoutReferentialIntegrity($SQLFromDeltas, 'frontend', 'prod');

    // some debug
    echo "Debug - revision start for applying deltas: $revisionStart\n";
    echo "Debug - deltas applied:  \n$SQLFromDeltas\n";

    echo "+++++ Changing the target of the symlink and chowning to $apacheUser:$apacheUser... +++++\n";
    $symlinkChangeCommand = "sudo rm -f /var/www/html/$projectName &&
sudo ln -s $newDeployAbsolutePath /var/www/html/$projectName &&
sudo chown -h $apacheUser:$apacheUser /var/www/html/$projectName";

    // some debug
    echo "Debug - executing command $symlinkChangeCommand\n";

    exec($symlinkChangeCommand);


    echo "+++++ Rsyncing to webservers again... +++++\n";
    exec("/usr/local/bin/rsync.sh");


    echo "+++++ Clearing APC. +++++\n";
    apc_clear_cache();
    // @todo: clear the cache accross all servers using Humpty


    echo "+++++ Clearing caches +++++\n";
    //exec("/usr/local/humpty/humpty -p $projectName -a symfony-clear-cache");
    //exec("/usr/local/humpty/humpty -p $projectName -a symfony-clear-minify-cache");

    
    echo "+++++ Production build complete. +++++\n";


    echo "+++++ Revision notes +++++\n";
    $revisionNotesTask = new siteDisplayRevisionNotesTask($this->dispatcher, $this->formatter);
    $revisionNotesTask->run(array(), array());

    $currentRevision = exec ("cat " . TaskUtils::getCurrentRevisionNumberPath());
    echo "\n+++++ NOW TAG THE RELEASE AGAINST REVISION $currentRevision using this command: +++++\n";
    $releaseDate = date("Y-m-d_H-i");
    $cmd = "svn cp svn://testbox.beans/projects/$projectName/trunk@r$currentRevision  svn://testbox.beans/projects/$projectName/tags/REL-$releaseDate -m'created a new release tag'";
    echo $cmd . "\n\n";
  }

  private function checkCalledByBuildProductionTask()
  {
      $pid = getmypid();
      $cmd = "ps -p $pid -o ppid";
      $ppid = exec($cmd);

      $cmd = "ps -p $ppid -o cmd";
      $parentProcessCommand = exec($cmd);

      if (strpos($parentProcessCommand, 'site:build-production') === FALSE)
      {
        throw new Exception("The site:build-production-critical-section must be ONLY called by the site:build-production task.");
      }
  }
}
?>
