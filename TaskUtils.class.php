<?php

class TaskUtils
{
  /**
   * Returns the path for the temporary file where to store the lastest deploy revision number
   *
   * @return string
   */
  public static function getLastDeployRevisionNumberPath()
  {
    return sfConfig::get('sf_data_dir') . '/lastDeployRevisionNumber';
  }

  /**
   * Returns the path for the temporary file to store the current revision number
   * of the trunk
   *
   * @return string
   */
  public static function getCurrentRevisionNumberPath()
  {
    return sfConfig::get('sf_data_dir') . '/currentRevisionNumber';
  }


  public static function getTrunkWebRootSvnUri()
  {
    $projectName = self::getProjectName();
    $projectName2 = ($projectName == 'sbeans-symfony') ? 'studentbeans' : $projectName;
    return "svn://testbox.beans/projects/$projectName/trunk/webroot/$projectName2";
  }

  /**
   * Returns the path of the database schema file (whose extension may be xml or yml)
   *
   * @return string
   */
  public static function getSchemaFilePath()
  {
    $YMLSchemaFilepath = sfConfig::get('sf_root_dir') . "/config/schema.yml";
    $XMLSchemaFilepath = sfConfig::get('sf_root_dir') . "/config/schema.xml";
    return is_file($YMLSchemaFilepath) ? $YMLSchemaFilepath : $XMLSchemaFilepath;
  }

  /**
   * Returns the path where the revision notes are
   *
   * @return string
   */
  public static function getRevisionNotesDirPath()
  {
    return sfConfig::get('sf_data_dir') . "/revnotes/";
  }

  /*
   * Returns the path of the directory containing all delta files
   *
   * @return string
   */
  public static function getDeltaPath()
  {
    return sfConfig::get('sf_data_dir') . '/sql/deltas';
  }

  /**
   * Returns the revision number when the latest deploy took place
   *
   * @return integer the revision number when the latest deploy took place
   */
  public static function getLastDeployRevisionNumber()
  {
    $repositoryTagsURL = 'svn://testbox.beans/projects/' . TaskUtils::getProjectName() . '/tags/';
    $latestTagName = exec("svn list $repositoryTagsURL | grep -E '^REL'");
    $o = shell_exec("svn log $repositoryTagsURL/$latestTagName | head -20 | grep -E '^r[0-9]+ \|'| head -2 | tail -1 | awk '{ print $1 }' | sed 's/r//'");
    return (int)$o;
  }

  public static function getWorkingBranchUri()
  {
    return exec("svn info | grep 'URL:' | sed 's/URL: //'");
  }

  public static function getWorkingBranchLatestRelease()
  {
    $workingBranchUri = self::getWorkingBranchUri();
    return exec("svn info $workingBranchUri | grep 'Last Changed Rev:' | sed 's/Last Changed Rev: //'");
  }

  /**
   * Returns whether we are working on a branch, rather than on the trunk
   *
   * @return boolean
   */
  public static function isBranch()
  {
    if (exec("svn info | grep '/branches/'")) {
      return true;
    }
    else if (exec("svn info | grep '/trunk/'")) {
      return false;
    }
    else
    {
      throw new sfException("Couldn't tell whether we are in a branch or in the trunk");
    }
  }

  /**
   * Returns the current revision number for the given svn path
   *
   * @param string $repositoryTagsURL A URL to retrieve the latest revision number for
   * @return integer the revision number
   */
  public static function getCurrentRevisionNumber($repositoryTagsURL = '')
  {
    return exec("svn info $repositoryTagsURL | grep 'Last Changed Rev: ' | sed 's/Last Changed Rev: //'");
  }

  /**
   * Returns the SQL statement coming from all the delta files to consider
   *
   * @param integer $revisionStart the starting revision number we will start considering delta files
   * @param boolean $revert(=false) true to return the aggregation of the UNDO statements
   * @return string the SQL statement coming from all the delta files to consider
   */
  public static function getDeltaFilesQuery($revisionStart, $revert = false)
  {
    $sqlDeltaImporterPath = realpath(sfConfig::get('sf_root_dir') . '/../../build/deltatools');
    if (!is_dir($sqlDeltaImporterPath)) // this means we are on the live server
    {
      $sqlDeltaImporterPath = "/usr/local/bin";
    }
    $deltasPath = self::getDeltaPath();
    if (!is_file("$sqlDeltaImporterPath/SQLDeltaImporter.php")) // this means we are on the live server
    {
      throw new sfException(sprintf('Unable to locate SQLDeltaImporter on this path: ' . "$sqlDeltaImporterPath/SQLDeltaImporter.php"));
    }
    $revert = $revert ? '-r' : '';
    exec("$sqlDeltaImporterPath/SQLDeltaImporter.php -s $revisionStart $revert $deltasPath", $output);
    return implode("\n", $output);
  }

  /**
   * Returns a text containing all the revision notes added from the revision $revisionStart (excluded)
   *
   * @param integer $revisionStart the starting revision number we will start considering delta files
   * @param string $separator - the separator string between single revision notes
   * @return string
   */
  public static function getRevisionNotes($revisionStart, $separator = "\nXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX\n")
  {
    $revisionNoteFiles = glob(self::getRevisionNotesDirPath() . '*');

    $relevantRevisionNoteFiles = array();
    foreach ($revisionNoteFiles as $revisionNoteFile)
    {
      $revisionNoteFilename = basename($revisionNoteFile);

      if (preg_match('/^(\d+)_.*/', $revisionNoteFilename, $matches)) {
        if ($revisionNoteNumber = $matches[1]) {
          if ($revisionNoteNumber > $revisionStart) {
            $relevantRevisionNoteFiles[$revisionNoteNumber] = $revisionNoteFilename;
          }
        }
      }
    }
    ksort($relevantRevisionNoteFiles);

    $revisionNotesContent = '';
    foreach ($relevantRevisionNoteFiles as $file)
    {
      $revisionNotesContent .= file_get_contents(self::getRevisionNotesDirPath() . $file) . $separator;
    }
    return $revisionNotesContent;
  }

  /**
   * Applies a custom SQL statement to the database defined by $environment
   *
   * @param string $SQL the SQL statement to apply
   * @param string $application the name of a valid application (i.e.: frontend) - used to get a Propel connection
   * @param string $environment the environment we want to apply the deltas to
   */
  public static function applyCustomSQL($SQL, $application, $environment)
  {
    if ($SQL) // applying the delta files, if any
    {
      $configuration = ProjectConfiguration::getApplicationConfiguration($application, $environment, true);
      sfContext::createInstance($configuration);
      $databaseManager = new sfDatabaseManager($configuration);
      $con = Propel::getConnection();
      $stmt = $con->prepare($SQL);
      $rs = $stmt->execute();

      if ($rs === false) {
        echo "\nFailed to apply SQL to application '$application' and
environment '$environment'.
-- FAILED SQL START --
$SQL
-- FAILED SQL END --
(Failed to apply above SQL to application '$application' and
environment '$environment'.)
Error was:
";
        print_r($stmt->errorInfo());

        print_r($con->errorInfo());
      }
    }
  }

  /**
   * Applies a custom SQL statement to the database defined by $environment after
   * temporarily disabling the referential Integrity
   *
   * @param string $SQL the SQL statement to apply
   * @param string $application the name of a valid application (i.e.: frontend) - used to get a Propel connection
   * @param string $environment the environment we want to apply the deltas to
   */
  public static function applyCustomSQLWithoutReferentialIntegrity($SQL, $application, $environment)
  {
    $SQL = 'SET FOREIGN_KEY_CHECKS=0; ' . $SQL . ' SET FOREIGN_KEY_CHECKS=1;';

    return self::applyCustomSQL($SQL, $application, $environment);
  }

  /**
   * Returns an associative array with the details of the database connection
   * You need to call it before any output because it will try to set some cookies
   *
   * @param string $application the name of a valid application (i.e.: frontend) - used to get a Propel connection
   * @param string $environment the environment we want to apply the deltas to
   * @return array(of strings) the keys are: name, host, user, password
   */
  public static function getDatabaseConnectionDetails($application, $environment)
  {
    $databaseConnectionDetails = array();
    $configuration = ProjectConfiguration::getApplicationConfiguration($application, $environment, true);
    // this line seems to create problems
    // sfContext::createInstance($configuration);
    $databaseManager = new sfDatabaseManager($configuration);
    $databaseConfig = sfPropelDatabase::getConfiguration();
    $databaseDsn = $databaseConfig['propel']['datasources']['propel']['connection']['dsn'];
    if (preg_match('/dbname=([^;]+)/', $databaseDsn, $matches)) {
      $databaseConnectionDetails['name'] = $matches[1];
    }
    else
    {
      throw new sfException(sprintf('Unable to get the database name.'));
    }

    if (preg_match('/host=([^;]+)/', $databaseDsn, $matches)) {
      $databaseConnectionDetails['host'] = $matches[1];
    }
    else
    {
      throw new sfException(sprintf('Unable to get the host for the database.'));
    }

    $user = $databaseConfig['propel']['datasources']['propel']['connection']['user'];
    if ($user) {
      $databaseConnectionDetails['user'] = $user;
    }
    else
    {
      throw new sfException(sprintf('Unable to get the username for the database.'));
    }

    $password = $databaseConfig['propel']['datasources']['propel']['connection']['password'];
    if ($password) {
      $databaseConnectionDetails['password'] = $password;
    }
    else
    {
      throw new sfException(sprintf('Unable to get the username for the database.'));
    }
    return $databaseConnectionDetails;
  }

  /**
   * Returns whether all the tests (unit and function) pass
   *
   * @param string &$testsOutput - by reference, contains the output of the test:all task
   * @return boolean - true if all the tests (unit and function) pass, false otherwise
   */
  public static function areTestsFailing(&$testsOutput)
  {
    $currentDir = dirname(__FILE__);
    $symfonyDir = realpath($currentDir . '/../../../');

    exec("$symfonyDir/symfony test:all", $output);

    $testsOutput = implode("\n", $output) . "\n";

    // if the tests are successful, the next to last line will show:
    // All tests successful.
    $successIndex = count($output) - 2;
    if (strpos($output[$successIndex], 'All tests successful.')) {
      return false;
    }
    else
    {
      return true;
    }
  }

  /**
   * Returns the codename of the current project, e.g. graduatebeans, sbeans-symfony
   *
   * @param boolean $withFix (=true) whether to apply the fix to counterbalance
   *                the fact the repo for studentbeans is called sbeans-symfony
   * @return string
   */
  public static function getProjectName($withFix = true)
  {
    // work out the project name from the file system - this means we can't
    // move this file though unfortunately
    $projectName = basename(realpath(dirname(__FILE__) . '/../../../'));

    if (substr($projectName, 0, 2) == '20') // that means we are in one of the release directories
    {
      $projectName = basename(realpath(dirname(__FILE__) . '/../../../../'));
    }

    //////////////////////////////////////////////////////////////////////////////////////////////
    //// START - little patch while waiting sbeans-symfony will become studentbeans in the repo ///
    //////////////////////////////////////////////////////////////////////////////////////////////
    if ($withFix) {
      if (stripos(getcwd(), 'sbeans-symfony') !== FALSE) {
        $projectName = 'sbeans-symfony';
      }
    }
    //////////////////////////////////////////////////////////////////////////////////////////////
    //// END - little patch while waiting sbeans-symfony will become studentbeans in the repo ////
    //////////////////////////////////////////////////////////////////////////////////////////////

    return $projectName;
  }


  /**
   * Returns the path of the file where to save the database schema before any change done in the branch
   *
   * @return string
   */
  //   public static function getInitialSchemaFilePath()
  //   {
  //     $initialSchemasDirectory = sfConfig::get('sf_data_dir') . "/sql/initialBranchDBSchemas";
  //     $firstRevisionBranch = self::getFirstRevisionBranch();
  //
  //     return $initialSchemasDirectory . '/' . $firstRevisionBranch;
  //   }

  /**
   * Returns the first revision of branch we are working on (that could be the trunk itself)
   *
   * @return integer
   */
  public static function getFirstRevisionBranch()
  {
    $firstRevisionBranch = (int)exec("svn log --stop-on-copy ../.. | grep '^r[0-9]* | ' | awk '{ print $1 }' | sed 's/r//' | tail -1");

    if ($firstRevisionBranch == 1) // we are in the trunk
    {
      return (self::getLastDeployRevisionNumber() + 1);
    }
    return $firstRevisionBranch;
  }

  /**
   * Creates the lock file $filename in the default location (/misc/lock_files)
   *
   * @param string $filename
   */
  public static function createLockFile($filename)
  {
    $lockFilesDirectory = sfConfig::get('sf_data_dir') . "/lock_files/";
    if (!is_dir($lockFilesDirectory)) {
      throw new sfException(sprintf("You have to create the directory $lockFilesDirectory and exclude its content from the repository."));
    }
    exec('touch ' . $lockFilesDirectory . $filename);
  }


  /**
   * Checks whether the lock file $filename exists
   *
   * @param string $filename
   * @return boolean
   */
  public static function lockFileExist($filename)
  {
    $lockFilesDirectory = sfConfig::get('sf_data_dir') . "/lock_files/";
    if (!is_dir($lockFilesDirectory)) {
      throw new sfException(sprintf("You have to create the directory $lockFilesDirectory and exclude its content from the repository."));
    }
    return is_file($lockFilesDirectory . $filename);
  }

  /**
   * Returns the URL of the schema for the latest tag
   *
   * @return string
   */
  public static function getLatestTagSchemaUrl()
  {
    $currentSchemaFile = TaskUtils::getSchemaFilePath();

    preg_match('!.*/(schema.*$)!', $currentSchemaFile, $matches);
    $schemaFilename = $matches[1]; // i.e.: schema.xml

    return self::getLatestTagUrl() . '/config/' . $schemaFilename;
  }

  /**
   * Returns the URL of the initial schema of the working branch
   *
   * @return string
   */
  public static function getInitialSchemaUrlOfThisBranch()
  {
    if (!self::isBranch()) {
      return '';
    }
    $currentSchemaFile = TaskUtils::getSchemaFilePath();

    preg_match('!.*/(schema.*$)!', $currentSchemaFile, $matches);
    $schemaFilename = $matches[1]; // i.e.: schema.xml

    $branchSymfonyRootURL = exec("svn info | grep 'URL:' | awk {'print $2'}");

    return $branchSymfonyRootURL . '/config/' . $schemaFilename . '@' . TaskUtils::getFirstRevisionBranch();
  }

  /**
   * Returns the URLs of the fixtures for the latest tag
   *
   * @return array This array will hold all the lates
   */
  public static function getLatestFixtureFiles()
  {
    exec("svn ls " . self::getFixtureDirPath(), $fixtureList);
    return $fixtureList;
  }

  /**
   * Returns the URLs of the fixtures for the latest tag
   *
   * @return array This array will hold all the lates
   */
  public static function getLatestTagFixtureFiles()
  {
    exec("svn ls " . self::getLatestTagUrl() . "/data/fixtures/", $fixtureList);
    return $fixtureList;
  }

  /*
   * Get the lastes tag
   */
  public static function getLatestTag()
  {
    return exec("svn list svn://testbox.beans/projects/" . self::getProjectName() . "/tags/ | grep -E '^REL'");
  }

  /*
   * Get the URL to the latest tag
   */
  public static function getLatestTagUrl()
  {
    return "svn://testbox.beans/projects/" . self::getProjectName() . "/tags/" . self::getLatestTag() . 'webroot/' . self::getProjectName(false);
  }

  /*
   * Deletes the content of a database
   * 
   * @param array $dbInfo - associative array with these db details/keys:  user, password, host, name
   * @param array $tablePrefixesToSpare - you can spare tables starting with the prefixes in this array
   */
  public static function purgeDatabase($dbInfo, array $tablePrefixesToSpare = null)
  {
    $tablePrefixesToSpareCommand = "";
    if (is_array($tablePrefixesToSpare)) {
      // in this case, we want to delete all the tables but the ones having one of the prefixes
      // contained in the array $tablePrefixesToSpare

      // here $tablePrefixesToSpare can contain elements like:  wp_
      array_walk($tablePrefixesToSpare, function (&$item, $key)
      {
        $item = "^DROP TABLE IF EXISTS `" . $item;
      });
      // here $tablePrefixesToSpare will contain elements like: ^DROP TABLE IF EXISTS `wp_

      $tablePrefixesToSpareString = implode('|', $tablePrefixesToSpare);

      $tablePrefixesToSpareCommand = " | grep -vP '($tablePrefixesToSpareString)'";
    }

    $commandToGetDropStatements = "mysqldump -u{$dbInfo['user']} -p{$dbInfo['password']} " .
        " --host={$dbInfo['host']} --add-drop-table --no-data {$dbInfo['name']} " .
        " | grep ^DROP " . $tablePrefixesToSpareCommand;

    $dropStatements = shell_exec($commandToGetDropStatements);

    // we need to temporarily deactivate the foreign key integrity check in order to delete the tables just in alphabetical order
    $sql = "SET FOREIGN_KEY_CHECKS=0;" . $dropStatements . "SET FOREIGN_KEY_CHECKS=1;";

    $sql = str_replace('`', '\`', $sql);

    exec("mysql -u{$dbInfo['user']} -p{$dbInfo['password']} --host={$dbInfo['host']} {$dbInfo['name']} -e \"$sql\"");
  }

  /*
   * Moves old releases from the releases directory to the old-releases directory and purges the
   * old-releases directory from very old releases
   *
   * @param string $projectName
   * @param boolean $dryRun
   */
  public static function removeInactiveReleases($projectName, $dryRun = false)
  {
    $minNumberOfOldReleasesToKeep = 4;

    if (!$projectName) {
      throw new InvalidArgumentException('You need to pass a projectName');
    }

    // removing the oldest release in the old-releases directory
    if ($oldReleasesPath = self::getOldReleasesDirectoryPath($projectName)) {
      $path = $oldReleasesPath . '/*';
      $numberOfOldReleases = count(glob($path, GLOB_ONLYDIR));

      if ($numberOfOldReleases > $minNumberOfOldReleasesToKeep) {
        $oldestSubdirectory = shell_exec('ls -lr ' . $oldReleasesPath . ' | tail -1 | awk \'{print $9}\'');
        if ($oldReleasesPath && $oldestSubdirectory) {
          $oldestSubdirectoryPath = str_replace("\n", '', "$oldReleasesPath/$oldestSubdirectory");

          if ((strlen($oldestSubdirectoryPath) > 0) && (strpos($oldestSubdirectoryPath, self::getOldReleasesDirectoryPath($projectName)) === 0)) {
            echo "Debug - removing old release: $oldestSubdirectoryPath\n";
            if (!$dryRun) {
              exec("rm -rf --preserve-root $oldestSubdirectoryPath");
            }
          }
          else
          {
            throw new Exception("There is a problem with the oldestSubdirectoryPath variable.");
          }
        }
      }
    }

    // getting all the releases paths
    $releases = glob(self::getReleasesDirectoryPath($projectName) . '/*', GLOB_ONLYDIR);


    // some debug
    echo "Debug - current release: " . self::getCurrentReleasePath($projectName) . " \n";

    // getting all the releases paths but the one that is currently symlinked
    $oldReleases = self::removeElementFromArray(self::getCurrentReleasePath($projectName), $releases);

    foreach ($oldReleases as $oldRelease)
    {
      if ($oldRelease == self::getCurrentReleasePath($projectName)) {
        throw new Exception("Shouldn't move the current release ($oldRelease) to the old releases directory.");
      }

      echo "Debug - moving $oldRelease to the old releases pool\n";

      if (!$dryRun) {
        exec("mv $oldRelease " . self::getOldReleasesDirectoryPath($projectName) . '/');
      }
    }
  }

  /*
   * Gets the path to the current release of the project
   * (the one having the symlink pointed to)
   *
   * @param string $projectName
   * @param boolean $onlyDirectoryName (=false)
   * @return string
   */
  public static function getCurrentReleasePath($projectName, $onlyDirectoryName = false)
  {
    $linkDestination = readlink('/var/www/html/' . $projectName);
    $ret = '';

    if (!(strlen($linkDestination) > 0)) {
      throw new Exception("Couldn't retrieve the current release path.");
    }

    if (!$onlyDirectoryName) {
      $ret = $linkDestination;
    }
    else
    {
      $linkDestinationParts = explode('/', $linkDestination);
      $ret = end($linkDestinationParts);
    }

    // removing trailing slash, if any.
    if (substr($ret, -1) == '/') {
      $ret = substr($ret, 0, -1);
    }

    return $ret;
  }

  /*
   * @param string $projectName
   * @return string
   */
  public static function getReleasesDirectoryPath($projectName)
  {
    return '/var/www/html/releases/' . $projectName;
  }

  /*
   * @param string $projectName
   * @return string
   */
  public static function getOldReleasesDirectoryPath($projectName)
  {
    return '/var/www/html/releases/old-releases/' . $projectName;
  }

  /*
   * @param string $projectName
   * @param bool $relative
   * @return string
   */
  public static function getUndoDeltasFilePath($projectName, $relative = false)
  {
    if ($relative) {
      return 'data/undo.sql';
    }

    return "/var/www/html/$projectName/data/undo.sql";
  }

  /*
   * @param mixed $element - the element to remove
   * @param array $array
   * @return array
   */
  private static function removeElementFromArray($element, $array)
  {
    foreach ($array as $key => $value)
    {
      if ($array[$key] == $element) {
        unset($array[$key]);
      }
    }
    return $array;
  }

  /*
   * Checks we are in staging otherwise it throws an exception
   *
   * @return bool
   */
  public static function checkInStaging()
  {
    $inStaging = preg_match('!^/var/www/html/staging/!', sfConfig::get('sf_root_dir'));
    if (!$inStaging) {
      throw new sfException(sprintf('This task can only be launched from a staging directory.'));
    }
    return true;
  }

  /**
   * @static
   * @return string
   */
  public static function getOS()
  {
    return PHP_OS;
  }

  /**
   * @static
   * @return string
   */
  public static function getLinuxDist()
  {
    $release = `cat /etc/*-release`;

    if (stripos($release, 'ubuntu')) {
      return 'Ubuntu';
    }
    elseif (stripos($release, 'Fedora')) {
      return 'Fedora';
    }

    return 'Other';
  }

  /**
   * @static
   * @return string
   */
  public static function getApacheUser()
  {
    $os = self::getOS();
    if (strcasecmp($os, 'Linux') == 0) {
      $os = self::getLinuxDist();
    }
    switch ($os)
    {
      case 'Darwin':
        return '_www';
        break;
      case 'Fedora':
      case 'Ubuntu':
        return 'www-data';
        break;
      default:
        return 'apache';
    }
  }
}