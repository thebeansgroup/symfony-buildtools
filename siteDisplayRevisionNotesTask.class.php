<?php
class siteDisplayRevisionNotesTask extends sfBaseTask
{
  var $availableEnvironments;

  protected function configure()
  {

    $this->detailedDescription = <<<EOF
The [site:display-revision-notes|INFO] task:
_ displays all the revision notes to apply from the latest build

EOF;

    parent::configure();

    $this->namespace = 'site';
    $this->name = 'display-revision-notes';
    $this->briefDescription = 'Displays all the revision notes to apply from the latest build.';
  }
 
  protected function execute($arguments = array(), $options = array())
  {
    $lastDeployRevisionNumberPath = TaskUtils::getLastDeployRevisionNumberPath();

    if (is_file($lastDeployRevisionNumberPath))
    {
        $latestTagRevision = file_get_contents($lastDeployRevisionNumberPath);
    }
    else
    {
        $latestTagRevision = TaskUtils::getLastDeployRevisionNumber();
    }

    echo wordwrap(TaskUtils::getRevisionNotes($latestTagRevision));
  }
}
?>
