<?php
/**
 * Allows developers to import db packages
 */
class siteImportDbPackageTask extends sfBaseTask
{
  protected function configure()
  {
    $this->detailedDescription = <<<EOF
The [import-db-package|INFO] task downloads a database package
from the vbox and imports it into the specified database.
EOF;

    parent::configure();

    $this->namespace = 'site';
    $this->name = 'import-db-package';
    $this->aliases = array('import-db-package');
    $this->briefDescription = 'Imports a database package into the specified db.';

    $this->addOptions(
      array(
        new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED,
          'The database to import the package into', 'dev')
      )
    );

    $this->addArguments(
      array(
        new sfCommandArgument('package', sfCommandArgument::REQUIRED,
          'The database package to import'),
      )
    );
  }
 
  protected function execute($arguments = array(), $options = array())
  {
    if (strtolower(trim($options['env'])) == 'prod')
    {
      throw new sfException("This task cannot be run on a production database");
    }

    echo "Downloading package {$arguments['package']}... ";

    $packageFile = rtrim($arguments['package'], '.tar.gz') . '.tar.gz';

    $destPath = "/tmp/$packageFile";

    if (!copy("http://vbox.beans/dbpackages/$packageFile", $destPath))
    {
      throw new sfException("Failed to download package $packageFile to $destPath");
    }

    echo "done\nImporting package... ";

    $dbInfo = TaskUtils::getDatabaseConnectionDetails('frontend', $options['env']);

    // import the package using shell pipes
    shell_exec("tar xf $destPath -O | mysql -u {$dbInfo['user']} -p{$dbInfo['password']} -h {$dbInfo['host']} {$dbInfo['name']}");

    echo "done\nCleaning up... ";
    unlink($destPath);

    echo "done\n+++++ Import complete. +++++\n";
  }
}
?>