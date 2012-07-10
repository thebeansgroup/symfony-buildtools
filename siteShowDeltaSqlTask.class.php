<?php
class siteShowDeltaSqlTask extends sfBaseTask
{
  protected function configure()
  {
    $this->detailedDescription = <<<EOF
The [show-delta-sql|INFO] task displays the SQL statements that will be applied as a result of the aggregation of the delta files

EOF;

    parent::configure();

    $this->namespace = 'site';
    $this->name = 'show-delta-sql';
    $this->briefDescription = 'Displays the SQL statements that will be applied as a result of the aggregation of the delta files';
  }
 
  protected function execute($arguments = array(), $options = array())
  {
    $latestTagRevision = TaskUtils::getLastDeployRevisionNumber();
    echo "Previous deploy revision number: $latestTagRevision\n\n";
    
    echo "+++++ Delta statements... +++++\n";    
    $SQLFromDeltas = TaskUtils::getDeltaFilesQuery($latestTagRevision);
    echo $SQLFromDeltas;
    
    echo "\n\n";
    
    echo "+++++ Undo statements... +++++\n";    
    $SQLFromUndos = TaskUtils::getDeltaFilesQuery($latestTagRevision, true);
    echo $SQLFromUndos;

    echo "\n\n";
  }
}
?>