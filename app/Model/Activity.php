<?php 

App::uses('AppModel', 'Model');

class Activity extends AppModel
{
	public $extendTo = array(
		'AlphaActivity', 
		'BetaActivity'
	);

	public $hasOne = array(
		'AlphaActivity', 
		'BetaActivity'		
	);
	
	public $hasMany = array(
		'ChildActivity' => array(
			'className' => 'Activity', 
			'foreignKey' => 'parent_activity_id', 
			'dependent' => true
		)
	);
	
	public $belongsTo = array(
		'ParentActivity' => array(
			'className' => 'Activity', 
			'foreignKey' => 'parent_activity_id'
		),
		'LastChangeUser' => array(
			'className' => 'User',
			'foreignKey' => 'last_change_user_id'
		)
 	);
}