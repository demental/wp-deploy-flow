<?php

class WP_Deploy_Flow_Pusher {

  public function __construct($params)
  {
    $this->params = $params;
  }

  public function commands()
  {
    $commands = array();
    $this->_commands_for_database_dump($commands);

    if($this->params['ssh_db_host']) {
      $this->_commands_for_database_import_thru_ssh($commands);
    } else {
      $this->_commands_for_database_import_locally($commands);
    }

    $commands[]= array('rm dump.sql', true);

    $this->_commands_for_files( $commands );
    $this->_commands_post_push($commands);
    return $commands;
  }

  public function commands_for_files() {
    $commands = array();
    return $this->_commands_for_files($commands);

  }

  protected function _commands_for_files(&$commands) {
		extract( $this->params );

		$remote_path = $path . '/';
		$local_path = ABSPATH;
    $excludes = array_merge(
      $excludes,
      array(
        '.git',
        'wp-content/cache',
        'wp-content/_wpremote_backups',
        'wp-config.php',
      )
    );
    if(!$ssh_host) {
       // in case the destination env is in a subfolder of the source env, we exclude the relative path to the destination to avoid infinite loop
      $local_remote_path = realpath($remote_path);
      if($local_remote_path) {

        $local_path = realpath($local_path) . '/';
        $local_remote_path = str_replace($local_path . '/', '', $local_remote_path);
        $excludes[]= $local_remote_path;
        $remote_path = realpath($remote_path). '/';
      }
    }
    $excludes = array_reduce( $excludes, function($acc, $value) { $acc.= "--exclude \"$value\" "; return $acc; } );

		if ( $ssh_host ) {
      $command = "rsync -avz -e 'ssh -p $ssh_port' --chmod=Du=rwx,Dg=rx,Do=rx,Fu=rw,Fg=r,Fo=r $local_path $ssh_user@$ssh_host:$remote_path $excludes";
    } else {
      $command = "rsync -avz $local_path $remote_path $excludes";
    }
		$commands[]= array($command, true);
  }

  protected function _commands_post_push(&$commands) {
		extract( $this->params );
		$const = strtoupper( $env ) . '_POST_SCRIPT';
		if ( defined( $const ) ) {
			$subcommand = constant( $const );
			$commands[]= array("ssh $ssh_user@$ssh_host -p $ssh_port \"$subcommand\"", true);
		}
  }

  protected function _commands_for_database_import_thru_ssh(&$commands)
  {
		extract( $this->params );
		$commands[]= array( "scp -P $ssh_port dump.sql $ssh_db_user@$ssh_db_host:$ssh_db_path", true );
		$commands[]= array( "ssh $ssh_db_user@$ssh_db_host -p $ssh_port \"cd $ssh_db_path; mysql --user=$db_user --password=$db_password --host=$db_host $db_name < dump.sql; rm dump.sql\"", true );
  }

  protected function _commands_for_database_import_locally(&$commands)
  {
		extract( $this->params );
		$commands[]= array( "mysql --user=$db_user --password=$db_password --host=$db_host $db_name < dump.sql;", true );
  }

  protected function _commands_for_database_dump(&$commands)
  {
		extract( $this->params );

    $siteurl = get_option( 'siteurl' );
    $searchreplaces = array($siteurl => $url, untrailingslashit( ABSPATH ) => untrailingslashit( $path ));
    $commands = array(
      array( 'wp db export db_bk.sql', true )
    );
    foreach($searchreplaces as $search => $replace) {
      $commands[]= array( "wp search-replace $search $replace", true );
    }
    $commands[]= array( 'wp db dump dump.sql', true );
    $commands[]= array( 'wp db import db_bk.sql', true );
    $commands[]= array( 'rm db_bk.sql', true );
  }

}