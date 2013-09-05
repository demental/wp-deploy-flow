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

  public function testCommandsForFileReturnsOneCommand()
  {
    $test_params = array('excludes' => array());
    $subject = new Wp_Deploy_Flow_Pusher($test_params);
    $result = $subject->commands_for_files();

    $this->assertEquals(1, count($result));
    $this->assertEquals(0, strpos($result[0][0], 'rsync'));
  }
}

