<?php
WP_CLI::add_command( 'deploy', 'WP_Deploy_Flow_Command' );

require 'pusher.php';
require 'puller.php';
require 'configurator.php';
/**
 * deploys
 *
 * @package wp-deploy-flow
 * @author Arnaud Sellenet
 */
class WP_Deploy_Flow_Command extends WP_CLI_Command {

	/**
   * Push local to remote, both system and database
   *
   * @synopsis <environment> [--dry-run]
   */
	public function push( $args = array(), $flags = array() ) {
    $this->_push_command('commands', $args, $flags);
	}

	/**
   * Push local to remote, only filesystem
   *
   * @synopsis <environment> [--dry-run]
   */
	public function push_files( $args = array(), $flags = array() ) {
    $this->_push_command('commands_for_files', $args, $flags);
  }

	/**
   * Pull local from remote, both system and database
   *
   * @synopsis <environment> [--dry-run]
   */
	public function pull( $args = array(), $flags = array() ) {
    $this->_pull_command('commands', $args, $flags);
	}

	/**
   * Pull local from remote, only filesystem
   *
   * @synopsis <environment> [--dry-run]
   */
	public function pull_files( $args = array(), $flags = array() ) {
    $this->_pull_command('commands_for_files', $args, $flags);
  }

  protected function _push_command($command_name, $args, $flags)
  {
    $this->initParams($args);
    $this->flags = $flags;

		if ( $this->params['locked'] === true ) {
			return WP_CLI::error( "$env environment is locked, you cannot push to it" );
		}

    $reflectionMethod = new ReflectionMethod('WP_Deploy_Flow_Pusher', $command_name);
    $commands = $reflectionMethod->invoke(new WP_Deploy_Flow_Pusher($this->params));

    $this->_execute_commands($commands, $args);
	}

  protected function _pull_command($command_name, $args, $flags)
  {
    $this->initparams($args);
    $this->flags = $flags;
    extract($this->params);

		$const = strtoupper( ENVIRONMENT ) . '_LOCKED';
		if ( constant( $const ) === true ) {
			return WP_CLI::error( ENVIRONMENT . ' env is locked, you can not pull to your local copy' );
		}

    $reflectionMethod = new ReflectionMethod('WP_Deploy_Flow_Puller', $command_name);
    $commands = $reflectionMethod->invoke(new WP_Deploy_Flow_Puller($this->params));

    $this->_execute_commands($commands, $args);
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

  protected function _initParams($args)
  {
    $this->params = new WP_Deploy_Flow_Configurator( $args )->getParams();
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
