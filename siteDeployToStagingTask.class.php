<?php
class siteDeployToStagingTask extends sfBaseTask
{
  protected function configure()
  {
    $this->detailedDescription = <<<EOF
The [deploy-to-staging|INFO] task deploys the application on the staging environment.
EOF;

    parent::configure();

    $this->namespace = 'site';
    $this->name = 'deploy-to-staging';
    $this->briefDescription = 'Deploys the application on the staging environment.';
  }
 
  protected function execute($arguments = array(), $options = array())
  {
    ini_set('memory_limit', '256M');

    // make sure this task is running from a working copy of the trunk. That way we can
    // record the revision number of the trunk for when we need to tag a production build.
    //
    // WHY HAS THIS BEEN COMMENTED OUT: Basically we can now deploy both the trunk as well as a branch. Keeping this code
    // would cause an exception to be thrown when trying to stage a branch.
//    $inTrunk = shell_exec("svn info | grep 'svn://testbox.beans/projects/" . TaskUtils::getProjectName() . "/trunk'");
//    if (!$inTrunk)
//    {
//      throw new sfException("Your working copy must be of the trunk for this task to work.");
//    }


    // before performing the deploy, we check that the files config/rsync_exclude.txt and
    // config/properties.ini have not been changed
    $rsyncExcludeMd5Sum = sfConfig::get('rsyncExcludeMd5Sum'); // defined in the ProjectConfiguration class
    $propertiesIniMd5Sum = sfConfig::get('propertiesIniMd5Sum'); // defined in the ProjectConfiguration class

    ob_start(); // to prevent the system command from printing out useless text
    $rsyncExcludeMd5Sum_actual = system("md5sum " . sfConfig::get('sf_config_dir') . "/rsync_exclude.txt | awk '{print $1}'");
    $propertiesIniMd5Sum_actual = system("md5sum " . sfConfig::get('sf_config_dir') . "/properties.ini | awk '{print $1}'");
    ob_end_clean();

    if ( ($rsyncExcludeMd5Sum != $rsyncExcludeMd5Sum_actual) ||
         ($propertiesIniMd5Sum != $propertiesIniMd5Sum_actual) )
    {
      throw new sfException("Either the file rsync_exclude.txt or properties.ini have been changed. Please update the md5's in config/ProjectConfiguration.class.php");
    }


    // record the revision number of the last deploy in a file that will be used when building staging
    $lastDeployRevisionNumber =  TaskUtils::getLastDeployRevisionNumber();
    $filename =  TaskUtils::getLastDeployRevisionNumberPath();
    exec("echo $lastDeployRevisionNumber > $filename");

    // writing the current revision number in a file that will be used when building production
    $currentRevisionNumber =  TaskUtils::getCurrentRevisionNumber('svn://testbox.beans/projects/' . TaskUtils::getProjectName() . '/trunk');
    $currentRevFilename =  TaskUtils::getCurrentRevisionNumberPath();
    exec("echo $currentRevisionNumber > $currentRevFilename");
    
//    THE REASON WHY THE BELOW CODE IS COMMENTED OUT IS THAT THE BUILD PROCESS STARTED TO ERROR ON SEGMENTATION FAULT
//    WHEN BUILDING THE FORMS, THUS THESE THREE STEPS HAVE BEEN PUT INDIVIDUALLY INTO THE BUILD SCRIPT
//    // before rsyncing we need to regenerate all the base classes as they are not in the repo
//    $buildAllTask = new sfPropelBuildModelTask($this->dispatcher, $this->formatter);
//    $buildAllTask->run(array(), array());
//    $buildAllTask = new sfPropelBuildFormsTask($this->dispatcher, $this->formatter);
//    $buildAllTask->run(array(), array());
//    $buildAllTask = new sfPropelBuildFiltersTask($this->dispatcher, $this->formatter);
//    $buildAllTask->run(array(), array());

    $clearCacheTask = new sfProjectDeployTask($this->dispatcher, $this->formatter);
    $clearCacheTask->run(array('production'), array('--go'));
  }
}
?>
