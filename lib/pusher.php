<?php

class WP_Deploy_Flow_Pusher {
  private $commands = array();

  public function __construct($params)
  {
    $this->params = $params;
  }

  public function commands()
  {
    $this->_commands_for_database_dump();

    if($this->params['ssh_db_host']) {
      $this->_commands_for_database_import_thru_ssh();
    } else {
      $this->_commands_for_database_import_locally();
    }

    $this->command_for_files();

    $this->_command_post_push_if_exists();

    return $this->commands;
  }

  public function command_for_files() {

		$remote_path = $this->params['path'] . '/';
		$local_path = ABSPATH;
    $excludes = array_merge(
      $this->params['excludes'],
      array(
        '.git',
        'wp-content/cache',
        'wp-content/_wpremote_backups',
        'wp-config.php',
      )
    );
    if(!$this->params['ssh_host']) {
       // in case the destination env is in a subfolder of the source env, we exclude the relative path to the destination to avoid infinite loop
      $local_remote_path = realpath($remote_path);
      if($local_remote_path) {

        $local_path = realpath($local_path) . '/';
        $local_remote_path = str_replace($local_path . '/', '', $local_remote_path);
        $excludes[]= $local_remote_path;
        $remote_path = realpath($remote_path). '/';
      }
    }
    $excludes = array_reduce( $excludes, function($acc, $value) { $acc[]= "--exclude \"$value\""; return $acc; } );
    $excludes = implode(' ', $excludes);
		if ( $this->params['ssh_host'] ) {
      $command = "rsync -avz -e 'ssh{$this->_ssh_port_option()} --chmod=Du=rwx,Dg=rx,Do=rx,Fu=rw,Fg=r,Fo=r $local_path {$this->_ssh_uri()}:$remote_path $excludes";
    } else {
      $command= "rsync -avz $local_path $remote_path $excludes";
    }
    $this->commands[]= array($command, true);
    return $this->commands;
  }

  protected function _command_post_push_if_exists() {
    if($this->params['post_push_command']) $this->_command_post_push();
  }

  protected function _command_post_push()
  {
    if($this->params['ssh_host']) {
      $this->commands[]= array("ssh {$this->_ssh_uri()}{$this->_ssh_port_option()} \"{$this->params['post_push_command']}\"", true);
    } else {
      $this->commands[]= array($this->params['post_push_command'], true);
    }
  }

  protected function _commands_for_database_import_thru_ssh()
  {
		extract( $this->params );
    $this->commands[]= array( "scp -P $ssh_port dump.sql $ssh_db_user@$ssh_db_host:$ssh_db_path", true );
    $this->commands[]= array( "ssh $ssh_db_user@$ssh_db_host -p $ssh_db_port \"cd $ssh_db_path; mysql --user=$db_user --password=$db_password --host=$db_host $db_name < dump.sql; rm dump.sql\"", true );
    $this->commands[]= array('rm dump.sql', true);
  }

  protected function _commands_for_database_import_locally()
  {
		extract( $this->params );
    $this->commands[]= array( "mysql --user=$db_user --password=$db_password --host=$db_host $db_name < dump.sql;", true );
    $this->commands[]= array('rm dump.sql', true);
  }

  protected function _commands_for_database_dump()
  {
		extract( $this->params );

    $siteurl = get_option( 'siteurl' );
    $searchreplaces = array($siteurl => $url, untrailingslashit( ABSPATH ) => untrailingslashit( $path ));
    $this->commands[]= array( 'wp db export db_bk.sql', true );
    foreach($searchreplaces as $search => $replace) {
      $this->commands[]= array( "wp search-replace $search $replace", true );
    }
    $this->commands[]= array( 'wp db dump dump.sql', true );
    $this->commands[]= array( 'wp db import db_bk.sql', true );
    $this->commands[]= array( 'rm db_bk.sql', true );
  }

  protected function _ssh_uri()
  {
    return "{$this->params['ssh_user']}@{$this->params['ssh_host']}";
  }
  public function _ssh_port_option()
  {
    return $this->params['ssh_port'] ? " -p {$this->params['ssh_port']}" : "";
  }
}