<?php 
/**
* Entity class that corresponds to one (or several in the case of inheritance) model records.
*/
App::uses('Entity', 'Model/Entity');

class ActivityEntity extends Entity
{	
	public function init($initData) {
		parent::init($initData);
		// pr("Activity!");
		// pr($initData);
		$this->setTitle(get($initData, 'title', $this->title()));
		// pr($this->description());
		// pr(get($initData, 'description', $this->description()));
		$this->setDescription(get($initData, 'description', $this->description()));
	}

	// public function nameAndCreator() {
		// return $this->name() . " (Created by " . $this->User()->name() . ")";
	// }
}

