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
	
	public function testMultiTableConstruction() {
		$title = "Some Title";
		$description = "Some Content";
		$alphaFieldOne = "Alpha field 1 contents";
		
		$alphaActivity = Entity::create('AlphaActivity', array('initData' => array('title' => $title, 'description' => $description, 'alphaFieldOne' => $alphaFieldOne)));
		// pr($alphaActivity->serialize());
		// $alphaActivity->printRoles();
		// die;
		$this->assertEquals($alphaActivity->title(), $title);
		$this->assertEquals($alphaActivity->description(), $description);
		$this->assertEquals($alphaActivity->alphaFieldOne(), $alphaFieldOne);
	}
}
		