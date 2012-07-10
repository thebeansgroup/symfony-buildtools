<?php
class siteRollbackTask extends sfBaseTask
{
  protected function configure()
  {
    $this->detailedDescription = <<<EOF
The [check-schema|INFO] task rollback to the previous "stable" version of the site if a deploy goes wrong. It must be launched from the "stable" version.

EOF;

    parent::configure();

    $this->namespace = 'site';
    $this->name = 'rollback';
    $this->aliases = array('rollback');
    $this->briefDescription = 'Rollback to the previous "stable" version of the site if a deploy goes wrong. It must be launched from the "stable" version.';
  }
 
  protected function execute($arguments = array(), $options = array())
  {
    $projectName = TaskUtils::getProjectName();
    $apacheUser = TaskUtils::getApacheUser();

    echo "+++++ updating symlink... +++++\n";
    $target = sfConfig::get('sf_root_dir');
    exec("sudo rm -f /var/www/html/$projectName &&
          sudo ln -s $target /var/www/html/$projectName &&
          sudo chown -h $apacheUser:$apacheUser /var/www/html/$projectName &&
          sudo chmod -R 775 /var/www/html/$projectName");

    exec("sudo /usr/local/bin/rsync.sh");

    echo "+++++ applying undo-statements... +++++\n";
    echo $undoStatements;
    $undoStatements = file_get_contents(sfConfig::get('sf_root_dir') . '/' . TaskUtils::getUndoDeltasFilePath($projectName, true));
    TaskUtils::applyCustomSQLWithoutReferentialIntegrity($undoStatements, 'frontend', 'prod');

    echo "+++++ clearing caches... +++++\n";
    exec("./symfony cc");
  }
}
?>