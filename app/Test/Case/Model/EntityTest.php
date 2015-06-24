<?php 
/**
* Note this test makes a call to elis and could fail if information changes. 
*/
App::uses('Entity', 'Model/Entity');


class EntityTest extends ControllerTestCase {
	public $fixtures = array(
		'user',
		'activity', 
		'alpha_activity', 
		'beta_activity', 
		'some_detail', 
		'some_specific_detail'
	);
	
		
    public function setUp() {
		parent::setUp();
		// $this->TestCase = ClassRegistry::init('TestCase');
    }
	
	public function testMultiTableInheritance() {
		$this->assertEquals(true, true);
	}
}
		