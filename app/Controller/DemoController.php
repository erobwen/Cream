<?php
App::uses('AppController', 'Controller');
App::uses('Entity', 'Model/Entity');


/**
 * Test Cases Controller
 */
class DemoController extends AppController {
	
	public function renderJson($data) {
		$this->set('data', $data);
		$this->layout = 'ajax';
		$this->render('/dataAsJson');
	}
	
	public function comparison() {	
		/* Old style, work with table oriented Model classes.
			$this->TestCase->contain(array('ActivationTest' => array('QuantifiedProductSet' => array('TestSet'))));
			$data = $this->TestCase->findById($id);
			$this->TestCase->ActivationTest->QuantifiedProductSet->TestSet->id = $data['TestCase']['ActivationTest'][0]['QuantifiedProductSet']['TestSet']['id'];
			$this->TestCase->ActivationTest->QuantifiedProductSet->TestSet->saveField('name', "Foobar");
		 */ 			

		/* New style, work with entity objects that inherit functionality and data (from base class and base models). 
			$testCase = Entity::get('TestCase', 3);
			$testCase->ActivationTest()[0]->ActivationSpecification()->QuantifiedProductSet()->TestSet()->setName("Foobar"); // . $createdTestCase->id()
		*/

		// Test
		// $randomInt = rand(1, 100);
		// $newName = "Foobar" . $randomInt;
		// pr("New name: " . $newName);
		// $testCase = Entity::get('TestCase', 3);
		// pr("Before setting:" . $testCase->ActivationTest()[0]->ActivationSpecification()->QuantifiedProductSet()->TestSet()->name());
		// $testCase->ActivationTest()[0]->ActivationSpecification()->QuantifiedProductSet()->TestSet()->setName($newName); // . $createdTestCase->id()
		// pr("After setting:" . $testCase->ActivationTest()[0]->ActivationSpecification()->QuantifiedProductSet()->TestSet()->name());

		// $testCase = Entity::get('TestCase', 3);
		// $testCase->ActivationTest()[0]->ActivationSpecification()->TestSet()->name();
		// $testCase->ActivationTest()[0]->ActivationSpecification()->TestSet()->setName($newName); 
		
		// . $createdTestCase->id()
		// pr("After setting:" . $testCase->ActivationTest()[0]->ActivationSpecification()->QuantifiedProductSet()->TestSet()->name());

		// die;
	}
	
	public function basic() {
		try {
			// Testing entity creation, field getting, relation browsing and field setting.
			// $testCase = Entity::get('TestCase', 3);
			// pr($testCase->userId());
			// pr($testCase->name());
			// pr($testCase->ActivationTest()[0]->name());
			// pr($testCase->ActivationTest()[0]->NextActivationTest()->name());
			// pr($testCase->ActivationTest()[1]->name());
			// pr($testCase->ActivationTest()[0]->ActivationSpecification()->QuantifiedProductSet()->TestSet()->name());
			// pr($testCase->ActivationTest()[1]->ActivationSpecification()->QuantifiedProductSet()->TestSet()->name());
			// pr($testCase->ActivationTest()[0]->ActivationSpecification()->QuantifiedProductSet()->TestSet()->setName("Foobar" . rand(1, 100)));
			// pr($testCase->ActivationTest()[0]->ActivationSpecification()->QuantifiedProductSet()->TestSet()->name());
			// pr($testCase->serialize());


			// $activationSpecification = Entity::get('ActivationSpecification', 3);
			// $activationSpecification->printRoles();
			// pr($activationSpecification->serialize());

			// Testing with another class
			// Entity::restart();
			// $activationTest = Entity::get('ActivationTest', 1);
			// pr($activationTest->name());
			// pr($activationTest->serialize());
			
			// die();
		} catch(Exception $exception) {
			// pr($exception->getMessage());
		}
		$this->renderJson(array());
	}
		
	
	public function single_table() {
		// Create a new entity (with a certain class)
		// $activationSequence = Entity::create('TestCase', array('class' => 'activation_sequence'));
		// $id = $activationSequence->id();
		// pr("=== Activation sequence ===");
		// pr($activationSequence->serialize());
		// pr("activationSequence->test(): " . $activationSequence->test());

		// Create a new entity (with a certain class)
		// $batchActivation = Entity::create('TestCase', array('class' => 'batch_activation'));
		// pr("=== Batch activation ===");
		// pr($batchActivation->serialize());
		// pr("batchActivation->test(): " . $batchActivation->test());
		
		// $batchActivation->printRoles();
		
		// Reload
		// Entity::restart();
		// $loadedTestCase = Entity::get('TestCase', $id);
		// pr($loadedTestCase->serialize());
		die;
	}

	
	public function multi_table() {
		// Entity experiments:
		try {
			// Demonstrate muiltiple model creations, and an entity containing fields from both models.
			pr("=== Demonstrate muiltiple model creations, and an entity containing fields from both models. === ");
			$alphaActivity = Entity::create('AlphaActivity');
			// pr("here");
			// die;
			pr($alphaActivity->id());
			$alphaActivity->setTitle("New alpha");
			pr($alphaActivity->serialize());
			
			// Add a child activity
			pr("=== Adding a child activity ===");
			$betaActivity = Entity::create('BetaActivity');
			$betaActivity->setTitle("New beta");
			$betaActivity->setParentActivity($alphaActivity);
			pr($alphaActivity->serialize());


			// Add another beta activity (node that cached relation in parent gets cleared)
			pr("=== Adding another beta activity ===");
			$otherBetaActivity = Entity::create('BetaActivity');
			$otherBetaActivity->setTitle("The other beta");
			$otherBetaActivity->setParentActivity($alphaActivity);
			pr($alphaActivity->serialize());
			
			die();
		} catch(Exception $exception) {
			pr($exception->getMessage());
		}
		$this->renderJson(array());
	}

	
	public function to_many_manipulation() {
		try {
			// Demonstrate muiltiple model creations, and an entity containing fields from both models.
			pr("=== Demonstrate muiltiple model creations, and an entity containing fields from both models. === ");
			$alphaActivity = Entity::create('AlphaActivity');
			$alphaActivity->setTitle("New alpha");
			
			// Add a child activity
			pr("=== Adding a child activity ===");
			$betaActivity = Entity::create('BetaActivity');
			$betaActivity->setTitle("New beta");
			$alphaActivity->addChildActivity($betaActivity);
	
			pr($alphaActivity->serialize());
	
			// Add another beta activity (node that cached relation in parent gets cleared)
			pr("=== Adding another beta activity ===");
			$otherBetaActivity = Entity::create('BetaActivity');
			$otherBetaActivity->setTitle("The other beta");
			$alphaActivity->addChildActivity($otherBetaActivity);
			
	
			pr($alphaActivity->serialize());
		
			die();
		} catch(Exception $exception) {
			pr($exception->getMessage());
		}
		$this->renderJson(array());		
		
	}

	public function copy() {
		// Entity experiments:
		try {
			// Demonstrate copying of some complex data structure
			pr("=== Create an original ===");
			$alphaActivity = Entity::create('AlphaActivity');
			$alphaActivity->setTitle("New alpha from original");

			$betaActivity = Entity::create('BetaActivity');
			$betaActivity->setTitle("New beta");
			$betaActivity->setParentActivity($alphaActivity);

			$otherBetaActivity = Entity::create('BetaActivity');
			$otherBetaActivity->setTitle("The other beta");
			$otherBetaActivity->setParentActivity($alphaActivity);
			
			pr($alphaActivity->serialize());

			//Create a copy and serialize.
			pr("=== Copy and serialize ===");
			$alphaActivityCopy = $alphaActivity->copy();
			pr($alphaActivityCopy->serialize());
			
			die();
		} catch(Exception $exception) {
			pr($exception->getMessage());
		}
		$this->renderJson(array());
	}
	
	
	public function demo() {
		// Manual dispatch of event to the right model:
		$poodleId = 42;
		$this->contain(array('Poodle' => array('Dog' => array('Animal'))));
		$data = $this->findById($poodleId);
		$nextAnimalId = $data['Poodle']['Dog']['Animal']['next_animal_id'];		
		$this->Dog->Animal->contain(array('Rabbit', 'Dog' => array('Poodle', 'Cockerspaniel')));
		$nextAnimalData = $this->Dog->Animal->findById($nextAnimalId, $id);
		if($nextAnimalData['Rabbit']['id'] == null) {
			$this->Dog->Animal->Rabbit->greet($nextAnimalData['Rabbit']['id'], "Welcome");
		} else if (isset($nextAnimalData['Dog']['id'])) {
			if(isset($nextAnimalData['Dog']['Poodle']['id'])) {
				$this->greet($nextAnimalData['Dog']['Poodle']['id'], "Welcome");
			} else if (isset($nextAnimalData['Dog']['Cockerspaniel']['id'])){
				$this->Dog->Cockerspaniel->greet($nextAnimalData['Dog']['Cockerspaniel']['id'], "Welcome");		
			}
		}

		// With entities and inheritance:
		$poodle = Entity::get('Poodle', 42);
		$poodle->nextAnimal()->greet("Welcome");

	}
}


/**
* Why not interpret "is one" as "has one"? Well, since we have to implement manual dispatch mechanisms. It is just as useful as a "manual automatic feature" . 

Example, assume that we want greet the next animal in line. 

Class structure:

	Animal
		Rabbit
		Dog
			Cokerspaniel
			Poodle

Assume that we have a list of animals, that point to the next animal. Assume that an animal wants to greet the next animal in line, and that the response of the greeting should be depeond on what kind of animal is next in line. 
			
// Without entities and inheritance:

	$poodleId = 42;
	$this->contain('Poodle' => array('Dog' => array('Animal')));
	$data = $this->findById($poodleId);
	$nextAnimalId = $data['Poodle']['Dog']['Animal']['next_animal_id'];
	
	// Manual dispatch of event to the right model
	$this->Dog->Animal->contain(array('Rabbit', 'Dog' => array('Poodle', 'Cockerspaniel')));
	$nextAnimalData = $this->Dog->Animal->findById($nextAnimalId, $id);
	if($nextAnimalData['Rabbit']['id'] == null) {
		$this->Dog->Animal->Rabbit->greet($nextAnimalData['Rabbit']['id'], "Welcome");
	} else if (isset($nextAnimalData['Dog']['id'])) {
		if(isset($nextAnimalData['Dog']['Poodle']['id'])) {
			$this->greet($nextAnimalData['Dog']['Poodle']['id'], "Welcome");
		} else (isset($nextAnimalData['Dog']['Cockerspaniel']['id'])){
			$this->Dog->Cockerspaniel->greet($nextAnimalData['Dog']['Cockerspaniel']['id'], "Welcome");		
		}
	}

// With entities and inheritance:

	$poodle->nextAnimal()->greet("Welcome");

*/
