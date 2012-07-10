<?php
class sitePrepareTestingTask extends sfBaseTask
{
  protected function configure()
  {
    $this->detailedDescription = <<<EOF
checks delta files, prepares the testing environment, checks out the trunk in the testing area.

EOF;

    $this->addOption('ignoreWorkingCopyChanges', 'ignore-working-copy-changes');

    parent::configure();

    $this->namespace = 'site';
    $this->name = 'prepare-testing';
    $this->briefDescription = 'Prepares the testing environment. That is, it simply checks out the branch in the testing area.';
  }
 
  protected function execute($arguments = array(), $options = array())
  {
    ini_set('memory_limit', '320M');
    date_default_timezone_set('Europe/London'); // this kills a lot of annoying output
    $apacheUser = TaskUtils::getApacheUser();
    // I need to get $dbInfo before any output because TaskUtils::getDatabaseConnectionDetails 
    // will try to set some cookies
    $dbInfo = TaskUtils::getDatabaseConnectionDetails('frontend', 'dev');    
    
    $inDevelopment = preg_match('!^/var/www/html/development/!', sfConfig::get('sf_root_dir'));
    if (!$inDevelopment)
    {
      //throw new sfException(sprintf('This task can only be launched from the development area.'));
    } 

    $developmentWorkingCopyRootDirectory = realpath(sfConfig::get('sf_root_dir') . '/../..');
    $testingWorkingCopyRootDirectory = str_replace('/var/www/html/development/', '/var/www/html/testing/', $developmentWorkingCopyRootDirectory);

    $firstRevisionBranch = TaskUtils::getFirstRevisionBranch();

    echo "+++++ Checking for uncommitted files... +++++\n";
    $changedFiles = exec("svn st | grep '^M'");
    if($changedFiles && !$options['ignoreWorkingCopyChanges'])
    {
	throw new sfException("Your working copy is not clean, there are some modified files. Maybe you forgot to commit something, please double check. \n To bypass this limitation, rerun the task like this: ./symfony prepare-testing --ignore-working-copy-changes");
    }

    echo "+++++ Checking whether there are some delta files that haven't been committed... +++++\n";
    $deltaPath = TaskUtils::getDeltaPath();
    $uncommittedDeltas = exec("svn st $deltaPath");
    if ($uncommittedDeltas)
    {
        throw new sfException(sprintf("There are some delta files that haven't been committed."));
    }

    echo "+++++ Clearing the testing area... +++++\n";
    passthru("sudo /bin/chown -R $apacheUser:$apacheUser $testingWorkingCopyRootDirectory",
      $errorOccurredOnClearingTesting);
    passthru("sudo /bin/chmod -R 775 $testingWorkingCopyRootDirectory", $errorOccurredOnClearingTesting);
    passthru("rm -rf $testingWorkingCopyRootDirectory/*", $errorOccurredOnClearingTesting);
    if ($errorOccurredOnClearingTesting)
    {
      throw new sfException(sprintf('An error occurred while clearing the testing area.'));
    }

    //clearing SVN hidden files (the previous rm command spares them)
    exec("find $testingWorkingCopyRootDirectory -name '.s*' | xargs rm -rf");

    echo "+++++ Checking out the trunk in a testing area... +++++\n";
    // I'm using chdir because I need something like:
    // svn://testbox/projects/graduatebeans/trunk
    // rather than
    // svn://testbox/projects/graduatebeans/trunk/webroot/graduatebeans
    chdir($developmentWorkingCopyRootDirectory);
    
    $branchReporitoryRootURL = exec("svn info | head -n 2 | tail -n 1 | sed 's/^URL: //'");
    $trunkReporitoryRootURL = exec("svn info | head -n 3 | tail -n 1 | sed 's/^Repository Root: //'") . '/trunk';

    // the command to issue will be something like this:
    $commandForMergingNewFashion = "svn merge --reintegrate $branchReporitoryRootURL";
    $commandForMergingOldFashion = "svn merge -r$firstRevisionBranch:HEAD $branchReporitoryRootURL";
    
    // this command will issue something like:
    // svn co svn://testbox/projects/graduatebeans/trunk /var/www/html/testing/projects/graduatebeans/branch
    exec("svn co $trunkReporitoryRootURL $testingWorkingCopyRootDirectory");
    
    echo "\n\n+++++ TASK COMPLETED SUCCESSFULLY +++++\n\n";
    
    echo "\n\n+++++ What's next? +++++\n";
    echo "- Move to the testing area (that is a working copy of the trunk) (it will be something like this: /var/www/html/testing/projects/sbeans-symfony/branch)\n";
    echo "- Merge your branch into the trunk using one of these command: \n";
    echo "$commandForMergingNewFashion    or \n";
    echo "$commandForMergingOldFashion \n";
    echo "_ In the case you have a lot of useless tree conflicts run this command from the root of the project (it will delete all the mergeinfo files):  \n";
    echo 'svn propget svn:mergeinfo --depth=infinity | grep -v "^/" | grep -v "^\." | sed -e \'s/ - /\t/\' | cut -f1| xargs svn propdel svn:mergeinfo' . "\n";
    echo "- Run ./symfony site:build-testing \n";
    echo "- Test using a URL similar to this one:  http://testing.sb.devXXX.beans/frontend_testing.php \n";
    echo "- Commit to the trunk \n\n";
  }
}
?>
