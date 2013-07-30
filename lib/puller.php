<?php

class WP_Deploy_Flow_Puller {

  public function __construct($params)
  {
    $this->params = $params;
  }

  public function commands()
  {
    $commands = array();

    if($this->params['ssh_db_host']) {
      $this->_commands_for_database_import_thru_ssh($commands);
    } else {
      $this->_commands_for_database_import_locally($commands);
    }
    $this->_commands_for_database_dump($commands);

    $commands[]= array('rm dump.sql', true);

    $this->_commands_for_files( $commands );
    return $commands;
  }

  public function commands_for_files() {
    $commands = array();
    return $this->_commands_for_files($commands);

  }

  protected function _commands_for_files(&$commands) {
		extract( $this->params );

    $dir = wp_upload_dir();
    $dist_path  = constant( WP_Deploy_Flow_Command::config_constant( 'path' ) ) . '/';
    $remote_path = $dist_path;
    $local_path = ABSPATH;

    $excludes = array(
      '.git',
      'wp-content/cache',
      'wp-content/_wpremote_backups',
      'wp-config.php',
    );


    if(!$ssh_host) {
       // in case the source env is in a subfolder of the destination env, we exclude the relative path to the source to avoid infinite loop
      $remote_local_path = realpath($local_path);
      if($remote_local_path) {
        $remote_path = realpath($remote_path);
        $remote_local_path = str_replace($remote_path . '/', '', $remote_local_path);
        $excludes[]= $remote_locale_path;
      }
    }
    $excludes = array_reduce( $excludes, function($acc, $value) { $acc.= "--exclude \"$value\" "; return $acc; } );

    if ( $ssh_host ) {
      $commands[]= array("rsync -avz -e 'ssh -p $ssh_port' $ssh_user@$ssh_host:$remote_path $local_path $excludes", true);
    } else {
      $commands[]= array("rsync -avz $remote_path $local_path $excludes", true);
    }
  }

  protected function _commands_for_database_import_thru_ssh(&$commands)
  {
		extract( $this->params );
    $host = $db_host . ':' . $db_port;

    $wpdb = new wpdb( $db_user, $db_password, $db_name, $host );
    $path = ABSPATH;
    $url = get_bloginfo( 'url' );
    $dist_path  = constant( WP_Deploy_Flow_Command::config_constant( 'path' ) ) . '/';
    $commands[]= array("ssh $ssh_user@$ssh_host -p $ssh_port \"cd $dist_path;wp migrate to $path $url dump.sql\" && scp $ssh_user@$ssh_host:$dist_path/dump.sql .", true);
  }

  protected function _commands_for_database_import_locally(&$commands)
  {
		extract( $this->params );

    $host = $db_host . ':' . $db_port;
    $wpdb = new wpdb( $db_user, $db_password, $db_name, $host );
    $path = ABSPATH;
    $url = get_bloginfo( 'url' );
    $dist_path  = constant( WP_Deploy_Flow_Command::config_constant( 'path' ) ) . '/';
    $commands[]= array("wp migrate to $path $url dump.sql", true);
  }

  protected function _commands_for_database_dump(&$commands) {
    $commands[]= array('wp db import dump.sql', true);
  }
}

//
// public function pull( $args = array(), $flags = array() ) {
//
//   $host = $db_host . ':' . $db_port;
//
//   $wpdb = new wpdb( $db_user, $db_password, $db_name, $host );
//   $path = ABSPATH;
//   $url = get_bloginfo( 'url' );
//   $dist_path  = constant( self::config_constant( 'path' ) ) . '/';
//   $command = "ssh $ssh_user@$ssh_host -p $ssh_port \"cd $dist_path;wp migrate to $path $url dump.sql\" && scp $ssh_user@$ssh_host:$dist_path/dump.sql .";
//   WP_CLI::launch( $command );
//   WP_CLI::launch( 'wp db import dump.sql' );
//   self::pull_files( $args );
// }
//
// public function pull_files( $args = array() ) {
//   $this->params = self::_prepare_and_extract( $args );
//   $this->flags = $flags;
//   extract($this->params);
//   $commands = array();
//   $const = strtoupper( ENVIRONMENT ) . '_LOCKED';
//   if ( constant( $const ) === true ) {
//     return WP_CLI::error( ENVIRONMENT . ' env is locked, you can not pull to your local copy' );
//   }
//
//   $host = $db_host.':'.$db_port;
//
//   if ( $ssh_host ) {
//     $dir = wp_upload_dir();
//     $dist_path  = constant( self::config_constant( 'path' ) ) . '/';
//     $remote_path = $dist_path;
//     $local_path = ABSPATH;
//
//     WP_CLI::line( sprintf( 'Running rsync from %s:%s to %s', $ssh_host, $remote_path, $local_path ) );
//     $com = sprintf( "rsync -avz -e 'ssh -p %s' %s@%s:%s %s  --delete --exclude '.git' --exclude 'wp-content/cache' --exclude 'wp-content/_wpremote_backups' --exclude 'wp-config.php'", $ssh_port, $ssh_user, $ssh_host, $remote_path, $local_path );
//     WP_CLI::line( $com );
//     WP_CLI::launch( $com );
//   }
//
// }
