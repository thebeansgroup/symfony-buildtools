<?php
class siteCheckSchemaTask extends sfBaseTask
{
  protected function configure()
  {
    $this->detailedDescription = <<<EOF
The [check-schema|INFO] task displays the differences between the original schema and the current one (it compares the current schema against the one of the latest tag)

EOF;

    parent::configure();

    $this->namespace = 'site';
    $this->name = 'check-schema';
    $this->briefDescription = 'Displays the differences between the original schema and the current one';
  }
 
  protected function execute($arguments = array(), $options = array())
  {
    $currentSchemaFile = TaskUtils::getSchemaFilePath();
    $previousSchemaUrl = '';

    if (! TaskUtils::isBranch())
    {
	// this is allright if we are in the trunk: we want to compare with the latest tag
	// For example, we may be ready for an upload and want to check the last things
	$previousSchemaUrl = TaskUtils::getLatestTagSchemaUrl();
    }
    else
    {
	// if we are on a branch, we want to compare with the initial schema of our branch.
	// That could help to write delta files
	$previousSchemaUrl = TaskUtils::getInitialSchemaUrlOfThisBranch();
    }

    $previousSchemaTempFile = '/tmp/previousSchemaTempFile' . getmypid();

    exec("svn cat $previousSchemaUrl > $previousSchemaTempFile");

    $diff = shell_exec("diff $previousSchemaTempFile $currentSchemaFile");

    echo ($diff ? $diff : "No changes in the db schema since the last upload\r\n");
  }
}
?>