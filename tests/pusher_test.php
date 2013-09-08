<?php
require './lib/pusher.php';

function get_option($key) {
  return 'http://mysite.org/';
}
function untrailingslashit($in) {
  return $in;
}

class PusherTest extends PHPUnit_Framework_TestCase {

  protected function minimal_valid_params()
  {
    return array(
      'excludes' => array(),
      'path' => '/path/to/remote',
      'db_user' => 'dbuser',
      'db_host' => 'localhost',
      'db_password' => 'dbpassword'
    );
  }

  public function testCommandsReturnsArrayWithCommands() {
    $subject = new Wp_Deploy_Flow_Pusher($this->minimal_valid_params());
    $expected = array (
      array (
        'wp db export db_bk.sql',
        true,
      ),
      array (
        'wp search-replace http://mysite.org/ ',
        true,
      ),
      array (
        'wp search-replace ABSPATH /path/to/remote',
        true,
      ),
      array (
        'wp db dump dump.sql',
        true,
      ),
      array (
        'wp db import db_bk.sql',
        true,
      ),
      array (
        'rm db_bk.sql',
        true,
      ),
      array (
        'mysql --user=dbuser --password=dbpassword --host=localhost  < dump.sql;',
        true,
      ),
      array (
        'rm dump.sql',
        true,
      ),
      array (
        'rsync -avz ABSPATH /path/to/remote/ --exclude ".git" --exclude "wp-content/cache" --exclude "wp-content/_wpremote_backups" --exclude "wp-config.php"',
        true,
      ),
    );

    $result = $subject->commands();
    $this->assertEquals($expected, $result);
  }

  public function testCommandForFileReturnsOneRsyncCommand()
  {
    $expected = array(
      array(
        'rsync -avz ABSPATH /path/to/remote/ --exclude ".git" --exclude "wp-content/cache" --exclude "wp-content/_wpremote_backups" --exclude "wp-config.php"',
         true)
      );
    $subject = new Wp_Deploy_Flow_Pusher($this->minimal_valid_params());

    $result = $subject->command_for_files();
    $this->assertEquals($expected, $result);
  }

  public function testCommandForFileUsesSshForRsyncWhenSsh_hostIsSet()
  {
    $test_params = array_merge($this->minimal_valid_params(), array('ssh_host' => 'a_ssh_host'));
    $subject = new Wp_Deploy_Flow_Pusher($test_params);
    $result = $subject->command_for_files();
    $this->assertRegexp("`^rsync \-avz \-e 'ssh`",$result[0][0]);
  }

  public function testUseOfPostPushCommandsWhenSSh_notSet()
  {
    $expected = array('do_domething_on_server',true);
    $test_params = array_merge($this->minimal_valid_params(), array('post_push_command' => 'do_domething_on_server'));
    $subject = new Wp_Deploy_Flow_Pusher($test_params);
    $result = array_pop($subject->commands());

    $this->assertEquals($expected, $result);
  }

  public function testUseOfPostPushCommandsWhenSSh_isSet()
  {
    $expected = array('ssh user@server.com "do_domething_on_server"',true);
    $test_params = array_merge($this->minimal_valid_params(),
      array(
        'ssh_host' => 'server.com',
        'ssh_user' => 'user',
        'post_push_command' => 'do_domething_on_server'
      )
    );
    $subject = new Wp_Deploy_Flow_Pusher($test_params);
    $result = array_pop($subject->commands());

    $this->assertEquals($expected, $result);
  }

}