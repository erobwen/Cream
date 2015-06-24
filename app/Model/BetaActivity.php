<?php 

App::uses('AppModel', 'Model');

class BetaActivity extends AppModel
{
	public $extends = 'Activity';
	
	public $belongsTo = array(
		'Activity'
 	);
}

