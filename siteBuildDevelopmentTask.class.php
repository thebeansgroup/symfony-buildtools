<?php
class siteBuildDevelopmentTask extends sfBaseTask
{
  protected function configure()
  {
    $this->detailedDescription = <<<EOF
The [build-development|INFO] task:
_ calls site:setup dev (clears caches and fix permissions - project specific)
_ deleting the initial database content
_ calls propel:build-all-load --env=dev

IMPORTANT!
In order for this task to work, the user must be able to launch:
_ sudo /bin/chown
_ sudo symfony
without being asked for a password

EOF;

    parent::configure();

    $this->namespace = 'site';
    $this->name = 'build-development';
    $this->aliases = array('build-dev');
    $this->briefDescription = 'Builds the development environment.';
  }
 
  protected function execute($arguments = array(), $options = array())
  {
    ini_set('memory_limit', '300M');
    date_default_timezone_set('Europe/London'); // this kills a lot of annoying output
    
    // I need to get $dbInfo before any output because TaskUtils::getDatabaseConnectionDetails 
    // will try to set some cookies
    $dbInfo = TaskUtils::getDatabaseConnectionDetails('frontend', 'dev');
    
    $inDevelopment = preg_match('!/var/www/html/development/!', sfConfig::get('sf_root_dir'));
    if (!$inDevelopment)
    {
      throw new sfException(sprintf('This task can only be launched in the development area.'));
    }

    // this is very useful to fix the revision number of the working copy for reliable
    // delta files names
     echo "+++++ Updating to current revision +++++\n";
     exec("svn up");

    echo "+++++ Setting file permissions and clearing caches... +++++\n";
    $siteSetupTask = new siteSetupTask($this->dispatcher, $this->formatter);
    $siteSetupTask->run(array('dev'), array());
    
    echo "+++++ Deleting lock files, if any +++++\n";
    if (sfConfig::get('sf_data_dir'))
    {
      exec('rm ' . sfConfig::get('sf_data_dir') . '/lock_files/*');
    }
    
    // This seems to cause problems and it is not very useful anyway
    // as the db schema is in the schema.yml file anyway
    /*
    echo "+++++ Backing up the initial database content +++++\n";
    // we need to build the base classes in order to get the connection details
    $propelBuildModelTask = new sfPropelBuildModelTask($this->dispatcher, $this->formatter);
    $propelBuildModelTask->run(array(), array());
    $propelBuildFormsTask = new sfPropelBuildFormsTask($this->dispatcher, $this->formatter);
    $propelBuildFormsTask->run(array(), array());
    $propelBuildFiltersTask = new sfPropelBuildFiltersTask($this->dispatcher, $this->formatter);
    $propelBuildFiltersTask->run(array(), array());

    exec("mysqldump --add-drop-table --no-data --user={$dbInfo['user']} --password={$dbInfo['password']} --host={$dbInfo['host']} {$dbInfo['name']} 
          > /home/`whoami`/backupDevelopment`date +%s`.sql");
    */
    date_default_timezone_set('Europe/London');
    echo "+++++ Purging the database +++++\n";        
    TaskUtils::purgeDatabase($dbInfo, array('wp_', 'wp2_'));

    echo "+++++ Running propel:build-all-load --env=dev --no-confirmation... +++++\n";
    sfToolkit::clearGlob(sfConfig::get('sf_cache_dir') . DIRECTORY_SEPARATOR);
    $propelBuildAllLoadTask = new sfPropelBuildAllLoadTask($this->dispatcher, $this->formatter);
    $propelBuildAllLoadTask->run(array(), array('--env=dev', '--no-confirmation'));
    sfToolkit::clearGlob(sfConfig::get('sf_cache_dir') . DIRECTORY_SEPARATOR);
    
    echo "+++++ Compiling style.css from LESS... +++++\n";
    $this->runTask('lc', array(), array('--minify=false'));
    
    echo "+++++ Creating lock file... +++++\n";
    TaskUtils::createLockFile('builtDevelopment');

    echo "+++++ Development build complete. +++++\n";
  }
}
?>
