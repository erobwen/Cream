<?php 
/**
* Entity class that corresponds to one (or several in the case of inheritance) model records.
*/
App::uses('Entity', 'Model/Entity');

class AlphaActivityEntity extends ActivityEntity
{	
	public function init($initData) {
		parent::init($initData);
		// pr("Alpha activity!");
		// $this->printRoles();
		// pr(get($initData, 'alphaFieldOne', $this->alphaFieldOne()));
		// die;
		$this->setAlphaFieldOne(get($initData, 'alphaFieldOne', $this->alphaFieldOne()));
	}

	// public function nameAndCreator() {
		// return $this->name() . " (Created by " . $this->User()->name() . ")";
	// }
}

