<?php
class WP_Deploy_Flow_Configurator {

  protected $_config;
  protected $_environment;
  protected $_required_config_values = array( 'path', 'url', 'db_host', 'db_user', 'db_name', 'db_password' );
  protected $config_values = array( 'path', 'url', 'db_host', 'db_user', 'db_name', 'db_password' );

  public function __construct($environment)
  {
    $this->_environment = $environment;
  }
	public function getParams() {
		$out = array();
		$errors = self::_validate_config();
		if ( $errors !== true ) {
			foreach ( $errors as $error ) {
				WP_Cli::error( $error );
			}
			return false;
		}
		$out = self::config_constants_to_array();
		$out['env'] = $this->_environment;
		$out['db_user'] = escapeshellarg( $out['db_user'] );
		$out['db_host'] = escapeshellarg( $out['db_host'] );
		$out['db_password'] = escapeshellarg( $out['db_password'] );
		$out['ssh_port'] = ( isset($out['ssh_port']) ) ? intval( $out['ssh_port']) : 22;
    $out['excludes'] = explode(':', $out['excludes']);
		return $out;
	}

  public function setConfig($config)
  {
    if($errors = $this->validate_config($config) === true) {
      self::$_config = $config;
    } else {
      throw new Exception();
    }

  }

	protected static function _validate_config($config) {
		$errors = array();
		foreach (self::$_required_config_values as $postfix ) {
      if(empty(self::$_rawconfig[$postfix])) {
        $errors[]= "Missing required config value $postfix";
      }
		}
		if ( count( $errors ) == 0 ) return true;
		return false;
	}

	public static function config_constant( $postfix ) {
		return strtoupper( self::$_env.'_'.$postfix );
	}

	protected static function fill_rawconfig_from_constants() {
		$out = array();
		foreach ( self::$_config_values as $key ) {
			self::$_rawconfig[$key] = defined( self::config_constant( $key ) ) ? constant( self::config_constant( $key ) ) : null;
		}
	}
}