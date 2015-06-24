<?php 
/**
*  "Cream" - Object Relational Model, Written by Robert Wensman
*/

/**
* List all non abstract classes. 
*/
// App::uses('ActivationTestEntity', 'Model/Entity'); // Abstract, cannot be initialized (because model has a 'class' column
App::uses('UpgradeJobProductListEntity', 'Model/Entity');
App::uses('ProductListEntityEntity', 'Model/Entity');
App::uses('UpgradeJobEntityEntity', 'Model/Entity');

// App::uses('TestCaseEntityEntity', 'Model/Entity'); // Abstract, cannot be initialized (because model has a 'class' column
App::uses('BatchActivationEntity', 'Model/Entity');
App::uses('ActivationSequenceEntity', 'Model/Entity');

// public function createEntity($modelName, $class = '', $customOptionalArguments=array()) {
	// return Entity::create($modelName, $class, $customOptionalArguments);
// }
// public function getEntity($modelName, $id, $data = null) {
	// return Entity::get($modelName, $id, $data);
// }



if (!function_exists('startsWith')) {
	function startsWith($haystack, $needle) {
		// search backwards starting from haystack length characters from the end
		return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
	}
}

if (!function_exists('endsWith')) {
	function endsWith($haystack, $needle) {
		// search forward starting from end minus needle length characters
		return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== FALSE);
	}
}

if (!function_exists('get')) {
	function get($array, $key, $default = null) {
		if (isset($array[$key])) {
			return $array[$key];
		} else {
			return $default;
		}
	}
}

if (!function_exists('pre')) { // Printout entities without bloating the printout with the models. 
	function pre($data) {
		Entity::pr($data);
	}
}

if (!function_exists('copyArray')) {
	function copyArray($original) {
		$new = array();
		foreach($original as $key => $value) {
			$new[$key] = $value;
		}
		return $new;
	}
}


/**
* Entity
*/
class Entity {
	/**
	* Model helpers. Most of these could really reside in the app model, but for increased portability they are here. 
	*/
	public static function getModel($modelName) {
		$model = null;
		if (ClassRegistry::isKeySet($modelName)) {
			$model = ClassRegistry::getObject($modelName);
		} else {
			$model = ClassRegistry::init($modelName);
		}
		if (!property_exists($model, 'extends')) {
			$model->{'extends'} = null;
		}
		if (!property_exists($model, 'extendsTo')) {
			$model->{'extendTo'} = array();
		}
		return $model;
	}
	
	/**
	* Note: 
	* If $modelAlias is an array, then this will result in an array with entries like $modelAlias => $modelAliasData
	* If $modelAlias is a string, it will just yield $modelAliasData
	*/
	public static function getRelatedData($model, $primaryKeyValue, $modelAlias, $relatedModel) {
		$model->contain(array($modelAlias));
		// pr($model->name);
		// pr(array('conditions' => array($model->primaryKey => $primaryKeyValue)));
		// $model->{$model->primaryKey} => $primaryKeyValue;
		$data = $model->find('first', array('conditions' => array($model->name . "." . $model->primaryKey => $primaryKeyValue)));
		$model->resetBindings();
		// pr("Foobar");
		
		return self::getRelatedDataFromData($data, $modelAlias, $relatedModel);
	}

	
	public static function getRelatedDataFromData($data, $modelAlias, $relatedModel) {
		if (isset($data[$modelAlias])) {
			$relatedData = $data[$modelAlias];
			if (!array_key_exists($relatedModel->primaryKey, $relatedData)) {
				// No id, must be a to many relation
				return $relatedData;
			} else {
				if ($relatedData[$relatedModel->primaryKey] != null) {
					// A single related entity
					return $relatedData;
				} else {
					return null;
				}
			}				
		} else {
			return null;
		}
	}
	
	
	public static function constructPrimitive($model, $data = array()) {
		$model->create();
		$model->save(array($model->alias => $data));
		return $model->{$model->primaryKey};
	}	
	
	/**
	* Get primitive data 
	*/
	public static function getPrimitiveData($model, $primaryKeyValue) {
		$model->contain();
		$data = $model->find('first', array('conditions' => array($model->name . "." . $model->primaryKey => $primaryKeyValue)))[$model->name];
		// $data = $model->findById($id)[$model->name]; // Load data, for synchronization (with databases default values)
		// prd($data);
		$model->resetBindings();

		// Separate primitive data from foreign keys.
		$primaryKeyValue = null;
		$primitiveData = array();
		$foreignKeys = array();	
		$columnTypes = $model->getColumnTypes();
		foreach($columnTypes as $column => $type) {
			if ($column == $model->primaryKey) {
				$primaryKeyValue = $data[$column];
			} else if ($model->isForeignKey($column)) {
				$foreignKeys[$column] = $data[$column];
			} else if (isset($data[$column])) { // and 
				$primitiveData[$column] = $data[$column];
			} else {
				$primitiveData[$column] = null;
			}
		}
		return array('primaryKeyValue' => $primaryKeyValue, 'primitiveData' => $primitiveData, 'foreignKeys' => $foreignKeys);
	}
	
	
	public function getAssociationsToModel($model, $modelName) {
		$result = array();
		foreach ($model->_associations as $assoc) {
			if (!empty($model->{$assoc})) {
				foreach($model->{$assoc} as $relationName => $relationInfo) {
					if ($relationInfo['className'] == $modelName) {
						$result[] = $relationName;
					}
				}
			}
		}
		// prd($result);
		return $result;
	} 
	
	public function getMirrorRelation($model, $relatedModelName, $sourceRelationName, $foreignKey) {
		foreach ($model->_associations as $assoc) {
			if (!empty($model->{$assoc})) {
				foreach($model->{$assoc} as $relationName => $relationInfo) {
					if ($relationName != $sourceRelationName && $relationInfo['className'] == $relatedModelName && $relationInfo['foreignKey'] == $foreignKey) {
						return array('relationName' => $relationName, 'relationInfo' => $relationInfo);
					}
				}
			}
		}
		return null;
	}
	
	
	/**
	*
	* Roles static services
	*
	*/
	public static $roleIdEntityMap = array();
	public static $reflectionClasses = array();
	
	public static function restart() {
		self::$roleIdEntityMap = array();
	}
	
	public static function createRoleId($modelName, $id) {
		return $modelName . ":" . $id; 
	}
	
	
	public static function getReflectionClass($className) {
		if (!isset(self::$reflectionClasses[$className])) {
			self::$reflectionClasses[$className] = new ReflectionClass($className);
		}
		return self::$reflectionClasses[$className];
	}
	
	public static function getPhpClassName($modelName, $class = null) {
		// pr("Get entity cqlass name");
		// pr($modelName);
		// pr($data);
		$elaborateName = null;
		if ($class != null) {
			$elaborateName = $class . "Entity";
		} else {
			$elaborateName = $modelName . "Entity";
		}
		// pr($elaborateName);
		if (class_exists($elaborateName)) {
			// pr("Exists!");
			return $elaborateName;
		} else {
			// pr("Sad day");
			return "Entity";
		}
	}
	
	
	public static function createEntityObjectFromRoles($roles, $initArguments = null) {
		$primaryRole = $roles[0];
		$columnClass = get($primaryRole['data'], 'class');
		$columnClass = ($columnClass == null) ? null : Inflector::classify($columnClass);
		$entityClass =  $columnClass  != null ? $columnClass : $primaryRole['modelName'];
		$phpClassName = self::getPhpClassName($primaryRole['modelName'], $columnClass);
		// pr("class name:");
		// pr($phpClassName);
		$reflectionClass = self::getReflectionClass($phpClassName);
		$entity = $reflectionClass->newInstanceArgs(array($roles));
		$entity->phpClassName = $phpClassName;
		$entity->entityClassName = $entityClass;
		if ($initArguments != null) {
			$entity->init($initArguments);
		}
		foreach($roles as $role) {
			self::$roleIdEntityMap[$role['roleId']] = $entity;
		}
		return $entity;			
	}
	
	// Load an existing entity by using Entity::get('TestCase', $id);
	public static function get($modelName, $id, $data = null) {
		// pr("=== Get a new entity ===");
		// pr($modelName);
		// pr($id);
		// pr($data);
		// pr("--");
		$roleId = self::createRoleId($modelName, $id);
		// pr($roleId);
		if (isset(self::$roleIdEntityMap[$roleId])) {
			// pr("Existing");
			return self::$roleIdEntityMap[$roleId];
		} else {
			// pr("New one");
			$model = self::getModel($modelName);
			// pr($model);
			
			// Search for more specific model (recursivley)
			if(!empty($model->extendTo)) {
				$relatedDatas = array();
				foreach($model->extendTo as $relatedModelAlias) {
					$relatedModel = self::getModel($model->getAssociated($distinctModelAlias)['className']);	
					$relatedDatas[$relatedModelAlias] = array('data' => self::getRelatedData($model, $id, $model->extendTo, $relatedModel), 'model' => $relatedModel);
				}
				foreach($relatedDatas as $extendToAlias => $relatedData) {
					if ($relatedData['data'] != null) {
						$relatedModelName = $model->getAssociated($extendToAlias)['className'];
						$entity = self::get($relatedModelName, $relatedData['data'][$relatedData['model']->primaryKey], $relatedData['data']);
						self::$roleIdEntityMap[$roleId] = $entity;
						return $entity;
					}
				}
			} else {
				// Get models
				$roles = array();
				self::getModels($modelName, $id, $roles);
				$data = $roles[0]['data'];
						
				// Create Entity
				return self::createEntityObjectFromRoles($roles);
			}
		}
	}
	
	
	public static function getModels($modelName, $primaryKeyValue, &$roles) {
		$model = self::getModel($modelName);
		if ($model->extends != null) {
			$model->contain($model->extends);
		} else {
			$model->contain();			
		}
		
		// Create role
		$roles[] = self::createRole($model, $primaryKeyValue);

		// Create the less specific model
		if ($model->extends != null) {
			$relatedModelName = $model->getAssociated($model->extends)['className'];
			$relatedModel = self::getModel($relatedModelName);
			$relatedModelPrimaryKeyValue = $data[$model->extends][$relatedModel->primaryKey];
			self::getModels($relatedModelName, $relatedModelPrimaryKeyValue, $roles);
		}
	}
	                                          
	
	// Create a new one by Entity::create('TestCase', $initArguments, 'some_specific_class');
	public static function create($modelName, $options = array()) {
		// pr($options);
		$class = get($options, 'class');
		$initArguments = get($options, 'initArguments', array());
		
		// Setup roles
		$data = null;
		if ($class != null) {
			$data = array('class' => $class);
		} else {
			$data = array();
		}
		// pr($data);
		// Create roles
		$roles = array();
		self::createRoles($modelName, $data, $roles);
		// pr($roles);
		// Create entity object. 
		return self::createEntityObjectFromRoles($roles, $initArguments);
	}
	
	
	public static function createRoles($modelName, $data, &$roles) {
		// Create entry in database
		// pr($modelName);
		$model = self::getModel($modelName);
		// pr($model);

		// die;
		$model->create();
		if ($data != null) {
			$model->save($data);
		} else {
			$model->save();			
		}
		
		// Create role
		$roles[] = self::createRole($model, $model->id);

		// Create the less specific role
		// pr($model->extends);
		if ($model->extends != null) {
			// pr("Found associated!");
			$relatedModelName = $model->getAssociated($model->extends)['className'];
			self::createRoles($relatedModelName, null, $roles);
		}
	}
	
	public static function createRole($model, $primaryKeyValue) {
		$data = self::getPrimitiveData($model, $primaryKeyValue); // Load data, for synchronization (with databases default values)

		// Create roleId
		$roleId = self::createRoleId($model->name, $primaryKeyValue);
		
		return array(
			'roleId' => $roleId, 
			'modelName' => $model->name, 
			'model' => $model, 
			'primaryKeyValue' => $primaryKeyValue, 
			'data' => $data['primitiveData'],
			'foreignKeys' => $data['foreignKeys']);
	}
	
	public static function createCopyWithoutRelations($copiedEntity, &$roleIdNewRoleMap) {
		$roles = array();
		foreach($copiedEntity->roles as $copiedRole) {
			$primaryKeyValue = self::constructPrimitive($copiedRole['model'], $copiedRole['data']);
			$newRole = array(
				'roleId' => self::createRoleId($copiedRole['modelName'], $primaryKeyValue),
				'modelName' => $copiedRole['modelName'],
				'model' => $copiedRole['model'],		
				'primaryKeyValue' => $primaryKeyValue,
				'data' => copyArray($copiedRole['data']),
				'foreignKeys' => array()
			);
			$roleIdNewRoleMap[$copiedRole['roleId']] = $newRole;
			$roles[] = $newRole;
		}

		// Create entity object. 
		return self::createEntityObjectFromRoles($roles);
	}
	

	
	/**
	* Dynamic base
	*/
	
	
	/**
	* Model info has the form:
	*  
	* array(
	*   'roleId' => $modelName . ":" . $id
	*   'modelName' => $modelName
	*	'model' => $model, 
	*	'primaryKeyValue' => $primaryKeyValue, 
	*	'data' => $data
	*   'foreignKeys' => $foreignKeys
	*/
	public $roles = null;
	public $className = null;
	public $dataCache = null;
	public $relationCache = null;
		
	public function __construct($roles) {
		$this->roles = $roles;
		$this->dataCache = array();
		$this->relationCache = array();
	} 
	
	
	/**
	* Initializer
	*/
	
	// To initalize an entity use init instead of overriding the constructor. This is because the constructor runs every time the entity is loaded from the database. Hence, constructive work needs to be done in a special 'init' function
	public function init() {}


	/**
	* Basic properties
	*/
	
	public function entityId() {
		return $this->roles[0]['roleId'];
	}
	
	public function getPrimaryKeyForModel($modelName) {
		foreach($this->roles as $role) {
			if ($role['modelName'] == $modelName) {
				return $role['primaryKeyValue'];
			} 
		}
	}

	public function getRole($modelName) {
		foreach($this->roles as $role) {
			if ($role['modelName'] == $modelName) {
				return $role;
			} 
		}
	}
	
	public function getMergedId() {
		$result = null;
		foreach($this->roles as &$role){
			if ($result == null) {
				$result = $role['primaryKeyValue'];
			} else {
				$result .= ", " . $role['primaryKeyValue'];
			}
		}
		return $result;
	}

	
	/**
	* Selection
	*/
	public function normalizeSelection(&$selection) {
		foreach($selection as $key => $entity) {
			// Just check the first key and value pair.
			if (is_int($key)) {
				foreach($selection as $key => $entity) {
					$selection[$entity->entityId()] = $entity;
					unset($selection[$key]);
				}
			}
			return $selection; // No integer keys, normalized already
		}		
	}
	
	public function extendSelection(&$selection, $steps = 1) {
		if ($steps > 0) {
			$this->normalizeSelection($selection);		

			foreach($selection as $id => $selected) {
				$selected->extendSelectionStepsThisNotIncluded($selection, $steps);
			}
		}
		return $selection;
	}
	
	public function extendSelectionSteps(&$selection, $steps) {
		$selection[$this->entityId()] = $this;
		$this->extendSelectionStepsThisNotIncluded($selection, $steps);
	}
	
	public function extendSelectionStepsThisNotIncluded(&$selection, $steps) {
		if ($steps != 0) {
			// Search through models to find table data or relation. 
			foreach($this->roles as $role) {
				$model = $role['model'];
			
				// Map belongs to models
				foreach($model->belongsTo as $modelAlias => $association) {
					$this->extendSelectionInAssociated($this->{$modelAlias}(), $selection, $steps - 1);
				}

				// Map has one models
				foreach($model->hasOne as $modelAlias => $association) {
					$this->extendSelectionInAssociated($this->{$modelAlias}(), $selection, $steps - 1);
				}
				// Map has many models
				foreach($model->hasMany as $modelAlias => $association) {
					$this->extendSelectionInAssociated($this->{$modelAlias}(), $selection, $steps - 1);
				}

				//Map has and belongs to models
				foreach($model->hasAndBelongsToMany as $modelAlias => $association) {
					$this->extendSelectionInAssociated($this->{$modelAlias}(), $selection, $steps - 1);
				}
			}	
		}
	}
	
	public function extendSelectionInAssociated($value, &$selection, $steps) {
		// $this->printRelationValue($value);
		if ($value != null) {
			if (is_array($value)) {
				foreach($value as $entity) {
					$entity->extendSelectionSteps($selection, $steps);
				}
			} else if (is_a($value, 'Entity')) {
				$value->extendSelectionSteps($selection, $steps);
			}
		}
	}
	
		
	public function selectCached(&$idEntityMap = array()) {
		if (!isset($idEntityMap[$this->entityId()])) {
			$idEntityMap[$this->entityId()] = $this;
			
			foreach($this->relationCache as $relationName => $cachedRelation) {
				if (is_object($cachedRelation)) {
					$cachedRelation->selectCached($idEntityMap);
				} else if (is_array($cachedRelation)) {
					foreach($cachedRelation as $relatedEntity) {
						$relatedEntity->selectCached($idEntityMap);
					}
				}
			}
		}
		return $idEntityMap;
	}

	
	public function selectDependent(&$idEntityMap = array()) {
		if (!isset($idEntityMap[$this->entityId()])) {
			// Add this to selection
			$idEntityMap[$this->entityId()] = $this;
			
			// Search through models to find table data or relation. 
			foreach($this->roles as $role) {
				$model = $role['model'];
			
				// Map belongs to models
				foreach($model->belongsTo as $modelAlias => $association) {
					if (isset($association['dependent']) && $association['dependent']) {
						$this->selectDependentInAssociated($this->{$modelAlias}(), $idEntityMap);
					}
				}

				// Map has one models
				foreach($model->hasOne as $modelAlias => $association) {
					if (isset($association['dependent']) && $association['dependent']) {
						$this->selectDependentInAssociated($this->{$modelAlias}(), $idEntityMap);
					}
				}
				// Map has many models
				foreach($model->hasMany as $modelAlias => $association) {
					if (isset($association['dependent']) && $association['dependent']) {
						// pr($modelAlias);
						$this->selectDependentInAssociated($this->{$modelAlias}(), $idEntityMap);
					}
				}

				//Map has and belongs to models
				foreach($model->hasAndBelongsToMany as $modelAlias => $association) {
					if (isset($association['dependent']) && $association['dependent']) {
						$this->selectDependentInAssociated($this->{$modelAlias}(), $idEntityMap);
					}
				}
			}	
		}
		return $idEntityMap;
	}

	public function selectDependentInAssociated($value, &$idEntityMap = array()) {
		// $this->printRelationValue($value);
		if ($value != null) {
			if (is_array($value)) {
				foreach($value as $entity) {
					$entity->selectDependent($idEntityMap);
				}
			} else if (is_a($value, 'Entity')) {
				$value->selectDependent($idEntityMap);
			}
		}
	}

	
	
	/**
	* General copy functionality
	*/
	public function copy($selection = null) {
		if ($selection == null) {
			return $this->copyDependent(0);
		} else {
			return $this->copySelection($this->normalizeSelection($selection));
		}
	}
	
	// public function extendSelection(&$selection, $steps = 1) {
		// $this->normalizeSelection($selection);
		// while ($steps-- > 0) { 
			// $extension = array();
			// foreach($selection as $entityId => $entity) {
				// foreach($entity->allRelated() as $related) {
					// $extension[] = $related;
				// }
			// }
			// foreach ($extension as $extendedEntity) {
				// $selection[$extendedEntity->entityId()] = $extendedEntity;
			// }
		// }
	// }
	
	// public function allRelated() {
		// TODO
		// return array();
	// }
	
	public function copyDependent($extendSteps = 0) {
		$selection = $this->selectDependent();
		$selection = $this->extendSelection($selection, $extendSteps);
		return $this->copySelection($selection);
	}

	
	public function copyCached() {
		return $this->copySelection($this->selectCached());
	}
	
		
	public function copySelection($idEntityMap) {		
		// Create copies and insert them in an id map
		$copyOfThis = null;
		$roleIdNewRoleMap = array();
		foreach($idEntityMap as $entityId => $entity) {
			// pr("Create copy without relations");
			// pr($entityId);
			// pr($entity->entityId());
			$entityCopy = self::createCopyWithoutRelations($entity, $roleIdNewRoleMap);
			if ($entityId == $this->entityId()) {
				$copyOfThis = $entityCopy;
			}
		}
			
		// Link all objects together
		foreach($idEntityMap as $entityId => $entity) {
			foreach($entity->roles as $role) {	
				$newRole = &$roleIdNewRoleMap[$role['roleId']];
				foreach($role['model']->belongsTo as $modelAlias => $options) {
					$modelName = $options['className'];
					$foreignKeyName = $options['foreignKey'];
					// $modelName = $role['model']->getAssociated($modelAlias)['className'];

					
					$belongToRoleId = self::createRoleId($role['modelName'], $role['foreignKeys'][$foreignKeyName]);
					// pr("Belongs to: " . $belongToRoleId);
					// pr($role['foreignKeys']);
					$foreignKeyValue = null;
					if (isset($roleIdNewRoleMap[$belongToRoleId])) {
						// Point to a new role
						$foreignKeyValue = $roleIdNewRoleMap[$belongToRoleId]['primaryKeyValue'];
					} else {
						// Point to the same old role
						$foreignKeyValue = $role['foreignKeys'][$foreignKeyName];
					}

					// Set the foreign key of the copy
					$roleIdNewRoleMap[$role['roleId']]['foreignKeys'][$foreignKeyName] = $foreignKeyValue;
				}
					
				// Save the newly written foreign keys.
				$newRole['model']->{$newRole['model']->primaryKey} = $newRole['primaryKeyValue'];
				$newRole['model']->save($newRole['foreignKeys']);
			}
		}
		
		return $copyOfThis;
	}
	
	
	/**
	* Serialize
	*/
	public function serialize($selection = null) {
		if ($selection == null) {
			return $this->serializeDependent(0);
		} else {
			return $this->serializeSelection($this->normalizeSelection($selection));
		}
	}
	
	
	public function serializeDependent($extendSteps = 0) {
		$selection = $this->selectDependent();
		$selection = $this->extendSelection($selection, $extendSteps);
		return $this->serializeSelection($selection);
	}

	
	public function serializeCached() {
		$selection = $this->selectCached();
		return $this->serializeSelection($selection);
	}
	
	public function rolesSummary() {
		$result = "";
		foreach($this->roles as $role) {
			if ($result != "") {
				$result .= "; ";
			}
			$result .= $role['modelName'] . ":" . $role['primaryKeyValue'];
		}
		return $result;
	}
	
	public function serializeWithoutRelations() {
		$mergedData = array('class' => null, 'phpEntityClass' => null);
		
		// if (count($this->roles) == 1) {
			// $mergedData[$this->roles[0]['model']->primaryKey] = $this->getMergedPrimaryKey();
			// $mergedData['model'] = $this->roles[0]['modelName'];
		// } else {
			$mergedData['roles'] = $this->rolesSummary();			
		// }
		
		$index = count($this->roles);
		while($index) {
			// pr($this->roles[$index - 1]['data']);
			$mergedData = array_merge($mergedData, $this->roles[--$index]['data']);
		}
		$mergedData['class'] = $this->entityClassName;
		$mergedData['phpEntityClass'] = $this->phpClassName;
		return $mergedData;
	}
	
	
	
	public function serializeAssociated($value, &$idEntityMap, &$visitedSet) {		
		if ($value == null) {
			return null;
		} else if (is_a($value, 'Entity')) {
			if (isset($idEntityMap[$value->entityId()])) {
				return $value->serializeSelection($idEntityMap, $visitedSet);
			} else {
				return '[[excluded]]';
			}	
		} else if (is_array($value)) {
			$serializedRelation = array();
			foreach($value as $entity) {
				if (isset($idEntityMap[$entity->entityId()])) {
					$serializedRelation[] = $entity->serializeSelection($idEntityMap, $visitedSet);
				} else {
					$serializedRelation[] = '[[excluded]]';
				}	
			}
			return $serializedRelation;
		}
	}
	
	
	public function serializeSelection(&$idEntityMap, &$visitedSet = array()) {
		$entityId = $this->entityId();
		if (isset($visitedSet[$entityId])) {
			return $entityId;
		} else {
			// pr("Serialize:" . $entityId);
			$visitedSet[$entityId] = true;

			$mergedData = $this->serializeWithoutRelations();			

			// Search through models to find table data or relation. 
			foreach($this->roles as $role) {
				$model = $role['model'];
			
				// Map belongs to models
				foreach($model->belongsTo as $modelAlias => $association) {
					if (!in_array($modelAlias, $model->extendTo) && $model->extends != $modelAlias) {
						$mergedData[$modelAlias] = $this->serializeAssociated($this->{$modelAlias}(), $idEntityMap, $visitedSet);
					}
				}

				// Map has one models
				foreach($model->hasOne as $modelAlias => $association) {
					// pr("Has one");
					// pr($modelAlias);
					// pr($model->extendTo);
					// pr($model->extends);
					if (!in_array($modelAlias, $model->extendTo) && $model->extends != $modelAlias) {
						$mergedData[$modelAlias] = $this->serializeAssociated($this->{$modelAlias}(), $idEntityMap, $visitedSet);
					}
				}
				
				// Map has many models
				foreach($model->hasMany as $modelAlias => $association) {
					if (!in_array($modelAlias, $model->extendTo) && $model->extends != $modelAlias) {
						$mergedData[$modelAlias] = $this->serializeAssociated($this->{$modelAlias}(), $idEntityMap, $visitedSet);
					}
				}

				//Map has and belongs to models
				foreach($model->hasAndBelongsToMany as $modelAlias => $association) {
					if (!in_array($modelAlias, $model->extendTo) && $model->extends != $modelAlias) {
						$mergedData[$modelAlias] = $this->serializeAssociated($this->{$modelAlias}(), $idEntityMap, $visitedSet);
					}
				}
			}	
	
			return $mergedData;
		}
	}
	
	public function serializeCachedRelations(&$visitedSet = array()) {
		// pr("Serialize");
		$entityId = $this->roles[0]['roleId'];
		// pr($visitedSet);
		if (isset($visitedSet[$entityId])) {
			return $entityId;
		} else {
			// pr("Serialize:" . $entityId);
			$visitedSet[$entityId] = true;
			// pr($visitedSet);

			$mergedData = $this->serializeWithoutRelations();			
			
			// pr($mergedData);
			// pr("start" . $entityId);
			foreach($this->relationCache as $relationName => $cachedRelation) {
				// pr("Relation name in serialize:");
				// pr($relationName);
				// pr($cachedRelation);
				if (is_object($cachedRelation)) {
					$mergedData[$relationName] = $cachedRelation->serializeCached($visitedSet);
				} else if (is_array($cachedRelation)) {
					$relatedEntities = array();
					foreach($cachedRelation as $relatedEntity) {
						$relatedEntities[] = $relatedEntity->serializeCached($visitedSet);
					}
					$mergedData[$relationName] = $relatedEntities;
				}
			}
			// pr("end");
			// pr("Finished serialize:" . $entityId);
			// pr($mergedData);
			return $mergedData;
		}
	}
	



	
	/**
	* Print for debug purposes
	*/
	public static function pr($data) {
		pr(self::serializeData($data));
	}
	
	public static function serializeData($data) {
		if (is_array($data)) {
			$result = array();
			foreach($data as $entity) {
				$result[] = self::serializeData($entity);
			}
			return $result;
		} else if(is_object($data)) {
			return $data->serializeCachedRelations();
		} else {
			return $data;
		}
	}
	
	public function printRoles() {
		$printedRoles = array();
		foreach($this->roles as $role) {
			$printedRoles[] = array(
				'roleId' => $role['roleId'],
				'modelName' => $role['modelName'],
				'primaryKeyValue' => $role['primaryKeyValue'],
				'data' => $role['data'],
				'foreignKeys' => $role['foreignKeys']
			);
		}
		$printedRelationCache = array();
		foreach($this->relationCache as $relation => $value) {
			if (is_object($value)) {
				$printedRelationCache[$relation] = $value->entityId();
			} else if(is_array($value)) {
				$printedSet = array();
				foreach($value as $entity) {
					if (is_a($entity, 'Entity')) {
						$printedSet[] = $entity->entityId();
					} else {
						$printedSet[] = $entity;
					}
				}
				$printedRelationCache[$relation] = $printedSet;
			}
		}
		
		$data = array(
			'entityClass' => $this->entityClassName, 
			'phpClass' => $this->phpClassName, 
			'entityId' => $this->entityId(), 
			'primaryKey' => $this->getMergedPrimaryKey(),
			'roles' => $printedRoles,
			'relations' => $printedRelationCache);				
		pr($data);
	}
	
	
	public function printSelection($selection) {
		if(is_array($selection)) {
			$result = array();
			foreach($selection as $id => $entity) {
				$result[] = $id;
			}
		} else {
			$result = "Selection is not an array";
		}
		pr("Selection:");
		pr($result);
	}
	
	
	public function printRelationValue($value) {
		$result = null;
		if (is_array($value)) {
			$result = array();
			foreach($value as $entity) {
				if (is_a($entity, 'Entity')) {
					$result[] = $entity->entityId();
				} else {
					$result[] = 'not an enity in relation!';
				}
			}
		} else if (is_a($value, 'Entity')) {
			$result = $value->entityId();
		} else {
			$result = "Unknown relation value";
		}
		pr("Relation value:");
		pr($result);
	}
	
	/**
	* Direct manipulation API:
	*/
	
	// public function foreignChangeInBelongsToRelation($modelName, $id, $relatedModelName) {
		// $role = $this->role($modelName);
		// foreach($role['model']->getAssociationsToModel($relatedModelName) as $relation) {
			// if (isset($this->relationCache[$relation])) {
				// unset($this->relationCache[$relation]);
			// }
		// };
	// }	
		
	/**
	* Generic Entity call that makes it possible to make calls like:
	* 
	* $entity->someProperty();
	*
	* $entity->RelatedModel(); 
	*/
	public function __call($method, $args)
    {
		// pr(" -- called " . $method);
		// pr($this->entityId());
		// Optimized getters
		if (isset($this->dataCache[$method])) {
			return $this->dataCache[$method];
		}
		if (isset($this->relationCache[$method])) {
			return $this->relationCache[$method];
		}
		
		// Analyze command 
		$command = null;
		$propertyOrRelation = null;
		if (startsWith($method, "set") && strlen($method) > 3 && ctype_upper(substr($method, 3, 1))) {
			$propertyOrRelation = substr($method, 3);
			$command = "set";
		} else if (startsWith($method, "add") && strlen($method) > 3 && ctype_upper(substr($method, 3, 1))) {
			$propertyOrRelation = substr($method, 3);
			$command = "add";
		} else if (startsWith($method, "remove") && strlen($method) > 6 && ctype_upper(substr($method, 6, 1))) {
			$propertyOrRelation = substr($method, 6);
			$command = "remove";
		} else {
			$propertyOrRelation = $method;
			$command = "get";
		}
		// pr("PropertyOrRelation: " . $propertyOrRelation);
		// pr("Command: " . $command);
		
		// Search through models to find table data or relation. 
		foreach($this->roles as &$role) {
			// pr("Call: " . $method);
			$model = $role['model'];
			$modelName = $role['modelName'];
			$primaryKeyValue = $role['primaryKeyValue'];
			$roleData = &$role['data'];
			
			// Is it id property.
			// if($propertyOrRelation == "id") {
				// TODO: What to return? Entity id?
			// }
			
			// Is it a property.
			$tableColumnName = Inflector::underscore($propertyOrRelation);
			if (isset($roleData[$tableColumnName])){
				if ($command == 'get') {
					return $roleData[$tableColumnName];			
				}
				
				if ($command == 'set'){
					$newValue = $args[0];
					$roleData[$tableColumnName] = $newValue;
					$model->{$model->primaryKey} = $primaryKeyValue;
					$model->saveField($tableColumnName, $newValue);
					$this->dataCache[$tableColumnName] = $newValue;
					return;
				}
			}
			
			// Is it a relation?
			// pr(array_roleIds($model->getAssociated()));
			$relatedModelAlias = $propertyOrRelation;
			// pr("Related model alias: " .  $relatedModelAlias);
			if (isset($model->getAssociated()[$relatedModelAlias])) {
				$associationType = $model->getAssociated()[$relatedModelAlias];
				
				// Filter out internal relations within the same object
				if (!in_array($relatedModelAlias, $model->extendTo) && $model->extends != $relatedModelAlias) {
					$relatedRoleAssociation = $model->getAssociated($relatedModelAlias);
					$relatedModelName = $relatedRoleAssociation['className'];
					$relatedModel = self::getModel($relatedModelName);

					// Get associated through relation
					if ($command == 'get') {
						// pr($primaryKeyValue);
						$relatedData = self::getRelatedData($model, $primaryKeyValue, $relatedModelAlias, $relatedModel);
						$associated = null;
						// pr($relatedData);
						if ($relatedData === null) {
							$associated = null;
						} else if(isset($relatedData[$relatedModel->primaryKey])) {
							//Single relation
							$associated = self::get($relatedModelName, $relatedData[$relatedModel->primaryKey], $relatedData);
						} else {
							//Set relation
							$associated = array();
							foreach($relatedData as $relatedEntityData) {
								$associated[] = self::get($relatedModelName, $relatedEntityData[$relatedModel->primaryKey], $relatedEntityData);
							}
						}
						$this->relationCache[$method] = $associated;
						return $associated;
					} 

					// Set associated entity for this relation (... to one relation)
					if ($command == 'set') {
						$newValue = $args[0];
						if ($associationType == 'belongsTo') {
							// Store abandoned value
							$oldValue = $this->{$relatedModelAlias}();
							$oldRelatedId = ($oldValue != null) ? $oldValue->getPrimaryKeyForModel($relatedModelName) : null;

							// Change this entity/model to point to new
							$newRelatedId = ($newValue != null) ? $newValue->getPrimaryKeyForModel($relatedModelName) : null;
							$model->{$model->primaryKey} = $primaryKeyValue;
							$role['foreignKeys'][$relatedRoleAssociation['foreignKey']] = $newRelatedId;
							$model->saveField($relatedRoleAssociation['foreignKey'], $newRelatedId);
	
							// Clear caches for peers.
							$mirrorRelation = self::getMirrorRelation($relatedModel, $model->name, $relatedModelAlias, $relatedRoleAssociation['foreignKey']);
							$mirrorRelationName = $mirrorRelation['relationName'];
							if ($oldValue != null && isset($oldValue->relationCache[$mirrorRelationName])) {
								unset($oldValue->relationCache[$mirrorRelationName]);
							}
							if ($newValue != null && isset($newValue->relationCache[$mirrorRelationName])) {
								unset($newValue->relationCache[$mirrorRelationName]);
							}
						} else if ($associationType == 'hasMany') {
							$oldValue = $this->{$relatedModelAlias}();
							$mirrorRelation = self::getMirrorRelation($relatedModel, $model->name, $relatedModelAlias, $relatedRoleAssociation['foreignKey']);
							$mirrorRelationName = $mirrorRelation['relationName'];
							foreach($oldValue as $oldRelated) {
								$oldRelated->{'set' . $mirrorRelationName}(null);
							}
							foreach($newValue as $newRelated) {
								$newRelated->{'set' . $mirrorRelationName}($this);
							}
						} else if ($associationType == 'hasOne') {
							$oldValue = $this->{$relatedModelAlias}();

							$mirrorRelation = self::getMirrorRelation($relatedModel, $model->name, $relatedModelAlias, $relatedRoleAssociation['foreignKey']);
							$mirrorRelationName = $mirrorRelation['relationName'];

							$oldValue->{'set' . $mirrorRelationName}(null);
							$newValue->{'set' . $mirrorRelationName}($this);
						} else if ($associationType == 'hasAndBelongsToMany') {
							pr("This part of the code is not tested yet, continue at own risk.");
							die;
							$oldValue = $this->{$relatedModelAlias}();
							$newIdArray = array();
							foreach($newValue as $newEntity) {
								$newIdArray[] = $newEntity->getrole($relatedModelName)['primaryKeyValue'];
							}
							$data = array($modelName => array($modelName => $newIdArray));
							$data[$model->primaryKey] = $role['primaryKeyValue'];
							$model->save($data);
							foreach(self::getAssociationsToModel($relatedModel, $model->name) as $relation) {
								foreach($oldValue as $oldEntity) {
									if (isset($oldEntity->relationCache[$relation])) {
										unset($oldEntity->relationCache[$relation]);
									}									
								}
								foreach($newValue as $newEntity) {
									if (isset($newEntity->relationCache[$relation])) {
										unset($newEntity->relationCache[$relation]);
									}									
								}
							}
						}
						// pr($role['foreignKeys']);
						$this->relationCache[$relatedModelAlias] = $newValue;
						return null;
					}
					
					// Add associated entity for this relation (... to many relation)
					if ($command == 'add') {
						$addedEntity = $args[0];
						$newRelatedEntities = array_merge($this->{$relatedModelAlias}(), array($addedEntity));
						// $this->printRelationValue($newRelatedEntities);
						// die();
						$this->{'set' . $relatedModelAlias}($newRelatedEntities);
						return null;
					}

					// Remove associated entity for this relation (... to many relation)
					if ($command == 'remove') {
						$removedEntity = $args[0];
						$newRelatedEntities = array();
						foreach($this->{$relatedModelAlias}() as $relatedEntity) {
							if ($relatedEntity != $removedEntity) {
								$newRelatedEntities[] = $relatedEntity;
							}
						}
						$this->{'set' . $relatedModelAlias}($newRelatedEntities);
						return null;
					}
				}
			}
		}
		return "Unknown property or relation '" . $method . "'";
    }	
}

