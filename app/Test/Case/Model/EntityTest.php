<?php 
/**
* Note this test makes a call to elis and could fail if information changes. 
*/
App::uses('Entity', 'Model/Entity');


class EntityTest extends ControllerTestCase {
	public $fixtures = array(
		'vehicle',
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
		
		$alphaActivity = Entity::create('AlphaActivity', array('title' => $title, 'description' => $description, 'alphaFieldOne' => $alphaFieldOne));
		// pr($alphaActivity->serialize());
		// $alphaActivity->printRoles();
		// die;
		$this->assertEquals($alphaActivity->title(), $title);
		$this->assertEquals($alphaActivity->description(), $description);
		$this->assertEquals($alphaActivity->alphaFieldOne(), $alphaFieldOne);
	}
	
	
	public function testSingleTableConstruction() {
		$boat = Entity::create('Vehicle', array('class' => 'boat'));
		// pr($boat->serialize());
		// $boat->printRoles();
		// die;
		$this->assertEquals("Boat foo", $boat->foo());		
		$this->assertEquals("Vehicle base fie", $boat->fie());		
	}
	
	public function testSingleRelation() {
		$alpha = Entity::create('AlphaActivity');
		$detail = Entity::create('SomeSpecificDetail', array('specificDetailField' => 'my value'));
		$alpha->setSomeDetail($detail);
		// pr($alpha->serialize());
		// $alpha->printRoles();
		// die;
		$this->assertEquals("my value", $alpha->SomeDetail()->specificDetailField());
		
		$alpha->setSomeDetail(null);
		$this->assertEquals(null, $alpha->SomeDetail());
		
	}
	
	
	public function testDataStructureConstructionAndBrowsing() {
		$alpha = Entity::create('AlphaActivity');
		$beta = Entity::create('BetaActivity');
		$beta->setTitle("Foobar");
		
		// Construction & Browsing
		$alpha->addChildActivity($beta);
		$this->assertEquals("Foobar", $alpha->ChildActivity()[0]->title());

		// Serialization				
		// pr(var_export($alpha->serialize()));
		$expected = array (
			'class' => 'AlphaActivity',
			'phpEntityClass' => 'AlphaActivityEntity',
			'roles' => 'AlphaActivity:1; Activity:1',
			'title' => 'Default Title',
			'description' => NULL,
			'last_change' => NULL,
			'alpha_field_one' => 'Alpha field one default',
			'alpha_field_two' => 'Alpha field two default',
			'SomeDetail' => NULL,
			'ParentActivity' => NULL,
			'LastChangeUser' => NULL,
			'AlphaActivity' => NULL,
			'BetaActivity' => NULL,
			'ChildActivity' => 
				array (
					0 => 
						array (
						  'class' => 'BetaActivity',
						  'phpEntityClass' => 'Entity',
						  'roles' => 'BetaActivity:1; Activity:2',
						  'title' => 'Foobar',
						  'description' => NULL,
						  'last_change' => NULL,
						  'beta_field_1' => 'Beta field 1 default',
						  'beta_field_2' => 'Beta field 2 default',
						  'ParentActivity' => 'AlphaActivity:1',
						  'LastChangeUser' => NULL,
						  'AlphaActivity' => NULL,
						  'BetaActivity' => NULL,
						  'ChildActivity' => NULL,
						),
				),
		);
		$this->assertEquals($expected, $alpha->serialize());
		
		// Removal of child
		$alpha->removeChildActivity($beta);
		$this->assertEquals(true, empty($alpha->ChildActivity()));
	}
}
		