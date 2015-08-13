<?php 

App::uses('AppModel', 'Model');

class SomeDetail extends AppModel
{
	public $extendTo = array(
		'SomeSpecificDetail'
	);
	
	public $hasOne = array(
		'SomeSpecificDetail'
	);
}