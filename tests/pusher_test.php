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
	function testCommandsCount() {

    $subject = new Wp_Deploy_Flow_Pusher($this->minimal_valid_params());

    $result = $subject->commands();
    $this->assertEquals(9, count($result));
  }

  public function testCommandsForFileReturnsOneRsyncCommand()
  {
    $expected = array(
      array(
        'rsync -avz ABSPATH /path/to/remote/ --exclude ".git" --exclude "wp-content/cache" --exclude "wp-content/_wpremote_backups" --exclude "wp-config.php"',
         true)
      );
    $subject = new Wp_Deploy_Flow_Pusher($this->minimal_valid_params());
    $result = $subject->commands_for_files();

    $this->assertEquals(1, count($result));
    $this->assertEquals($expected, $result);
  }

  public function testCommandsForFileUsesSshForRsyncWhenSsh_hostIsSet()
  {
    $test_params = array_merge($this->minimal_valid_params(), array('ssh_host' => 'a_ssh_host'));
    $subject = new Wp_Deploy_Flow_Pusher($test_params);
    $result = $subject->commands_for_files();
    $this->assertRegexp("`^rsync \-avz \-e 'ssh -p`",$result[0][0]);
  }

}