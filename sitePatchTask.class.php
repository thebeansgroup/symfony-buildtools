<?php

class sitePatchTask extends sfBaseTask
{

  protected function configure()
  {
    $this->detailedDescription = <<<EOF
The [site:patch|INFO] task applies a patch either on the staging or production areas on the production servers.
If an integer is passed as revisions, the patch is done against that revision and the previous one.
If an interval is passed (i.e.: 637:640), the patch is done against that interval.


EOF;

    parent::configure();

    $this->availableEnvironments = array('staging', 'prod');

    $availableEnvironmentsString = implode(', ', $this->availableEnvironments);

    $this->addArguments(array(
        new sfCommandArgument('revisions', sfCommandArgument::REQUIRED, "The revisions to patch against."),
    ));

    $this->addOptions(array(
        new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment (can be either staging or prod)'),
        new sfCommandOption('cert', null, sfCommandOption::PARAMETER_REQUIRED, 'The location for the certificates (they have to be called id_rsa and id_rsa.pub)')
    ));

    $this->namespace = 'site';
    $this->name = 'patch';
    $this->briefDescription = 'Applies a patch either on the staging or production areas on the production servers.';
  }

  protected function execute($arguments = array(), $options = array())
  {
    date_default_timezone_set('Europe/London'); // this kills a lot of annoying output
    $apacheUser = TaskUtils::getApacheUser();

    if (!function_exists('ssh2_connect'))
    {
      throw new Exception('You need to launch this as root:  yum install php-pecl-ssh2');
    }


    $env = $options['env'];
    $revisions = $arguments['revisions'];

    if (!in_array($env, $this->availableEnvironments))
    {
      throw new sfCommandException(sprintf("The environment $env is not available. Check the help page of this command."));
    }

    $revisionStart = 0;
    $revisionEnd = 0;
    if ($revisions)
    {
      if (!preg_match('!(\d+)(:(\d+))?!', $revisions, $matches))
      {
        throw new sfCommandException(sprintf("The environment $env is not available. Check the help page of this command."));
      }

      $revisionStart_ = $matches[1];

      if (isset($matches[3]))
      {
        $revisionStop_ = $matches[3];
      }

      //setting up the revisions
      if (is_numeric($revisions))
      {
        $revisionStart = $revisions - 1;
        $revisionEnd = $revisions;
      }
      else // that means $revisions is something like 100:112
      {
        $revisionStart = $revisionStart_;
        $revisionEnd = $revisionStop_;
      }

      if ($revisionStart > $revisionEnd)
      {
        throw new sfCommandException('The start revision must be higher than the end revision.');
      }
    }
    else // the revisions aren't been provided: use the latest revision of the branch
    {
      throw new sfCommandException(sprintf("You need to provide a revision note"));
      $revisionEnd = TaskUtils::getWorkingBranchLatestRelease();
      $revisionStart = $revisionEnd - 1;
    }

    $projectName = TaskUtils::getProjectName();
    
    // horrible however on aws we have moved the the correct name but on some other system we havent so we cannot yet change the getprojectname function 
    // in the taskutils
    if("sbeans-symfony" === $projectName)
    {
      $projectName = "studentbeans";
    }

    $branchUri = TaskUtils::getWorkingBranchUri();

    $patchUser = 'patch-production';
    if ($env == 'staging')
    {
      $patchUser = 'patch';
    }

    // generating the patch
    $patch = shell_exec("svn diff $branchUri -r $revisionStart:$revisionEnd");

    $this->log($patch);

    //$password = $this->ask('>>> Enter the password');
    $connection = ssh2_connect('deploy.studentbeans.com', 22, array('hostkey' => 'ssh-rsa'));
    if (ssh2_auth_pubkey_file($connection, 'ubuntu', "{$options['cert']}/id_rsa.pub", "{$options['cert']}/id_rsa"))
    {
      $this->log("Public Key Authentication Successful");
    }
    else
    {
      throw new Exception('Public Key Authentication Failed');
    }

    if (!$this->askConfirmation(">>> do you really want to apply this patch to $projectName:$env   (y/n)?", 'QUESTION', false))
    {
      throw new Exception('Task aborted.');
    }

    $patchTempFile = "/tmp/patch-$projectName-$env-$revisionStart-$revisionEnd-" . date('Ymd') . '-' . date('his') . '-' . mt_rand(0, 100);
    file_put_contents($patchTempFile, $patch);

    $patchBasedirStaging = ($env == 'staging') ? 'staging/' : '';
    $patchBasedir = '/var/www/html/' . $patchBasedirStaging . $projectName . '/';

    $this->log('uploading the patch');
    ssh2_scp_send($connection, $patchTempFile, $patchTempFile, 0777);

    $dryRunCommand = "cd $patchBasedir && patch -s -p0 --dry-run < $patchTempFile";
    $actualCommand = "cd $patchBasedir && patch -p0 < $patchTempFile";

    $this->log("trying to run on server: $dryRunCommand");

    // dry running (in silent mode)...
    $stream = ssh2_exec($connection, $dryRunCommand);
    stream_set_blocking($stream, true);
    $dryRunOutput = stream_get_contents($stream);

    if (!$dryRunOutput) // no error messages
    {
      // let's execute the command
      $stream = @ssh2_exec($connection, $actualCommand);
      stream_set_blocking($stream, true);
      $output = stream_get_contents($stream);
      $this->log($output);
    }
    else
    {
      // print error message
      $this->log($dryRunOutput);
      echo "\n\n\n Please send this to Vincent via email ($dryRunCommand):   $dryRunOutput \n\n\n";
      throw new Exception("some problem occurred. Patch couldn't be applied.");
    }

    unlink($patchTempFile);

    if ("prod" === $env)
    {
      if (!$this->askConfirmation(">>> Do you wish to sync the applied patch to the production servers? (y/n)?", 'QUESTION', false))
      {
        throw new Exception('Task aborted.');
      }
      $stream = ssh2_exec($connection, "sudo chown -R $apacheUser:$apacheUser $patchBasedir && sudo /usr/local/bin/rsync.sh");
      stream_set_blocking($stream, true);
      $this->log(stream_get_contents($stream));
    }

    $this->log("patch should have been applied successfully. Please check you can see your changes.");
  }

}

?>
