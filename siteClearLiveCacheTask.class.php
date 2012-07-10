<?php
class siteClearLiveCacheTask extends sfBaseTask
{
  protected function configure()
  {
    $this->detailedDescription = <<<EOF
The [check-schema|INFO] task rollback to the previous "stable" version of the site if a deploy goes wrong. It must be launched from the "stable" version.

EOF;

    parent::configure();


    $this->availableEnvironments = array('staging', 'prod');

    $availableEnvironmentsString = implode(', ', $this->availableEnvironments);

    $this->addOptions(array(
      new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment (can be either staging or prod)')
    ));


    $this->namespace = 'site';
    $this->name = 'live-cc';
    $this->aliases = array('live-cc');
    $this->briefDescription = 'Rollback to the previous "stable" version of the site if a deploy goes wrong. It must be launched from the "stable" version.';
  }
 
  protected function execute($arguments = array(), $options = array())
  {
    $env = $options['env'];

    if (!in_array($env, $this->availableEnvironments))
    {
      throw new sfCommandException(sprintf("The environment $env is not available. Check the help page of this command."));
    }

    if (!function_exists('ssh2_connect'))
    {
        throw new Exception('You need to launch this as root:  yum install php-pecl-ssh2');
    }
    
    $projectName = TaskUtils::getProjectName();


    $patchUser = 'patch-production';
    if ($env == 'staging')
    {
        $patchUser = 'patch';
    }
    $password = $this->ask('>>> Enter the password');
    $sshConnection = ssh2_connect('web1.beans', 22);
    if (! ssh2_auth_password($sshConnection, $patchUser, $password)) {
      throw new Exception('Authentication Failed...');
    }

    $stagingCommandPart = $env == 'staging' ? "-h 'env:staging'" : '';

    $humptyCommandTemplate = "/usr/local/humpty/humpty -p $projectName -a [ACTION] $stagingCommandPart";

    $humptyActions = array('symfony-clear-minify-cache', 'symfony-clear-project-autoload-cache', 'symfony-clear-cache');

    foreach($humptyActions as $humptyAction)
    {
      $humptyCommand = str_replace('[ACTION]', $humptyAction, $humptyCommandTemplate);

      $this->log("Executing $humptyCommand");

      $stream = ssh2_exec($sshConnection, $humptyCommand);
      stream_set_blocking($stream, true);
      $this->log(stream_get_contents($stream));
    }
  }
}
?>
