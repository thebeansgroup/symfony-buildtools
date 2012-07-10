<?php
class siteDeltaTask extends sfBaseTask
{
  protected function configure()
  {
    $this->detailedDescription = <<<EOF
The [delta|INFO]

EOF;

    parent::configure();

    $this->namespace = 'site';
    $this->name = 'delta';
    $this->aliases = array('delta');
    $this->briefDescription = 'Create a delta file automatically';
  }
 
  protected function execute($arguments = array(), $options = array())
  {
    $projectName = TaskUtils::getProjectName();

    $currentSchemaFile = TaskUtils::getSchemaFilePath();
    $revision = '';

    $currentRevisionNumber = TaskUtils::getCurrentRevisionNumber();
    $deltaFilesPath = TaskUtils::getDeltaPath();

    $deltaFileName = '';

    $svnRootPath = '';

    if (TaskUtils::isBranch())
    {
	// this is allright if we are in the trunk: we want to compare with the latest tag
	// For example, we may be ready for an upload and want to check the last things
	$revision = TaskUtils::getFirstRevisionBranch();
        $svnRootPath = TaskUtils::getWorkingBranchUri();

        $fakeProjectName = ($projectName == 'studentbeans') ? 'sbeans-symfony' : $projectName;

        preg_match("!svn://testbox(\.beans)?/projects/$fakeProjectName/branches/(.*)/webroot/.*!", $svnRootPath, $matches);
        $branchName = str_replace('/', '__', $matches[1]);
        $deltaFileName = $currentRevisionNumber . '_' . $branchName ;
    }
    else
    {
	// if we are on a branch, we want to compare with the initial schema of our branch.
	// That could help to write delta files
	$revision = TaskUtils::getLastDeployRevisionNumber();
        $svnRootPath = TaskUtils::getTrunkWebRootSvnUri();
        $deltaFileName = $currentRevisionNumber . '_trunk';
    }

    $propelSchemasFilepathPattern = sfConfig::get('sf_data_dir') . '/sql/*.sql';

    // svn cat svn://testbox/projects/sbeans-symfony/trunk/webroot/studentbeans/data/sql/lib.model.schema.sql@3193

    $oldSchema = '';
    $newSchema = '';

    foreach( glob($propelSchemasFilepathPattern) as $propelSchemaFile)
    {
      $newSchema .= file_get_contents($propelSchemaFile);

      $svnPath = $svnRootPath . '/data/sql/' . basename($propelSchemaFile) . '@' . $revision;

      $oldSchema .= shell_exec("svn cat $svnPath");
    }

    $oldSchemaPath  = "/tmp/oldSchema-$projectName-$currentRevisionNumber";
    $newSchemaPath  = "/tmp/newSchema-$projectName-$currentRevisionNumber";

    file_put_contents($oldSchemaPath, $oldSchema);
    file_put_contents($newSchemaPath, $newSchema);

    echo "+++++++++++ Creating delta file automatically ++++++++++++++\n";
    $outputFilePath = $deltaFilesPath . '/' . $deltaFileName;
    $nextgenPath = sfConfig::get('sf_root_dir') .  '/../../build/nextgen_mysql_diff';

    $doStatFilePath = "/tmp/doStat-$projectName-$currentRevisionNumber";
    $undoStatFilePath = "/tmp/undoStat-$projectName-$currentRevisionNumber";

    $nextgenDoCommand = "$nextgenPath/nextgen-mysql-diff.php PropelSchema $oldSchemaPath PropelSchema $newSchemaPath StandardFile $doStatFilePath";

    passthru($nextgenDoCommand);

    $nextgenUndoCommand = "$nextgenPath/nextgen-mysql-diff.php PropelSchema $newSchemaPath PropelSchema $oldSchemaPath StandardFile $undoStatFilePath";

    passthru($nextgenUndoCommand);

    $deltaContent = file_get_contents($doStatFilePath) . "\n\n--//@UNDO\n\n" . file_get_contents($undoStatFilePath);

    file_put_contents($outputFilePath, $deltaContent);

    echo "+++++++++++ Diffing the schemas visually ++++++++++++++\n";
    $this->checkMeldIsInstalled();
    shell_exec("meld $oldSchemaPath $newSchemaPath &");

    unlink($oldSchemaPath);
    unlink($newSchemaPath);
    unlink($doStatFilePath);
    unlink($undoStatFilePath);
  }

  private function checkMeldIsInstalled()
  {
    $o = shell_exec('meld --version');

    if (! preg_match('!meld .*!', $o))
    {
      throw new Exception('You need to have meld installed on your machine.');
    }

  }
}
?>