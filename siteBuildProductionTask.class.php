<?php
class siteBuildProductionTask extends sfPropelBaseTask
{
  protected function configure()
  {
    $this->detailedDescription = <<<EOF
The [build-production|INFO] task:
_ rsyncs the staging directory to the production directory
_ applies deltas to production db
_ clears caches using site:setup prod
_ tags a release

IMPORTANT!
This task MUST be run from a staging directory.

In order for this task to work, the user must be able to launch:
_ sudo /bin/chown
_ sudo symfony
_ sudo /usr/local/bin/rsync.sh
without being asked for a password

EOF;

    parent::configure();

    $this->namespace = 'site';
    $this->name = 'build-production';
    $this->briefDescription = 'Builds the production environment.';
  }

  protected function execute($arguments = array(), $options = array())
  {
    date_default_timezone_set('Europe/London'); // this kills a lot of annoying output
    $apacheUser = TaskUtils::getApacheUser();

    // this task can only be run from a staging directory
    TaskUtils::checkInStaging();

    $projectName = TaskUtils::getProjectName();

    if (!$this->askConfirmation("You want to deploy the $projectName project. Right? (y/n)", 'QUESTION', false))
    {
      exit();
    }

    //echo "+++++ Backing up web1 ++++++\n";
    //exec('sudo /usr/local/bin/backup.sh');

    //echo "+++++ Backing up database server file system ++++++\n";
    //exec("ssh testbox@dbserver sudo /usr/local/bin/backup.sh");

    //echo "+++++ Backing up database server file system ++++++\n";
    //exec("ssh testbox@dbserver sudo /usr/local/bin/backupDBs.php");

    echo "+++++ Removing inactive releases (dry run) +++++\n";
    TaskUtils::removeInactiveReleases($projectName, true);
    if (!$this->askConfirmation('Does that look allright? (y/n)', 'QUESTION', false))
    {
      exit();
    }

    echo "+++++ Removing inactive releases +++++\n";
    TaskUtils::removeInactiveReleases($projectName); // you can run this in dry-run mode

    echo "+++++ Rsyncing the cleaning up (this may take a while) +++++\n";
    $currentReleaseDirName = TaskUtils::getCurrentReleasePath($projectName, true);
    // to increase the speed of this step we are not keeping old releases on web2
    // exec("sudo rsync -avzut --delete /var/www/html/releases/old-releases/$projectName web2:/var/www/html/releases/old-releases/");
    //exec("sudo rsync -avzut --exclude=/$projectName/$currentReleaseDirName --delete /var/www/html/releases/$projectName web2:/var/www/html/releases/");
    $cmd = "/usr/local/bin/rsyncCleanup.sh -p $projectName";
    exec($cmd);

    echo "+++++ Prepping the current release to be copied as a base for the next_release directory... +++++\n";
    $cmd = 'sudo /usr/local/bin/preDeploy' . ucfirst($projectName) . '.sh';
    echo "Debug - executing command: $cmd \n";
    exec($cmd);

    echo "+++++ Copying files from staging web root to production web root... +++++\n";
    $cmd = 'sudo /usr/local/bin/rsync' . ucfirst($projectName) . 'StagingToProduction.sh';
    echo "Debug - executing command: $cmd \n";
    exec($cmd);

    echo "+++++ Prepping the new release directory... +++++\n";
    $nextReleaseDirectory = '/var/www/html/releases/' . $projectName . '/next_release';

    if (!is_dir($nextReleaseDirectory))
    {
        throw new Exception('Unable to find the next_release directory. You need to run the preDeploy script.');
    }

    $newDeployAbsolutePath = '/var/www/html/releases/' . $projectName . '/' . date('Y-m-d_h-i');
    exec("sudo mv $nextReleaseDirectory " . $newDeployAbsolutePath);
    exec("sudo chown -R $apacheUser:$apacheUser $newDeployAbsolutePath");
    exec('sudo chmod -R 775 ' . $newDeployAbsolutePath);
    exec('sudo chmod -R 777 ' . $newDeployAbsolutePath . '/cache');
    exec('sudo chmod -R 777 ' . $newDeployAbsolutePath . '/web/sfMinifyTSPlugin/cache');
    echo "Debug - path to the new release: $newDeployAbsolutePath \n";

    echo "+++++ Rsyncing from web1 to web2... +++++\n";
    //exec("sudo rsync -avzut --exclude=*/clickheat/logs/* --exclude=*.swp $newDeployAbsolutePath web2:/var/www/html/releases/$projectName/");
    $cmd = "/usr/local/bin/rsyncDeploy.sh -p $projectName -n $newDeployAbsolutePath";
    exec($cmd);

    // the previous exec will hang until the previous command is completed (see PHP exec manual)


    echo "+++++ creating undo-statements file, useful in the event of a rollback... +++++\n";
    $revisionStart = exec ("cat " . TaskUtils::getLastDeployRevisionNumberPath());
    $SQLFromUndoDeltas = TaskUtils::getDeltaFilesQuery($revisionStart, true);
    file_put_contents(TaskUtils::getUndoDeltasFilePath($projectName), $SQLFromUndoDeltas);
    echo "Debug - undo statements: \n $SQLFromUndoDeltas \n";


    echo "+++++ checking the new release directory has been created... +++++\n";
    if (!is_dir($newDeployAbsolutePath))
    {
	die("The new release directory $newDeployAbsolutePath hasn't been created.");
    }


    echo "+++++ pause to check everything is allright before flicking the switch... +++++\n";
    echo "Debug - the new active directory is going to be : $newDeployAbsolutePath  \n";

    if (!$this->askConfirmation('press y when ready', 'QUESTION', false))
    {
      exit();
    }

    // what is coming next needs to be executed as an atomic step. That is why we need
    // to call another task using the 'nohup' binary
    $command = 'nohup ' . sfConfig::get('sf_root_dir') . "/symfony site:build-production-critical-section '" . $newDeployAbsolutePath . "'";
    passthru($command);
  }
}
?>
