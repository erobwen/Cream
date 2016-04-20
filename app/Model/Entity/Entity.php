<?php
/**
 *  "Cream" - Object Relational Model, Written by Robert Wensman
 */

/**
 * Notes:
 * Bevare of translation where there are numbers in fields. For example fooBar1 translates to foo_bar1 and NOT foo_bar_1
 * Beware of not defining the backwards going lingks in an inheritance chain. Both in the relation and the extend to.
 */
App::uses('Change', 'Model/Entity');

/**
 * Convenience interface
 */
// public function createEntity($modelName, $class = '', $customOptionalArguments=array()) {
// return Entity::create($modelName, $class, $customOptionalArguments);
// }
// public function getEntity($modelName, $id, $data = null) {
// return Entity::get($modelName, $id, $data);
// }

if ( !function_exists('startsWith') )
{
    function startsWith($haystack, $needle)
    {
        // search backwards starting from haystack length characters from the end
        return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
    }
}

if ( !function_exists('endsWith') )
{
    function endsWith($haystack, $needle)
    {
        // search forward starting from end minus needle length characters
        return $needle === "" ||
        (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
    }
}

if ( !function_exists('get') )
{
    function get($array, $key, $default = null)
    {
        if ( isset($array[$key]) )
        {
            return $array[$key];
        }
        else
        {
            return $default;
        }
    }
}

if ( !function_exists('pre') )
{ // Printout a serialized version of an entity, for debugging application.
    function pre($data)
    {
        if ( is_a($data, 'Entity') )
        {
            pr($data->serialize());
        }
    }
}

if ( !function_exists('pred') )
{ // Printout entities in debug mode without bloating the printout with the models.  For debugging Cream itself.
    function pred($data)
    {
        pr(filterModels($data));
        // Entity::printDebug($data);
    }
}

if ( !function_exists('filterModels') )
{
    function filterModels($data, $recursiveLevels = 4)
    {
        if ( $recursiveLevels == 0 )
        {
            return "[[End of recursion]]";
        }
        else
        {
            if ( is_array($data) )
            {
                $newArray = [];
                foreach ( $data as $key => $value )
                {
                    $newArray[$key] = filterModels($value, $recursiveLevels - 1);
                }
                return $newArray;
            }
            else
            {
                if ( is_object($data) )
                {
                    if ( $data instanceof Model )
                    {
                        return "[Model]";
                    }
                    else
                    {
                        $newArray = ['Object' => true];
                        if ( $data instanceof Entity )
                        { // Put them up front!
                            $newArray['entityClassName'] = $data->entityClassName;
                            $newArray['phpClassName'] = $data->phpClassName;
                        }
                        foreach ( $data as $key => $value )
                        {
                            $newArray[$key] = filterModels($value, $recursiveLevels - 1);
                        }
                        return $newArray;
                    }
                }
                else
                {
                    return $data;
                }
            }
        }
    }
}

if ( !function_exists('copyArray') )
{
    function copyArray($original)
    {
        $new = [];
        foreach ( $original as $key => $value )
        {
            $new[$key] = $value;
        }
        return $new;
    }
}

if ( !function_exists('mergeRefinement') )
{
    function mergeRefinement(&$destination, &$additionalInfo)
    {
        if ( $destination === true )
        {
            return $additionalInfo;
        }
        elseif ( $additionalInfo === true )
        {
            return $destination;
        }
        else
        {
            foreach ( $additionalInfo as $key => $value )
            {
                if ( true || $key != 'selectTags' )
                {
                    if ( !isset($destination[$key]) )
                    {
                        $destination[$key] = $value;
                    }
                    else
                    {
                        $destination[$key] = mergeRefinement($destination[$key], $additionalInfo[$key]);
                    }
                }
            }
            return $destination;
        }
    }
}

/**
 * Entity
 */
class Entity
{
    /**
     * Model helpers. Most of these could really reside in the app model, but for increased portability they are here.
     */
    public static function getModel($modelName)
    {
        $model = null;
        if ( ClassRegistry::isKeySet($modelName) )
        {
            $model = ClassRegistry::getObject($modelName);
        }
        else
        {
            $model = ClassRegistry::init($modelName);
        }
        if ( !property_exists($model, 'extends') )
        {
            $model->{'extends'} = null;
        }
        if ( !property_exists($model, 'extendTo') )
        {
            $model->{'extendTo'} = [];
        }
        return $model;
    }

    // Index model
    public static $Index = null;

    // Models required for logging
    public static $Change = null;
    public static $User = null;
    public static $logEvents = true;
    public static $requestChanges = [];

    public static function registerEvent($type, $entity, $fieldOrRelation, $newValue)
    {
        if ( self::$logEvents )
        {

            // Prepare data
            $userId = CakeSession::read('Auth.User')['User']['username']; // TODO: make safe when not applicable.
            $serializedValue = null;
            $idListValue = null;
            if ( $newValue !== null )
            {
                if ( is_array($newValue) )
                {
                    $entityIds = [];
                    foreach ( $newValue as $relatedEntity )
                    {
                        $entityIds[] = $relatedEntity->entityId();
                    }
                    $serializedValue = serialize($entityIds);
                    $idListValue = $entityIds;
                }
                if ( is_a($newValue, 'Entity') )
                {
                    $serializedValue = serialize($newValue->entityId());
                    $idListValue = $newValue->entityId();
                }
                else
                {
                    $serializedValue = serialize($newValue);
                    $idListValue = $newValue;
                }
            }

            // Save in request changes list.
            self::$requestChanges[] = [
                'entityId' => $entity->entityId(),
                'relationOrProperty' => $fieldOrRelation,
                'value' => $idListValue,
            ];

            // Save
            $Change = self::$Change;
            $Change->create();
            $Change->save(
                [
                    'Change' => [
                        'user_id' => $userId,
                        'time' => time(),
                        'entity_id_model' => $entity->entityIdModel(),
                        'entity_id_primary_key' => $entity->entityIdPrimaryKey(),
                        'entity_id_primary_key_value' => $entity->entityIdPrimaryKeyValue(),
                        'type' => $type,
                        'field_or_relation' => $fieldOrRelation,
                        'value' => $serializedValue,
                    ],
                ]
            );
        }
    }

    public static function registerChange($entity, $fieldOrRelation, $newValue)
    {
        self::registerEvent('modify', $entity, $fieldOrRelation, $newValue);
    }

    public static function registerCreation($entity)
    {
        self::registerEvent('create', $entity, null, null);
    }

    public static function registerInitialized($entity)
    {
        self::registerEvent('initialized', $entity, null, null);
    }

    /**
     * Note:
     * If $modelAlias is an array, then this will result in an array with entries like $modelAlias => $modelAliasData
     * If $modelAlias is a string, it will just yield $modelAliasData
     */
    public static function getRelatedData($model, $primaryKeyValue, $modelAlias, $relatedModel)
    {
        // pr("Get related data:" . $model->name . "->" . $modelAlias);
        // $model->contain($modelAlias);
        // pr(array('conditions' => array($model->primaryKey => $primaryKeyValue)));
        // $model->{$model->primaryKey} => $primaryKeyValue;
        $data = $model->find(
            'first',
            [
                'conditions' => [$model->name . "." . $model->primaryKey => $primaryKeyValue],
                'contain' => [$modelAlias => ['fields' => ['*']]],
                'fields' => [$model->name . "." . $model->primaryKey],
            ]
        );
        // prd($data);
        $model->resetBindings();
        // pr("---");

        return self::getRelatedDataFromData($data, $modelAlias, $relatedModel);
    }

    public static function getRelatedDataFromData($data, $modelAlias, $relatedModel)
    {
        if ( isset($data[$modelAlias]) )
        {
            $relatedData = $data[$modelAlias];
            if ( !array_key_exists($relatedModel->primaryKey, $relatedData) )
            {
                // No id, must be a to many relation
                return $relatedData;
            }
            else
            {
                if ( $relatedData[$relatedModel->primaryKey] != null )
                {
                    // A single related entity
                    return $relatedData;
                }
                else
                {
                    return null;
                }
            }
        }
        else
        {
            return null;
        }
    }

    public static function constructPrimitive($model, $data = [])
    {
        $model->create();
        $model->save([$model->alias => $data]);
        return $model->{$model->primaryKey};
    }

    /**
     * Get primitive data
     */
    public static function getPrimitiveData($model, $primaryKeyValue, $data = null)
    {
        if ( $data == null )
        {
            $model->contain();
            $data = $model->find(
                'first',
                ['conditions' => [$model->name . "." . $model->primaryKey => $primaryKeyValue]]
            )[$model->name];
            $model->resetBindings();
        }
        else
        {
            // pr($data);
        }
        // $data = $model->findById($id)[$model->name]; // Load data, for synchronization (with databases default values)

        // Separate primitive data from foreign keys.
        $primaryKeyValue = null;
        $primitiveData = [];
        $foreignKeys = [];
        $columnTypes = $model->getColumnTypes();
        foreach ( $columnTypes as $column => $type )
        {
            if ( $column == $model->primaryKey )
            {
                $primaryKeyValue = $data[$column];
            }
            else
            {
                if ( $model->isForeignKey($column) )
                {
                    $foreignKeys[$column] = $data[$column];
                }
                else
                {
                    if ( isset($data[$column]) )
                    { // and
                        $primitiveData[$column] = $data[$column];
                    }
                    else
                    {
                        $primitiveData[$column] = null;
                    }
                }
            }
        }
        return [
            'primaryKeyValue' => $primaryKeyValue,
            'primitiveData' => $primitiveData,
            'foreignKeys' => $foreignKeys,
        ];
    }

    /*
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
    */

    public function getMirrorRelation($model, $relatedModelName, $sourceRelationName, $foreignKey)
    {
        foreach ( $model->_associations as $assoc )
        {
            if ( !empty($model->{$assoc}) )
            {
                foreach ( $model->{$assoc} as $relationName => $relationInfo )
                {
                    if ( $relationName != $sourceRelationName && $relationInfo['className'] == $relatedModelName &&
                        $relationInfo['foreignKey'] == $foreignKey
                    )
                    {
                        return ['relationName' => $relationName, 'relationInfo' => $relationInfo];
                    }
                }
            }
        }
        // pr("Cream: Failed to get mirror relation that should be in model: " . $model->name . ". Source relation: " . $sourceRelationName);
        // stackdump();

        return null;
    }

    /**
     * Indexes
     */
    public static function getIndex($modelName, $conditions = null)
    {
        // Serialize conditions for storage in database
        $serializedConditions = null;
        if ( $conditions != null )
        {
            $serializedConditions = serialize($conditions);
        }

        if ( !self::$Index->hasAny(['Index.model_name' => $modelName, 'Index.conditions' => $serializedConditions]) )
        {
            // Create a new index!
            return self::create('Index', ['modelName' => $modelName, 'conditions' => $serializedConditions]);
        }
        else
        {
            // Find the index based&nbsp;model/conditions
            $data = self::$Index->find(
                'first',
                ['conditions' => ['model_name' => $modelName, 'conditions' => $serializedConditions]]
            );
            return self::get('Index', $data['Index']['id']);
        }
    }

    /**
     *
     * Roles static services
     *
     */
    public static $roleIdEntityMap = [];
    public static $reflectionClasses = [];

    public static function restart()
    {
        self::$roleIdEntityMap = [];
    }

    public static function createRoleId($modelName, $primaryKey, $primaryKeyValue)
    {
        // return $modelName . ":" . $id;
        return $modelName . "." . $primaryKey . "=" . $primaryKeyValue;
    }

    public static function getReflectionClass($className)
    {
        if ( !isset(self::$reflectionClasses[$className]) )
        {
            self::$reflectionClasses[$className] = new ReflectionClass($className);
        }
        return self::$reflectionClasses[$className];
    }

    public static function getPhpClassName($modelName, $class = null)
    {
        // pr("Get entity cqlass name");
        // pr($modelName);
        // pr($data);
        $elaborateName = null;
        if ( $class != null )
        {
            $elaborateName = $class . "Entity";
        }
        else
        {
            $elaborateName = $modelName . "Entity";
        }
        // pr($elaborateName);
        if ( class_exists($elaborateName) )
        {
            // pr("Exists!");
            return $elaborateName;
        }
        else
        {
            // pr("Sad day");
            return "Entity";
        }
    }

    public static function createEntityObjectFromRoles($roles)
    {
        $primaryRole = $roles[0];
        $columnClass = get($primaryRole['data'], 'class');
        $columnClass = ($columnClass == null) ? null : Inflector::classify($columnClass);
        $entityClass = $columnClass != null ? $columnClass : $primaryRole['modelName'];
        $phpClassName = self::getPhpClassName($primaryRole['modelName'], $columnClass);
        // pr("class name:");
        // pr($phpClassName);
        $reflectionClass = self::getReflectionClass($phpClassName);
        $entity = $reflectionClass->newInstanceArgs([$roles]);
        $entity->phpClassName = $phpClassName;
        $entity->entityClassName = $entityClass;
        // pr($initData);die;
        foreach ( $roles as $role )
        {
            self::$roleIdEntityMap[$role['roleId']] = $entity;
        }
        return $entity;
    }

    // Load an existing entity by using Entity::get('TestCase', $id);
    public static function get()
    {  // $modelName, $id   or entityId
        $arguments = func_get_args();
        $roleId = null;
        $model = null;
        $id = null;
        $modelName = null;

        if ( count($arguments) >= 2 )
        {
            $modelName = $arguments[0];
            $id = $arguments[1];
            $datas = null;
            if ( isset($arguments[2]) )
            {
                $datas = $arguments[2];
            }
            $model = self::getModel($modelName);
            $roleId = self::createRoleId($modelName, $model->primaryKey, $id);
        }
        else
        {
            // prd($arguments);
            $roleId = $arguments[0];
            $subParts = explode("=", $roleId);
            $subSubParts = explode(".", $subParts[0]);
            $modelName = $subSubParts[0];
            $model = self::getModel($modelName);
            $id = $subParts[1];
            $datas = null;
            // pr("=== Get a new entity ===");
            // pr($modelName);
            // pr($id);
            // pr("--");
            // die;
        }
        // pr($roleId);
        if ( isset(self::$roleIdEntityMap[$roleId]) )
        {
            // pr("Existing");
            return self::$roleIdEntityMap[$roleId];
        }
        else
        {
            // pr($modelName);

            $data = null;
            if ( isset($datas[$model->name]) )
            {
                $data = $datas[$model->name];
            }
            else
            {
                $model->contain();
                $data = $model->find(
                    'first',
                    ['conditions' => [$model->name . "." . $model->primaryKey => $id]]
                )[$model->name];
                $model->resetBindings();
                $datas[$model->name] = $data;
            }

            if ( !empty($data['entity_model_name']) )
            {
                // Go to the most specific role directly!
                return self::get($data['entity_model_name'], $data['entity_model_id'], [$model->name => $data]);
            }
            elseif ( !empty($model->extendTo) )
            {
                // Search for more specific model (recursivley and EXTREMLEY slow)
                pr("Gowing down the hierarchy with, this is not recommended " . $modelName . ".id=" . $id);
                stackdump();
                die;
                $relatedDatas = [];
                foreach ( $model->extendTo as $relatedModelAlias )
                {
                    $relatedModel = self::getModel($model->getAssociated($relatedModelAlias)['className']);
                    $relatedDatas[$relatedModelAlias] = [
                        'data' => self::getRelatedData(
                            $model,
                            $id,
                            $relatedModelAlias,
                            $relatedModel
                        ),
                        'model' => $relatedModel,
                    ];
                }
                foreach ( $relatedDatas as $extendToAlias => $relatedData )
                {
                    // pred($extendToAlias);
                    // pred($relatedData);
                    if ( $relatedData['data'] != null )
                    {
                        $relatedModelName = $model->getAssociated($extendToAlias)['className'];
                        $entity = self::get(
                            $relatedModelName,
                            $relatedData['data'][$relatedData['model']->primaryKey],
                            [$model->name => $relatedData['data']]
                        );
                        return $entity;
                    }
                }
                pr(
                    "Cream: Trying to instantiate an abstract model " . $modelName .
                    ". This is not possible. Possibly you have forgotten to add your concrete(r) model in the extendTo list of the model. It could also be because you actually try to instantiate."
                );
                stackdump();
            }
            else
            {
                // Get models
                $roles = [];
                self::getModels($modelName, $id, $datas, $roles);

                // Create Entity
                return self::createEntityObjectFromRoles($roles);
            }
        }
    }

    public static function getModels($modelName, $primaryKeyValue, &$datas, &$roles)
    {
        // pr($modelName);
        $model = self::getModel($modelName);
        // pr($model);
        if ( $model->extends != null )
        {
            $model->contain($model->extends);
        }
        else
        {
            // pr("here");
            // die;
            $model->contain();
        }

        // Data
        $data = null;
        if ( isset($datas[$modelName]) )
        {
            $data = $datas[$modelName];
        }

        // Create role
        $roles[] = self::createRole($model, $primaryKeyValue, $data);

        // Create the less specific model
        if ( $model->extends != null )
        {
            $relatedModelName = $model->getAssociated($model->extends)['className'];
            $relatedModel = self::getModel($relatedModelName);
            $relatedData = self::getRelatedData($model, $primaryKeyValue, $model->extends, $relatedModel);
            $relatedData['info'] = "from related data when getting models";
            $datas[$relatedModelName] = $relatedData;
            // pr($relatedData);
            if ( !isset($relatedData[$relatedModel->primaryKey]) )
            {
                stackdump();
            }
            $relatedModelPrimaryKeyValue = $relatedData[$relatedModel->primaryKey];
            // pr($relatedModelPrimaryKeyValue);
            // pr($relatedModelName);
            // die;
            self::getModels($relatedModelName, $relatedModelPrimaryKeyValue, $datas, $roles);
        }
    }

    // Create a new one by Entity::create('TestCase', $initData, 'some_specific_class');
    public static function create($modelName, $initData = [])
    {
        $entity = self::createUninitialized($modelName, $initData);
        self::initialize($entity, $initData);
        return $entity;
    }

    // Create a new one by Entity::create('TestCase', $initData, 'some_specific_class');
    public static function createUninitialized($modelName, $initData = [])
    {
        // pr($options);
        $class = get($initData, 'class', null);
        unset($initData['class']);

        // Setup roles
        $data = null;
        if ( $class != null )
        {
            $data = ['class' => $class];
        }
        else
        {
            $data = [];
        }
        // pr($data);
        // Create roles
        $roles = [];
        self::createRoles($modelName, null, null, $data, $roles);
        // pr($roles);
        // Create entity object.
        // pr(self::sRoles($roles));
        // die;
        $entity = self::createEntityObjectFromRoles($roles);
        self::registerCreation($entity);
        return $entity;
    }

    public static function initialize($entity, $initData)
    {
        // self::$logEvents = false;
        $entity->init($initData);
        // self::$logEvents = true;
        self::registerInitialized($entity);
        return $entity;
    }

    public static function createRoles($modelName, $entityModelName, $entityModelId, $data, &$roles,
        &$previousRole = null)
    {
        // pr("Create roles:" . $modelName);
        // pr($data);
        // pr(self::sRoles($roles));
        // Create entry in database
        $model = self::getModel($modelName);
        // pr($model);

        // die;
        // $model->clear();
        $model->clear();
        $model->create();
        // if (empty($model->create())) {
        // pr("Cream failed to create a record for the model: " . $modelName . ". This could be because CakePHP does not allow empty records with null values as default to be created (Seriously?), so try adding 'dummy BOOLEAN DEFAULT true' to your database table.");
        // stackdump();
        // }
        if ( !empty($data) )
        {
            $model->save(
                $data
            ); // TODO: Consider, should data here be of the form array('modelName'  => array('fileld'))
        }
        else
        {
            $model->save();
        }
        if ( $model->getLastInsertID() == null )
        {
            pr(
                "Cream failed to create a record for the model: " . $modelName .
                ". This could be because CakePHP does not allow empty records with null values as default to be created (Seriously?), so try adding 'dummy BOOLEAN DEFAULT true' to your database table."
            );
            stackdump();
        }
        // pr($model->getLastInsertID());
        // pr($model->id);
        // Create role
        $role = self::createRole($model, $model->getLastInsertID());
        $roles[] = &$role;
        // pr(self::sRoles($roles));

        if ( $entityModelName == null )
        {
            $entityModelName = $modelName;
            $entityModelId = $model->getLastInsertID();
        }

        if ( $previousRole != null )
        {
            self::connectRoles($previousRole, $role);
        }
        // pr(self::sRoles($roles));

        // Create the less specific role
        if ( $model->extends != null )
        {
            // pr("Found associated!");
            $relatedModelName = $model->getAssociated($model->extends)['className'];
            self::createRoles(
                $relatedModelName,
                $entityModelName,
                $entityModelId,
                ['entity_model_name' => $entityModelName, 'entity_model_id' => $entityModelId],
                $roles,
                $role
            );
        }
    }

    public static function connectRoles(&$moreSpecific, &$lessSpecific)
    {
        // pr("Connect roles");
        $moreSpecificModel = $moreSpecific['model'];
        $forwardAssociationType = $moreSpecificModel->getAssociated()[$moreSpecificModel->extends];
        $forwardAssociation = $moreSpecificModel->getAssociated($moreSpecificModel->extends);
        $lessSpecificModel = $lessSpecific['model'];

        if ( $forwardAssociationType == 'belongsTo' )
        {
            // pr($lessSpecific['primaryKeyValue']);
            // pr($forwardAssociation['foreignKey']);
            // Update foreign keys record
            $moreSpecific['foreignKeys'][$forwardAssociation['foreignKey']] = $lessSpecific['primaryKeyValue'];
            // pr($moreSpecific['foreignKeys']);

            // Save to database
            $moreSpecificModel->{$moreSpecificModel->primaryKey} = $moreSpecific['primaryKeyValue'];
            $moreSpecificModel->saveField($forwardAssociation['foreignKey'], $lessSpecific['primaryKeyValue']);
        }
        else
        {
            if ( $forwardAssociationType == 'hasOne' )
            {
                // Find reverse relation
                $backwardAssociation = null;
                foreach ( $lessSpecificModel->belongsTo as $relationName => $relationInfo )
                {
                    if ( $relationInfo['className'] == $moreSpecific['modelName'] )
                    {
                        $backwardAssociation = $relationInfo;
                    }
                }

                // Update foreign keys record
                $lessSpecific['foreignKeys'][$backwardAssociation['foreignKey']] = $moreSpecific['primaryKeyValue'];

                // Save to database
                $lessSpecificModel->{$lessSpecificModel->primaryKey} = $lessSpecific['primaryKeyValue'];
                $lessSpecificModel->saveField($backwardAssociation['foreignKey'], $moreSpecific['primaryKeyValue']);
            }
            else
            {
                pr("Cannot extend multiple instances of base model! This should never happen!");
                die;
            }
        }
        // pr("---");
    }

    public static function createRole($model, $primaryKeyValue, $data = null)
    {
        $data = self::getPrimitiveData(
            $model,
            $primaryKeyValue,
            $data
        ); // Load data, for synchronization (with databases default values)

        // Create roleId
        $roleId = self::createRoleId($model->name, $model->primaryKey, $primaryKeyValue);

        return [
            'roleId' => $roleId,

            'modelName' => $model->name,
            'primaryKey' => $model->primaryKey,
            'primaryKeyValue' => $primaryKeyValue,

            'model' => $model,

            'data' => $data['primitiveData'],
            'foreignKeys' => $data['foreignKeys'],
        ];
    }

    public static function createCopyWithoutRelations($copiedEntity, &$roleIdNewRoleMap)
    {
        $roles = [];
        foreach ( $copiedEntity->roles as $copiedRole )
        {
            $primaryKeyValue = self::constructPrimitive($copiedRole['model'], $copiedRole['data']);
            $newRole = [
                'roleId' => self::createRoleId(
                    $copiedRole['modelName'],
                    $copiedRole['model']->primaryKey,
                    $primaryKeyValue
                ),

                'modelName' => $copiedRole['modelName'],
                'primaryKeyValue' => $primaryKeyValue,
                'primaryKey' => $copiedRole['model']->primaryKey,

                'model' => $copiedRole['model'],

                'data' => copyArray($copiedRole['data']),
                'foreignKeys' => [],
            ];
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
     *    'model' => $model,
     *    'primaryKeyValue' => $primaryKeyValue,
     *    'data' => $data
     *   'foreignKeys' => $foreignKeys
     */
    public $roles = null;
    public $className = null;
    public $dataCache = null;
    public $relationCache = null;

    public function __construct($roles)
    {
        $this->roles = $roles;
        $this->dataCache = [];
        $this->relationCache = [];
        $this->relationCache = [];
    }


    /**
     * Initializer
     */

    // To initalize an entity use init instead of overriding the constructor. This is because the constructor runs every time the entity is loaded from the database. Hence, constructive work needs to be done in a special 'init' function
    public function init($initData)
    {
        foreach ( $initData as $key => $value )
        {
            foreach ( $this->roles as $role )
            {
                // if ($key == 'modelName') {
                // pr("Here");
                // pred($this);
                // die;
                // }
                if ( array_key_exists(Inflector::underscore($key), $role['data']) )
                {
                    $this->{$key}($value);
                }
                // if ($key == 'modelName') {
                // pr("After");
                // pred($this);
                // die;
                // }
            }
        }
        // die;
    }

    /**
     * Basic properties
     */
    public function entityId()
    {
        return $this->roles[0]['roleId'];
    }

    public function entityIdModel()
    {
        return $this->roles[0]['modelName'];
    }

    public function entityIdPrimaryKey()
    {
        return $this->roles[0]['primaryKey'];
    }

    public function entityIdPrimaryKeyValue()
    {
        return $this->roles[0]['primaryKeyValue'];
    }

    public function getPrimaryKeyForModel($modelName)
    {
        // pr("Get primary key for model ". $modelName);
        foreach ( $this->roles as $role )
        {
            // pr($role['modelName']);
            if ( $role['modelName'] == $modelName )
            {
                return $role['primaryKeyValue'];
            }
        }
        die;
    }

    public function getRole($modelName)
    {
        foreach ( $this->roles as $role )
        {
            if ( $role['modelName'] == $modelName )
            {
                return $role;
            }
        }
    }

    /**
     * Selection
     */
    public function normalizeSelection(&$selection)
    {
        foreach ( $selection as $key => $entity )
        {
            // Just check the first key and value pair.
            if ( is_int($key) )
            {
                foreach ( $selection as $key => $entity )
                {
                    $selection[$entity->entityId()] = $entity;
                    unset($selection[$key]);
                }
            }
            return $selection; // No integer keys, normalized already
        }
    }

    /**
     * Standard serializations
     */
    public function serializeForList()
    {
        $selection = [];
        $this->selectForList($selection);
        return $this->serializeSelection($selection);
    }

    public function serializeRoot()
    {
        $selection = [];
        $this->selectRoot($selection);
        return $this->serializeSelection($selection);
    }

    public function serializeForView()
    {
        $selection = [];
        $this->selectForView($selection);
        return $this->serializeSelection($selection);
    }

    public function serializeForEdit()
    {
        $selection = [];
        $this->selectForEdit($selection);
        return $this->serializeSelection($selection);
    }

    /**
     * Add self to selection
     */
    public function addToSelection(&$selection, $refinement = true)
    {
        $addition = [$this->entityId() => $refinement];
        mergeRefinement($selection, $addition);

        // if ($refinement != null) {
        // if (!isset($selection[$entityId]) || is_bool($selection[$entityId])) {
        // $selection[$entityId] = $refinement;
        // } else if (is_array($selection[$entityId])) {
        // foreach($refinement as $key => $value) {
        // $selection[$entityId][$key] = $value;
        // }
        // }
        // } else if(!isset($selection[$entityId])) {
        // $selection[$entityId] = true;
        // }
    }

    /**
     * Propposed standard selectors.
     */
    public function selectForList(&$selection)
    {
        $this->addToSelection($selection, ['selectTags' => ['ForList' => true]]);
    }

    public function selectRoot(&$selection)
    {
        $this->addToSelection($selection, ['selectTags' => ['Root' => true]]);
    }

    public function selectForView(&$selection)
    {
        // pr("Select for view: " . $this->entityIdModel());
        $this->selectDependentRecursivley($selection, 'selectForView', ['selectTags' => ['ForView' => true]]);
    }

    public function selectForEdit(&$selection)
    {
        $this->selectDependentRecursivley($selection, 'selectForEdit', ['selectTags' => ['ForEdit' => true]]);
    }

    public function selectForCopy(&$selection)
    {
        $this->selectDependentRecursivley($selection, 'selectForCopy', ['selectTags' => ['ForCopy' => true]]);
    }

    /**
     * Select dependent
     */
    public function selectDependent(&$selection = [])
    {
        $this->selectDependentRecursivley($selection, 'selectDependent', true);
    }

    public function selectDependentRecursivley(&$selection = [], $recursiveCall, $refinement)
    {
        // Add this to selection
        $this->addToSelection($selection, $refinement);

        // Search through models to find table data or relation.
        foreach ( $this->roles as $role )
        {
            $model = $role['model'];

            // Map belongs to models
            foreach ( $model->belongsTo as $modelAlias => $association )
            {
                if ( isset($association['dependent']) && $association['dependent'] )
                {
                    $this->selectDependentInAssociated($this->{$modelAlias}(), $selection, $recursiveCall);
                }
            }

            // Map has one models
            foreach ( $model->hasOne as $modelAlias => $association )
            {
                if ( isset($association['dependent']) && $association['dependent'] )
                {
                    $this->selectDependentInAssociated($this->{$modelAlias}(), $selection, $recursiveCall);
                }
            }
            // Map has many models
            foreach ( $model->hasMany as $modelAlias => $association )
            {
                // pr("Select in has many  " . $this->entityId());
                // pr($selection);
                if ( isset($association['dependent']) && $association['dependent'] )
                {
                    // pr($modelAlias);
                    $this->selectDependentInAssociated($this->{$modelAlias}(), $selection, $recursiveCall);
                }
                // pr($selection);
                // pr("Finished for   " . $this->entityId());
            }

            //Map has and belongs to models
            foreach ( $model->hasAndBelongsToMany as $modelAlias => $association )
            {
                if ( isset($association['dependent']) && $association['dependent'] )
                {
                    $this->selectDependentInAssociated($this->{$modelAlias}(), $selection, $recursiveCall);
                }
            }
        }
        return $selection;
    }

    public function selectDependentInAssociated($value, &$selection = [], $recursiveCall)
    {
        // pr($recursiveCall);
        // $this->printRelationValue($value);
        if ( $value != null )
        {
            if ( is_array($value) )
            {
                foreach ( $value as $entity )
                {
                    if ( $entity == null )
                    {
                        pr("An entity was missing in a relation! Verify entity creation");
                        die;
                    }
                    $entity->{$recursiveCall}($selection);
                }
            }
            else
            {
                if ( is_a($value, 'Entity') )
                {
                    $value->{$recursiveCall}($selection);
                }
            }
        }
    }

    /**
     * General copy functionality
     */
    public function copy($selection = null)
    {
        if ( $selection == null )
        {
            return $this->copyDependent();
        }
        else
        {
            return $this->copySelection($this->normalizeSelection($selection));
        }
    }

    public function copyDependent()
    {
        $selection = $this->selectDependent();
        return $this->copySelection($selection);
    }

    public function copySelection($idEntityMap)
    {
        // Create copies and insert them in an id map
        $copyOfThis = null;
        $roleIdNewRoleMap = [];
        foreach ( $idEntityMap as $entityId => $entity )
        {
            // pr("Create copy without relations");
            // pr($entityId);
            // pr($entity->entityId());
            $entityCopy = self::createCopyWithoutRelations($entity, $roleIdNewRoleMap);
            if ( $entityId == $this->entityId() )
            {
                $copyOfThis = $entityCopy;
            }
        }

        // Link all objects together
        foreach ( $idEntityMap as $entityId => $entity )
        {
            foreach ( $entity->roles as $role )
            {
                $newRole = &$roleIdNewRoleMap[$role['roleId']];
                foreach ( $role['model']->belongsTo as $modelAlias => $options )
                {
                    $modelName = $options['className'];
                    $foreignKeyName = $options['foreignKey'];
                    // $modelName = $role['model']->getAssociated($modelAlias)['className'];

                    $belongToRoleId = self::createRoleId(
                        $role['modelName'],
                        $role['model']->primaryKey,
                        $role['foreignKeys'][$foreignKeyName]
                    ); // TODO: Check if this is really right. Looks wierd.
                    // pr("Belongs to: " . $belongToRoleId);
                    // pr($role['foreignKeys']);
                    $foreignKeyValue = null;
                    if ( isset($roleIdNewRoleMap[$belongToRoleId]) )
                    {
                        // Point to a new role
                        $foreignKeyValue = $roleIdNewRoleMap[$belongToRoleId]['primaryKeyValue'];
                    }
                    else
                    {
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
    public function serialize($selection = null)
    {
        if ( $selection == null )
        {
            return $this->serializeDependent();
        }
        else
        {
            return $this->serializeSelection($this->normalizeSelection($selection));
        }
    }

    public function serializeDependent()
    {
        $selection = $this->selectDependent();
        return $this->serializeSelection($selection);
    }

    public function serializeCached()
    {
        $selection = $this->selectCached();
        return $this->serializeSelection($selection);
    }

    public function rolesSummary()
    {
        $result = "";
        foreach ( $this->roles as $role )
        {
            if ( $result != "" )
            {
                $result .= "; ";
            }
            $result .= $role['modelName'] . ":" . $role['primaryKeyValue'];
        }
        return $result;
    }

    public function serializeWithoutRelations($lastChanges)
    {
        // Merge data
        $mergedData = [];
        $index = count($this->roles);
        while ( $index )
        {
            // pr($this->roles[$index - 1]['data']);
            $mergedData = array_merge($mergedData, $this->roles[--$index]['data']);
        }

        // Construct serialized version.
        $serialized = [];
        $serialized['entityId'] = null; // Note: This is to make these appear first in the array. 
        $serialized['class'] = null;   
        $serialized['phpEntityClass'] = null;
        foreach ( $mergedData as $key => $value )
        {
            $serialized[Inflector::variable($key)] = $value;
        }
        $serialized['class'] = $this->entityClassName;
        $serialized['phpEntityClass'] = $this->phpClassName;
        $serialized['entityId'] = $this->entityId();

        if ( $lastChanges !== null )
        {
            // Add last change information
            $changes = [];
            foreach ( $serialized as $key => $value )
            {
                if ( $lastChanges === true || isset($lastChanges[$key]) )
                {
                    $lastChange = self::$Change->find(
                        'first',
                        [
                            'conditions' =>
                                [
                                    'Change.type' => 'modify',
                                    'Change.entity_id_model' => $this->entityIdModel(),
                                    'Change.entity_id_primary_key_value' => $this->entityIdPrimaryKeyValue(),
                                    'Change.field_or_relation' => $key,
                                ],
                            'order' => ['Change.id DESC'],
                        ]
                    );
                    if ( isset($lastChange['Change']) )
                    {
                        $changes[$key] = [
                            'user' => self::$User->getFullName($lastChange['Change']['user_id']),
                            'time' => date('Y-m-d', $lastChange['Change']['time']),
                        ];
                    }
                    else
                    {
                        $changes[$key] = null;
                    }
                }
            }
            $serialized['lastChanges'] = $changes;

            // Find creation date. TODO: Finish as an option, for now remove for performance.
            /*
            $creation = self::$Change->find(
                'first',
                array('conditions' =>
                    array(
                        'Change.type' => 'create',
                        'Change.entity_id_model' => $this->entityIdModel(),
                        'Change.entity_id_primary_key_value' => $this->entityIdPrimaryKeyValue()
                    )
                )
            );
            if (!isset($creation['Change'])) {
                pr("No change event found for entity: " . $this->entityId());
                stackdump();
            }
            $serialized['created'] = array('user' => self::$User->getFullName($creation['Change']['user_id']), 'time' => date('Y-m-d', $creation['Change']['time']));
            */
        }

        return $serialized;
    }

    public function serializeAssociated(&$mergedData, $modelAlias, $value, &$lastChanges, &$selection, &$visitedSet)
    {
        // Serialize the relation value recursivley
        $relationValue = null;
        if ( $value === null )
        { //OBS!!! Use === to avoid having empty array compare to null!
            $relationValue = null;
        }
        else
        {
            if ( is_a($value, 'Entity') )
            {
                $relationValue = $value->serializeConnectedPartOfSelection($selection, $visitedSet);
            }
            else
            {
                if ( is_array($value) )
                {
                    $serializedRelation = [];
                    foreach ( $value as $entity )
                    {
                        $serializedRelation[] = $entity->serializeConnectedPartOfSelection($selection, $visitedSet);
                    }
                    $relationValue = $serializedRelation;
                }
            }
        }
        $mergedData[$modelAlias] = $relationValue;

        // Serialize last changes if requested
        if ( $lastChanges != null )
        {
            if ( $lastChanges === true || isset($lastChanges[$modelAlias]) )
            {
                $lastChange = self::$Change->find(
                    'first',
                    [
                        'conditions' =>
                            [
                                'Change.type' => 'modify',
                                'Change.entity_id_model' => $this->entityIdModel(),
                                'Change.entity_id_primary_key_value' => $this->entityIdPrimaryKeyValue(),
                                'Change.field_or_relation' => $modelAlias,
                            ],
                        'order' => ['Change.id DESC'],
                    ]
                );
                if ( isset($lastChange['Change']) )
                {
                    $mergedData['lastChanges'][$modelAlias] = [
                        'user' => self::$User->getFullName(
                            $lastChange['Change']['user_id']
                        ),
                        'time' => date('Y-m-d', $lastChange['Change']['time']),
                    ];
                }
                else
                {
                    $mergedData['lastChanges'][$modelAlias] = null;
                }
            }
        }
    }

    public static function batchSerializeSelection(&$selection)
    {
        $batchResult = [];
        $visitedSet = [];
        $entityIds = array_keys($selection);
        foreach ( $entityIds as $entityId )
        {
            if ( isset($selection[$entityId]) )
            {
                $entity = self::get($entityId);
                $batchResult[] = $entity->serializeConnectedPartOfSelection($selection, $visitedSet);
            }
        }
        return $batchResult;
    }
    
	public function serializeSelection(&$selection) {
		$serialized = [];
		$visitedSet = [];
		// Serialize this first, this will be returned on the client. 
		$serialized[] = $this->serializeConnectedPartOfSelection($selection, $visitedSet);
		
		// Serialize things that remain in the selection!
		while(!empty($selection)) {
			reset($selection);
			$entityId = key($selection);
			$entity = self::get($entityId);
			$serialized[] = $entity->serializeConnectedPartOfSelection($selection, $visitedSet);
			// Note: The serialization removes the key from the map.
		}
		return $serialized;
	}
	
	
    public function serializeConnectedPartOfSelection(&$selection, &$visitedSet = [])
    {
        $entityId = $this->entityId();
        if ( isset($visitedSet[$entityId]) )
        {
            return $entityId;
        }
        else
        {
            $visitedSet[$entityId] = true;
            if ( isset($selection[$entityId]) )
            {
                $elaboration = $selection[$entityId];
                unset($selection[$entityId]);
                // pr("Serialize:" . $entityId);

                // if ($this->entityIdModel() == "Project") {
                // pr("here");
                // die;
                // pr(is_array($selection[$this->entityId()]));
                // pr($selection[$this->entityId()]);
                // pr(isset($selection[$this->entityId()]['lastChanges']));
                // pr(is_array($selection[$this->entityId()]) && isset($selection[$this->entityId()]['lastChanges']));
                // }
                $lastChanges = (is_array($elaboration) && isset($elaboration['lastChanges'])) ?
                    $elaboration['lastChanges'] : null;
                // if ($lastChanges) {
                // prd("Foobar!");
                // }
                $mergedData = $this->serializeWithoutRelations($lastChanges);
                if ( is_array($elaboration) && isset($elaboration['selectTags']) )
                {
                    $mergedData['loadTags'] = $elaboration['selectTags'];
                }
                // if ($this->entityIdModel() == "SelectOptionActivity") {
                // pr($lastChanges);
                // prd($mergedData);
                // }

                // Search through models to find table data or relation.
                foreach ( $this->roles as $role )
                {
                    $model = $role['model'];

                    // Map belongs to models
                    foreach ( $model->belongsTo as $modelAlias => $association )
                    {
                        if ( !in_array($modelAlias, $model->extendTo) && $model->extends != $modelAlias )
                        {
                            $this->serializeAssociated(
                                $mergedData,
                                $modelAlias,
                                $this->{$modelAlias}(),
                                $lastChanges,
                                $selection,
                                $visitedSet
                            );
                        }
                    }

                    // Map has one models
                    foreach ( $model->hasOne as $modelAlias => $association )
                    {
                        // pr("Has one");
                        // pr($modelAlias);
                        // pr($model->extendTo);
                        // pr($model->extends);
                        if ( !in_array($modelAlias, $model->extendTo) && $model->extends != $modelAlias )
                        {
                            $this->serializeAssociated(
                                $mergedData,
                                $modelAlias,
                                $this->{$modelAlias}(),
                                $lastChanges,
                                $selection,
                                $visitedSet
                            );
                        }
                    }

                    // Map has many models
                    foreach ( $model->hasMany as $modelAlias => $association )
                    {
                        if ( !in_array($modelAlias, $model->extendTo) && $model->extends != $modelAlias )
                        {
                            $this->serializeAssociated(
                                $mergedData,
                                $modelAlias,
                                $this->{$modelAlias}(),
                                $lastChanges,
                                $selection,
                                $visitedSet
                            );
                        }
                    }

                    //Map has and belongs to models
                    foreach ( $model->hasAndBelongsToMany as $modelAlias => $association )
                    {
                        if ( !in_array($modelAlias, $model->extendTo) && $model->extends != $modelAlias )
                        {
                            $this->serializeAssociated(
                                $mergedData,
                                $modelAlias,
                                $this->{$modelAlias}(),
                                $lastChanges,
                                $selection,
                                $visitedSet
                            );
                        }
                    }
                }
                $this->serializeAdditionalRelations($mergedData, $selection, $visitedSet);
                $mergedData['loaded'] = true;
                return $mergedData;
            }
            else
            {
                return [  // TODO: Some space could be saved by returning a certain string, and creating this structure on the client. 
                    'loaded' => false,
                    'entityId' => $entityId,
                    'loadTags' => ['dummy' => true] // To ensure Json creates an object and not an array.
                ];
            }
        }
    }

    /**
     * This one is primarily used by index.
     */
    public function serializeAdditionalRelations(&$serialized, &$selection, &$visitedSet)
    {
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
        // pr("Parent!");
        // die;
        // pr(" -- called " . $method);
        // pr($this->entityId());
        // Optimized getters
        if ( isset($this->dataCache[$method]) )
        {
            return $this->dataCache[$method];
        }
        if ( isset($this->relationCache[$method]) )
        {
            return $this->relationCache[$method];
        }

        // Analyze command
        $command = null;
        $propertyOrRelation = null;
        if ( startsWith($method, "set") && strlen($method) > 3 && ctype_upper(substr($method, 3, 1)) )
        {
            $propertyOrRelation = lcfirst(substr($method, 3));
            $command = "set";
        }
        else
        {
            if ( startsWith($method, "add") && strlen($method) > 3 && ctype_upper(substr($method, 3, 1)) )
            {
                $propertyOrRelation = lcfirst(substr($method, 3));
                $command = "add";
            }
            else
            {
                if ( startsWith($method, "remove") && strlen($method) > 6 && ctype_upper(substr($method, 6, 1)) )
                {
                    $propertyOrRelation = lcfirst(substr($method, 6));
                    $command = "remove";
                }
                else
                {
                    if ( !array_key_exists(0, $args) )
                    {
                        $propertyOrRelation = $method;
                        $command = "get";
                    }
                    else
                    {
                        $propertyOrRelation = $method;
                        $command = "set";
                    }
                }
            }
        }
        // pr("PropertyOrRelation: " . $propertyOrRelation);
        // pr("Command: " . $command);

        // Search through models to find table data or relation.
        foreach ( $this->roles as &$role )
        {
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
            // pr($tableColumnName);
            if ( array_key_exists($tableColumnName, $roleData) )
            {
                if ( $command == 'get' )
                {
                    $value = $roleData[$tableColumnName];
                    $this->dataCache[$tableColumnName] = $value;
                    return $roleData[$tableColumnName];
                }

                if ( $command == 'set' )
                {
                    $newValue = $args[0];
                    $roleData[$tableColumnName] = $newValue;
                    $model->{$model->primaryKey} = $primaryKeyValue;
                    $model->saveField($tableColumnName, $newValue);
                    $this->dataCache[$tableColumnName] = $newValue;
                    self::registerChange($this, $propertyOrRelation, $newValue);
                    return;
                }
            }

            if ( $role['primaryKey'] == $propertyOrRelation )
            {
                $value = $role['primaryKeyValue'];
                $this->dataCache[$tableColumnName] = $value;
                return $value;
            }

            // Is it a relation?
            // pr(array_roleIds($model->getAssociated()));
            $relatedModelAlias = ucfirst($propertyOrRelation);
            // pr("Related model alias: " .  $relatedModelAlias);
            if ( isset($model->getAssociated()[$relatedModelAlias]) )
            {
                $associationType = $model->getAssociated()[$relatedModelAlias];

                // Filter out internal relations within the same object
                if ( !in_array($relatedModelAlias, $model->extendTo) && $model->extends != $relatedModelAlias )
                {
                    $relatedRoleAssociation = $model->getAssociated($relatedModelAlias);
                    $relatedModelName = $relatedRoleAssociation['className'];
                    $relatedModel = self::getModel($relatedModelName);

                    // Get associated through relation
                    if ( $command == 'get' )
                    {
                        // Find mirror relation
                        $mirrorRelation = self::getMirrorRelation(
                            $relatedModel,
                            $model->name,
                            $relatedModelAlias,
                            $relatedRoleAssociation['foreignKey']
                        );
                        $mirrorRelationName = $mirrorRelation['relationName'];

                        // pr($primaryKeyValue);
                        $relatedData = self::getRelatedData(
                            $model,
                            $primaryKeyValue,
                            $relatedModelAlias,
                            $relatedModel
                        );
                        // pr($relatedData);
                        $associated = null;
                        // pr($relatedData);
                        if ( $relatedData === null )
                        {
                            $associated = null;
                        }
                        else
                        {
                            if ( isset($relatedData[$relatedModel->primaryKey]) )
                            {
                                //Single relation here
                                $associated = self::get(
                                    $relatedModelName,
                                    $relatedData[$relatedModel->primaryKey],
                                    [$relatedModel->name => $relatedData]
                                );

                                // Write to mirror relation for optimization (avoid another select)
                                if ( $mirrorRelation != null )
                                {
                                    if ( $associationType == 'hasOne' ||
                                        $relatedModel->getAssociated()[$mirrorRelationName] == 'hasOne'
                                    )
                                    {
                                        $associated->relationCache[$mirrorRelationName] = $this;
                                    }
                                }
                            }
                            else
                            {
                                //Set relation
                                $associated = [];
                                foreach ( $relatedData as $relatedEntityData )
                                {
                                    $relatedEntity = self::get(
                                        $relatedModelName,
                                        $relatedEntityData[$relatedModel->primaryKey],
                                        [$relatedModel->name => $relatedEntityData]
                                    );
                                    $associated[] = $relatedEntity;

                                    // Write to mirror relation for optimization (avoid another select)
                                    if ( $mirrorRelation != null )
                                    {
                                        if ( $associationType == 'hasMany' )
                                        {
                                            $relatedEntity->relationCache[$mirrorRelationName] = $this;
                                        }
                                    }
                                }
                            }
                        }

                        $this->relationCache[$method] = $associated;
                        return $associated;
                    }

                    // Set associated entity for this relation (... to one relation)
                    if ( $command == 'set' )
                    {
                        $newValue = $args[0];
                        self::registerChange($this, $relatedModelAlias, $newValue);

                        if ( $associationType == 'belongsTo' )
                        {
                            // Store abandoned value
                            $oldValue = $this->{$relatedModelAlias}();
                            $oldRelatedId = ($oldValue != null) ? $oldValue->getPrimaryKeyForModel($relatedModelName) :
                                null;
                            // $newValue->printRoles();

                            // Change this entity/model to point to new
                            // pr($relatedModelName);
                            // pr($newValue->getPrimaryKeyForModel($relatedModelName));
                            $newRelatedId = ($newValue != null) ? $newValue->getPrimaryKeyForModel($relatedModelName) :
                                null;
                            // pr("Saving a belongs to");
                            // pr($newRelatedId);
                            // pr($relatedRoleAssociation['foreignKey']);
                            $role['foreignKeys'][$relatedRoleAssociation['foreignKey']] = $newRelatedId;
                            $model->{$model->primaryKey} = $primaryKeyValue;
                            // pr("Saving");
                            // pr($relatedRoleAssociation['foreignKey']);
                            // pr($newRelatedId);
                            $model->saveField($relatedRoleAssociation['foreignKey'], $newRelatedId);

                            // Clear caches for peers.
                            $mirrorRelation = self::getMirrorRelation(
                                $relatedModel,
                                $model->name,
                                $relatedModelAlias,
                                $relatedRoleAssociation['foreignKey']
                            );
                            $mirrorRelationName = $mirrorRelation['relationName'];
                            if ( $oldValue != null && isset($oldValue->relationCache[$mirrorRelationName]) )
                            {
                                unset($oldValue->relationCache[$mirrorRelationName]);
                            }
                            if ( $newValue != null && isset($newValue->relationCache[$mirrorRelationName]) )
                            {
                                unset($newValue->relationCache[$mirrorRelationName]);
                            }
                        }
                        else
                        {
                            if ( $associationType == 'hasMany' )
                            {
                                $oldValue = $this->{$relatedModelAlias}();
                                $mirrorRelation = self::getMirrorRelation(
                                    $relatedModel,
                                    $model->name,
                                    $relatedModelAlias,
                                    $relatedRoleAssociation['foreignKey']
                                );
                                $mirrorRelationName = $mirrorRelation['relationName'];
                                foreach ( $oldValue as $oldRelated )
                                {
                                    $oldRelated->{'set' . $mirrorRelationName}(null);
                                }
                                foreach ( $newValue as $newRelated )
                                {
                                    $newRelated->{'set' . $mirrorRelationName}($this);
                                }
                            }
                            else
                            {
                                if ( $associationType == 'hasOne' )
                                {
                                    $oldValue = $this->{$relatedModelAlias}();

                                    $mirrorRelation = self::getMirrorRelation(
                                        $relatedModel,
                                        $model->name,
                                        $relatedModelAlias,
                                        $relatedRoleAssociation['foreignKey']
                                    );
                                    $mirrorRelationName = $mirrorRelation['relationName'];

                                    $oldValue->{'set' . $mirrorRelationName}(null);
                                    $newValue->{'set' . $mirrorRelationName}($this);
                                }
                                else
                                {
                                    if ( $associationType == 'hasAndBelongsToMany' )
                                    {
                                        // Store old value
                                        $oldValue = $this->{$relatedModelAlias}();

                                        // Create an array of new id:s
                                        $newIdArray = [];
                                        foreach ( $newValue as $newEntity )
                                        {
                                            if ( !is_a($newEntity, 'Entity') )
                                            {
                                                pr(
                                                    "The value beeing set to relation " . $relatedModelAlias .
                                                    " (habtm relation) is invalid. It should be an array of entities, but is something else:"
                                                );
                                                pred($newValue);
                                                pr(
                                                    "Perhaps something is wrong with the setup of model " .
                                                    $modelName . ":"
                                                );
                                                pr($model->getAssociated());
                                                stackdump();
                                            }
                                            $newIdArray[] = $newEntity->getRole($relatedModelName)['primaryKeyValue'];
                                        }
                                        $data = [$relatedModelAlias => [$relatedModelAlias => $newIdArray]];
                                        // $data[$model->primaryKey] = $role['primaryKeyValue'];

                                        $model->{$model->primaryKey} = $primaryKeyValue;

                                        $model->save($data);
                                        // pr($model->primaryKey);
                                        // pr($primaryKeyValue);
                                        // prd($data);
                                        // pr(self::getAssociationsToModel($relatedModel, $model->name));
                                        // die;

                                        // Clear cache for mirror relations
                                        $mirrorRelation = self::getMirrorRelation(
                                            $relatedModel,
                                            $model->name,
                                            $relatedModelAlias,
                                            $relatedRoleAssociation['associationForeignKey']
                                        );
                                        $mirrorRelationName = $mirrorRelation['relationName'];
                                        foreach ( $oldValue as $oldRelated )
                                        {
                                            if ( isset($oldRelated->relationCache[$mirrorRelationName]) )
                                            {
                                                unset($oldRelated->relationCache[$mirrorRelationName]);
                                            }
                                        }
                                        foreach ( $newValue as $newRelated )
                                        {
                                            if ( isset($newRelated->relationCache[$mirrorRelationName]) )
                                            {
                                                unset($newRelated->relationCache[$mirrorRelationName]);
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        // Setup relation cache
                        $this->relationCache[$relatedModelAlias] = $newValue;
                        return null;
                    }

                    // Add associated entity for this relation (... to many relation)
                    if ( $command == 'add' )
                    {

                        $addedEntity = $args[0];
                        $currentValue = $this->{$relatedModelAlias}();
                        if ( !is_array($currentValue) )
                        {
                            pr(
                                "Cream: The current value of '" . $relatedModelAlias . "' is not an array: " .
                                $this->entityId() . "."
                            );
                            stackdump();
                        }
                        $newRelatedEntities = array_merge($currentValue, [$addedEntity]);
                        // $this->printRelationValue($newRelatedEntities);
                        // die();
                        $this->{'set' . $relatedModelAlias}($newRelatedEntities);
                        return null;
                    }

                    // Remove associated entity for this relation (... to many relation)
                    if ( $command == 'remove' )
                    {
                        $removedEntity = $args[0];
                        $newRelatedEntities = [];
                        foreach ( $this->{$relatedModelAlias}() as $relatedEntity )
                        {
                            if ( $relatedEntity != $removedEntity )
                            {
                                $newRelatedEntities[] = $relatedEntity;
                            }
                        }
                        $this->{'set' . $relatedModelAlias}($newRelatedEntities);
                        return null;
                    }
                }
            }
        }
        pr(
            "Cream: Unknown property or relation '" . $method . "' for entity: " . $this->entityId() .
            ". Perhaps you need to add the relation in the model? Or is the field misspelled?"
        );
        stackdump();
    }

    public function __get($fieldOrRelation)
    {
        $this->{$fieldOrRelation}();
    }

    // public function __set ($fieldOrRelation , $value) {
    // $this->{'set' . ucfirst($fieldOrRelation)}($value);
    // }
}

Entity::$Change = Entity::getModel('Change');
Entity::$User = Entity::getModel('User');
Entity::$Index = Entity::getModel('Index');

