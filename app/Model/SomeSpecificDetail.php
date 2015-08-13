<?php 

App::uses('AppModel', 'Model');

class SomeSpecificDetail extends AppModel
{
	public $extends = 'SomeDetail';

	public $belongsTo = array(
		'SomeDetail' => array(
			'dependent' => true
		)
 	);

}