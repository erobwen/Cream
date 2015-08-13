<?php 
/**
*  "Cream" - Object Relational Model, Written by Robert Wensman
*/
App::uses('Entity', 'Model/Entity');

class VehicleEntity extends Entity
{
	public function init($initData) {
		parent::init($initData);
	}	
	
	public function foo() {
		return "Vehicle base foo";
	}
	
	public function fie() {
		return "Vehicle base fie";
	}
}