<?php 

App::uses('AppModel', 'Model');

class AlphaActivity extends AppModel
{
	public $extends = 'Activity';

	public $belongsTo = array(
		'Activity',
		'SomeDetail' => array(
			'dependent' => true
		)
 	);
}