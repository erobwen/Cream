<?php

App::uses('AppModel', 'Model');
App::uses('Entity', 'Model/Entity');

/**
 * Special index model that cream uses to keep track of indexes. An index is a list of entities, corresponding to a
 * search in a database.
 */
class Index extends AppModel
{
}

class IndexEntity extends Entity
{
    public function select(&$selection)
    {
        $modelName = $this->modelName();

        $this->addToSelection($selection);
        foreach ( $this->{$modelName}() as $entity )
        {
            $entity->selectForList($selection);
        }
    }

    public function selectForView(&$selection)
    {
        $this->select($selection);
    }

    public $indexCache = [];

    public function serializeAdditionalRelations(&$serialized, &$selection, &$visitedSet)
    {
        $serializedIndexContent = [];
        $modelName = $this->modelName();
        $indexContent = $this->{$modelName}();
        foreach ( $indexContent as $entity )
        {
            $serializedIndexContent[] = $entity->serializeConnectedPartOfSelection($selection, $visitedSet);
        }
        $serialized[$modelName] = $serializedIndexContent;
    }

    public function __call($method, $args)
    {
        if ( isset($this->indexCache[$method]) )
        {
            return $this->indexCache[$method];
        }

        $modelName = parent::__call('modelName', []);
        $serializedConditions = parent::__call('conditions', []);

        if ( $method == $modelName )
        {
            $model = Entity::getModel($modelName);
            $serializedConditions = parent::__call('conditions', []);
            $conditions = null;
            if ( $serializedConditions != null )
            {
                $conditions = unserialize($serializedConditions);
            }
            $options = [];
            if ( $conditions != null )
            {
                $options['conditions'] = $conditions;
            }
            $entities = [];
            foreach ( $model->find('all', $options) as $item )
            {
                $primaryKey = $item[$model->name][$model->primaryKey];
                $entities[] = Entity::get($model->name, $primaryKey);
            }
            $this->indexCache[$method] = $entities;
            return $entities;
        }
        else
        {
            return parent::__call($method, $args);
        }
    }
}

