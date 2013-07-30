<?php
WP_CLI::add_command( 'deploy', 'WP_Deploy_Flow_Command' );

/**
 * deploys
 *
 * @package wp-deploy-flow
 * @author Arnaud Sellenet
 */
class WP_Deploy_Flow_Command extends WP_CLI_Command {

	protected static $_env;
	/**
   * Push local to remote
   *
   * @synopsis <environment> [--dry-run]
   */
	public function push( $args = array(), $flags = array() ) {
    $this->args = $args;
    $this->flags = $flags;

    $commands = array();

		extract( self::_prepare_and_extract( $args, false ) );
		if ( $locked === true ) {
			WP_CLI::error( "$env environment is locked, you cannot push to it" );
			return;
		}

    $this->commands_for_database_dump($args, $commands);

    if($ssh_db_host) {
      $this->commands_for_database_import_thru_ssh($args, $commands);
    } else {
      $this->commands_for_database_import_locally($args, $commands);
    }

    $commands[]= array('rm dump.sql', true);

    $this->commands_for_pushing_files( $args, $commands );
    $this->commands_post_push($args, $commands);
    $this->_execute_commands($commands);
	}

	public function push_files( $args = array() ) {
		extract( self::_prepare_and_extract( $args, false ) );
		if ( $locked === true ) {
			WP_CLI::error( "$env environment is locked, you cannot push to it" );
			return;
		}

    $commands = array();

    $this->commands_for_pushing_files( $args, $commands );

    $this->_execute_commands($commands, $args);
	}

  protected function commands_for_pushing_files($args, &$commands) {
		extract( self::_prepare_and_extract( $args, false ) );

		$dir = wp_upload_dir();
		$remote_path = $path . '/';
		$local_path = ABSPATH;
    $excludes = array(
      '.git',
      'wp-content/cache',
      'wp-content/_wpremote_backups',
      'wp-config.php',
    );
    if(!$ssh_host) {
       // in case the destination env is in a subfolder of the source env, we exclude the relative path to the destination to avoid infinite loop
      $local_remote_path = realpath($remote_path);
      if($local_remote_path) {

        $local_path = realpath($local_path);
        $local_remote_path = str_replace($local_path . '/', '', $local_remote_path);
        $excludes[]= $local_remote_path;
      }
    }
    $excludes = array_reduce( $excludes, function($acc, $value) { $acc.= "--exclude \"$value\" "; return $acc; } );

		if ( $ssh_host ) {
      $command = "rsync -avz -e 'ssh -p $ssh_port' $local_path $ssh_user@$ssh_host:$remote_path $excludes";
    } else {
      $command = "rsync -avz $local_path $remote_path $excludes";
    }
		$commands[]= array($command, true);
  }

  protected function commands_post_push($args, &$commands) {
		extract( self::_prepare_and_extract( $args, false ) );
		$const = strtoupper( $env ) . '_POST_SCRIPT';
		if ( defined( $const ) ) {
			$subcommand = constant( $const );
			$command = "ssh $ssh_user@$ssh_host -p $ssh_port \"$subcommand\"";
			WP_CLI::line( $command );
			WP_CLI::launch( $command );
		}
  }

  protected function commands_for_database_import_thru_ssh($args, &$commands)
  {
		extract( self::_prepare_and_extract( $args, false ) );
		$commands[]= array( "scp -P $ssh_port dump.sql $ssh_db_user@$ssh_db_host:$ssh_db_path", true );
		$commands[]= array( "ssh $ssh_db_user@$ssh_db_host -p $ssh_port \"cd $ssh_db_path; mysql --user=$db_user --password=$db_password --host=$db_host $db_name < dump.sql; rm dump.sql\"", true );
  }

  protected function commands_for_database_import_locally($args, &$commands)
  {
		extract( self::_prepare_and_extract( $args, false ) );
		$commands[]= array( "mysql --user=$db_user --password=$db_password --host=$db_host $db_name < dump.sql;", true );
  }

  protected function commands_for_database_dump($args, &$commands)
  {
		extract( self::_prepare_and_extract( $args, false ) );

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

  protected function _execute_commands($commands)
  {
    if($this->flags['dry-run']) {
      WP_CLI::line('DRY RUN....');
    }

		foreach ( $commands as $command_info ) {
			list( $command, $exit_on_error ) = $command_info;
      WP_CLI::line( $command );
      if(!$this->flags['dry-run']) WP_CLI::launch( $command, $exit_on_error );
		}
  }

	public function pull( $args = array() ) {
		extract( self::_prepare_and_extract( $args, false ) );

		$const = strtoupper( ENVIRONMENT ) . '_LOCKED';
		if ( constant( $const ) === true ) {
			return WP_CLI::error( ENVIRONMENT . ' env is locked, you can not pull to your local copy' );
		}
		$host = $db_host . ':' . $db_port;

		$wpdb = new wpdb( $db_user, $db_password, $db_name, $host );
		$path = ABSPATH;
		$url = get_bloginfo( 'url' );
		$dist_path  = constant( self::config_constant( 'path' ) ) . '/';
		$command = "ssh $ssh_user@$ssh_host -p $ssh_port \"cd $dist_path;wp migrate to $path $url dump.sql\" && scp $ssh_user@$ssh_host:$dist_path/dump.sql .";
		WP_CLI::launch( $command );
		WP_CLI::launch( 'wp db import dump.sql' );
		self::pull_files( $args );
	}

	public function pull_files( $args = array() ) {

		WP_CLI::line( 'pulling files' );
		extract( self::_prepare_and_extract( $args, false ) );

		$const = strtoupper( ENVIRONMENT ) . '_LOCKED';
		if ( constant( $const ) === true ) {
			return WP_CLI::error( ENVIRONMENT . ' env is locked, you can not pull to your local copy' );
		}

		$host = $db_host.':'.$db_port;

		if ( $ssh_host ) {
			$dir = wp_upload_dir();
			$dist_path  = constant( self::config_constant( 'path' ) ) . '/';
			$remote_path = $dist_path;
			$local_path = ABSPATH;

			WP_CLI::line( sprintf( 'Running rsync from %s:%s to %s', $ssh_host, $remote_path, $local_path ) );
			$com = sprintf( "rsync -avz -e 'ssh -p %s' %s@%s:%s %s  --delete --exclude '.git' --exclude 'wp-content/cache' --exclude 'wp-content/_wpremote_backups' --exclude 'wp-config.php'", $ssh_port, $ssh_user, $ssh_host, $remote_path, $local_path );
			WP_CLI::line( $com );
			WP_CLI::launch( $com );
		}

	}

	public static function _prepare_and_extract( $args, $tunnel = true ) {
		$out = array();
		self::$_env = $args[0];
		$errors = self::_validate_config();
		if ( $errors !== true ) {
			foreach ( $errors as $error ) {
				WP_Cli::error( $error );
			}
			return false;
		}
		$out = self::config_constants_to_array();
		$out['env'] = self::$_env;
		$out['db_user'] = escapeshellarg( $out['db_user'] );
		$out['db_host'] = escapeshellarg( $out['db_host'] );
		$out['db_password'] = escapeshellarg( $out['db_password'] );
		$out['ssh_port'] = ( isset($out['ssh_port']) ) ? escapeshellarg( $out['ssh_port']) : 22;

		if ( $out['ssh_db_host'] && $tunnel ) {
			$com = sprintf( 'ssh -f -L 3310:127.0.01:%s %s@%s sleep 600 >> logfile', ( $out['db_port'] ? $out['db_port'] : 3306 ), $out['ssh_db_user'], escapeshellarg( $out['ssh_db_host'] ) );
			shell_exec( $com );
			$out['db_host'] = '127.0.0.1';
			$out['db_port'] = '3310';
		}
		return $out;
	}

	protected static function _validate_config() {
		$errors = array();
		foreach ( array( 'path', 'url', 'db_host', 'db_user', 'db_name', 'db_password' ) as $postfix ) {
			$required_constant = self::config_constant( $postfix );
			if ( ! defined( $required_constant ) ) {
				$errors[] = "$required_constant is not defined";
			}
		}
		if ( count( $errors ) == 0 ) return true;
		return $errors;
	}

	protected static function config_constant( $postfix ) {
		return strtoupper( self::$_env.'_'.$postfix );
	}

	protected static function config_constants_to_array() {
		$out = array();
		foreach ( array( 'locked', 'path', 'ssh_db_path', 'url', 'db_host', 'db_user', 'db_port', 'db_name', 'db_password', 'ssh_db_host', 'ssh_db_user', 'ssh_db_path', 'ssh_host', 'ssh_user', 'ssh_port', 'remove_admin' ) as $postfix ) {
			$out[$postfix] = defined( self::config_constant( $postfix ) ) ? constant( self::config_constant( $postfix ) ) : null;
		}
		return $out;
	}

	private static function _trim_url( $url ) {

		/** In case scheme relative URI is passed, e.g., //www.google.com/ */
		$url = trim( $url, '/' );

		/** If scheme not included, prepend it */
		if ( ! preg_match( '#^http(s)?://#', $url ) ) {
			$url = 'http://' . $url;
		}

		$url_parts = parse_url( $url );

		/** Remove www. */
		$domain = preg_replace( '/^www\./', '', $url_parts['host'] );

		return $domain;
	}

	/**
	 * Help function for this command
	 */
	public static function help() {
		WP_CLI::line( <<<EOB

EOB
  );
  }
}
