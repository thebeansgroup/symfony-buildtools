<?php

/**
 * Allows developers to import db packages
 */
class siteGetFbStatsTask extends sfBaseTask
{

  private $facebook;

  protected function configure()
  {
    $this->detailedDescription = <<<EOF
The [import-db-package|INFO] task downloads a database package
from the vbox and imports it into the specified database.
EOF;

    parent::configure();

    $this->namespace = 'site';
    $this->name = 'get-fb-stats';
    $this->aliases = array('get-fb-stats');
    $this->briefDescription = 'gets the stats for a fb like link';

    $this->addArguments(
            array(
                new sfCommandArgument('fb-link', sfCommandArgument::REQUIRED,
                        'The link for which to get the facebook stats'),
            )
    );
  }

  protected function execute($arguments = array(), $options = array())
  {
    $objectUrl = $arguments['fb-link'];

    $this->facebook = new Facebook(array(
                'appId' => sfConfig::get('app_facebook_comment_app_id'),
                'secret' => sfConfig::get('app_facebook_comment_secret')
            ));

    echo "Getting FB stats for link $objectUrl... ", PHP_EOL;
    $availableColumns = array('normalized_url', 'share_count', 'like_count', 'comment_count', 'total_count', 'click_count');
    $columnsString = implode(', ', $availableColumns);
    $stats = $this->facebook->api(
                    array(
                        "method" => "fql.query",
                        'query' => sprintf("SELECT %s FROM link_stat WHERE url='%s'", $columnsString, $objectUrl)
                    )
    );
    // getting the right array
    $stats = $stats[0];
    echo "Shares: {$stats['share_count']}, Likes: {$stats['like_count']}, Comments: {$stats['comment_count']}, Total: {$stats['total_count']}", PHP_EOL;
  }

}

?>