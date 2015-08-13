<?php 
/**
*  "Cream" - Object Relational Model, Written by Robert Wensman
*/
App::uses('VehicleEntity', 'Model/Entity');

class CarEntity extends VehicleEntity
{
	public function init($initData) {
		parent::init($initData);
	}	
	
	public function foo() {
		return "Car foo";
	}
}