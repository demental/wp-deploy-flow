<?php
require './lib/pusher.php';

function get_option($key) {
  return 'http://mysite.org/';
}
function untrailingslashit($in) {
  return $in;
}

class PusherTest extends PHPUnit_Framework_TestCase {

	function testCommandsCount() {

    $test_params = array('excludes' => array());
    $subject = new Wp_Deploy_Flow_Pusher($test_params);

    $result = $subject->commands();
    $this->assertEquals(9, count($result));
  }

  public function testCommandsForFileReturnsOneRsyncCommand()
  {
    $test_params = array('excludes' => array());
    $expected = array(
      array(
        'rsync -avz / // --exclude ".git" --exclude "wp-content/cache" --exclude "wp-content/_wpremote_backups" --exclude "wp-config.php" --exclude "/"',
         true)
      );
    $subject = new Wp_Deploy_Flow_Pusher($test_params);
    $result = $subject->commands_for_files();

    $this->assertEquals(1, count($result));
    $this->assertEquals($expected, $result);
  }

  public function testCommandsForFileUsesSshForRsyncWhenSsh_hostIsSet()
  {
    $test_params = array('excludes' => array(), 'ssh_host' => 'a_ssh_host');
    $subject = new Wp_Deploy_Flow_Pusher($test_params);
    $result = $subject->commands_for_files();
    $this->assertRegexp("`^rsync \-avz \-e 'ssh -p`",$result[0][0]);
  }

}