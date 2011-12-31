<?php
/**
 * @author Johannes "Haensel" Bauer
 * @since version 0.1
 * @version 0.1
 */

/**
 * ENeo4jPropertyContainer represents either a node or a relationship in an Neo4j graph database and provides
 * basic functionalities both classes have in common.
 */
abstract class ENeo4jPropertyContainer extends EActiveResource
{    
    public $self; //always contains the full uri. If you need the id use getId() instead.
    public $batchId; //this is used when using the ENeo4jBatchTransaction. Each property container gets an id to be uniquely identified
    
    protected static $_connection;
    
    /**
     * Overrides the parent implementation in order to allow setting another MetaData class than EActiveResourceMetaData
     * @param type $className
     * @return className 
     */
    public static function model($className=__CLASS__)
    {
            if(isset(self::$_models[$className]))
                    return self::$_models[$className];
            else
            {
                    $model=self::$_models[$className]=new $className(null);
                    $model->_md=new ENeo4jMetaData($model);
                    $model->attachBehaviors($model->behaviors());
                    return $model;
            }
    }
    
    public function routes()
    {
        return CMap::mergeArray(
                parent::routes(),
                array(
                    'properties'=>':site/:resource/:id/properties'
                )
        );
    }
    
    /**
     * Overrides EActiveResource getMetaData() to allow defining own MetaData classes
     * @return ENeo4jMetaData the meta for this ActiveResource class.
     */
    public function getMetaData()
    {
            if($this->_md!==null)
                    return $this->_md;
            else
                    return $this->_md=self::model(get_class($this))->_md;
    }
    
    /**
     * Overrides the ActiveResource method.
     * @return array The Configuration() array of the Graph Service.
     */
    public function rest()
    {
        return CMap::mergeArray(
                $this->getConnection()->rest(),
                array(
                    'idProperty'=>'self',
                    'container'=>'data',
                )
        );
    }
    
    public function properties()
    {
        return CMap::mergeArray(
            parent::properties(),
            array(
                $this->getModelClassField()=>array('type'=>'string'),
        ));
    }
    
    public function beforeSave() {
        $this->{$this->getModelClassField()}=get_class($this);
        return parent::beforeSave();
    }
    
    public function getConnection()
    {
        if(isset(self::$_connection))
                return self::$_connection;
        else
        {
            self::$_connection=Yii::app()->getComponent('neo4j');
            if(self::$_connection instanceof ENeo4jGraphService)
                return self::$_connection;
            else
                throw new EActiveResourceException('No "neo4j" component specified!');
        }
    }

    public function assignBatchId($id)
    {
        $this->batchId=$id;
    }

    /**
     * Inits the model and sets the modelclassfield so that the model can be instantiated properly.
     */
    public function init()
    {
        $modelclassfield=$this->getModelClassField();
        $this->$modelclassfield=get_class($this);
    }
    
    /**
     * This method returns the name of the attribute that is used to determine to which modelclass a node or relationship
     * belongs to. Override this method to use your own attribute definition, but take care not to switch once you already created nodes or relationships
     * as this attribute is used to instantiate model objects.
     * @return string the attribute name
     */
    public function getModelClassField()
    {
        return 'modelclass';
    }

    /**
     * Overrides the ActiveResource method to return the id and not the whole uri as you would get it via "self"
     * @return integer the id
     */
    public function getId()
    {
        $uri=$this->self;
        return end(explode('/',$uri));
    }
    
    /**
     * Checks whether this ActiveResource has the named attribute
     * @param string $name attribute name
     * @return boolean whether this ActiveResource has the named attribute.
     */
    public function hasAttribute($name)
    {
            return isset($this->getMetaData()->properties[$name]);
    }

    /**
     * Overrides the ActiveResource method in order to allow something similar to "single table inheritance".
     * As we cannot know which modelclass the loaded node or relationship belongs to we have to look for a field attribute that
     * tells us the classname. This field is specified via getModelClassField() and defaults to 'modelclass'.
     * @param array $attributes The attributes the model has to be instantiated with
     * @return ActiveResource The instantiated model
     */
    protected function instantiate($attributes)
    {
        if(isset($attributes[$this->getModelClassField()]))
            $class=$attributes[$this->getModelClassField()];
        else
            $class=get_class($this);
        $model=new $class(null);
        return $model;
    }

    public function offsetExists($offset)
    {
        return $this->__isset($offset);
    }
    
    /**
     * Overrides the ActiveResource method and updates the node or relationship.
     * All loaded attributes will be saved to the service.
     * Note, validation is not performed in this method. You may call {@link validate} to perform the validation.
     * @param array $attributes list of attributes that need to be saved. Defaults to null,
     * meaning all attributes that are loaded from the service will be saved.
     * @return boolean whether the update is successful
     * @throws EActiveResourceException if the resource is new
     */
    public function update($attributes=null)
    {

            if($this->getIsNewResource())
                    throw new EActiveResourceException(Yii::t('ext.Neo4jSuite.ENeo4jPropertyContainer','The PropertyContainer cannot be updated because it is new.'));
            if($this->beforeSave())
            {
                    Yii::trace(get_class($this).'.update()','ext.Neo4jSuite.ENeo4jPropertyContainer');
                    $this->updateById($this->getId(),$this->getAttributesToSend($attributes));
                    $this->afterSave();
                    return true;
            }
            else
                    return false;
    }

    /**
     * Overrides the ActiveResource method in order to only update models of the modelclass that is trying to update them.
     * Note, the attributes are not checked for safety and validation is NOT performed.
     * @param mixed $id node id. Use array for multiple ids.
     * @param array $attributes list of attributes (name=>$value) to be updated
     */
    public function updateById($id,$attributes)
    {
            Yii::trace(get_class($this).'.updateById()','ext.Neo4jSuite.ENeo4jPropertyContainer');
            $model=$this->findById($id);
            if($model)
            {
                $model->putRequest('properties',array(),$attributes);
            }
            else
                throw EActiveResourceException(Yii::t('ext.Neo4jSuite.ENeo4jPropertyContainer','The property container could not be found'));
    }

    /**
     * Overrides the ActiveResource method in order to delete property containers with the specified id.
     * @param mixed $id the node id
     */
    public function deleteById($id)
    {
            Yii::trace(get_class($this).'.deleteById()','ext.Neo4jSuite.ENeo4jPropertyContainer');
            $model=$this->findById($id);
            if($model)
                try
                {
                    $response=$model->deleteRequest('resource');
                }
                catch (EActiveResourceRequestException $e)
                {
                    throw new ENeo4jException('Could not delete property container. Check for existing relationships');
                }
    }

    /**
     * Creates a property container (either node or relationship) with the given attributes.
     * This method is internally used by the find methods.
     * @param array $attributes attribute values (column name=>column value)
     * @param boolean $callAfterFind whether to call {@link afterFind} after the resource is populated.
     * @return ENeo4jPropertyContainer the newly created propertyContainer.
     * Null is returned if the input data is false.
     */
    public function populateRecord($attributes,$callAfterFind=true)
    {
        
        if ($attributes!==false && is_array($attributes))
        {
                $resource=$this->instantiate($attributes['data']);
                $resource->setScenario('update');
                $resource->init();
                
                foreach($attributes as $name=>$value)
                {
                        if(property_exists($resource,$name))
                                $resource->$name=$value;
                        else if($name==$this->getContainer())
                        {
                            foreach($value as $key=>$val)
                                $resource->$key=$val;
                        }
                }
                $resource->attachBehaviors($resource->behaviors());
                if($callAfterFind)
                        $resource->afterFind();
                return $resource;
        }
        else
                return null;
    }

    /**
     * Creates a list of property containers (either nodes or relationships) based on the input data.
     * This method is internally used by the find methods.
     * @param array $data list of attribute values for the active resources.
     * @param boolean $callAfterFind whether to call {@link afterFind} after each resource is populated.
     * @param string $index the name of the attribute whose value will be used as indexes of the query result array.
     * If null, it means the array will be indexed by zero-based integers.
     * @return array list of ENeo4jPropertyContainers.
     */
    public function populateRecords($data,$callAfterFind=true,$index=null)
    {
            $resources=array();

            foreach($data as $attributes)
            {
                    if(($resource=$this->populateRecord($attributes,$callAfterFind))!==null)
                    {
                            if($index===null)
                                    $resources[]=$resource;
                            else
                                    $resources[$resource->$index]=$resource;
                    }
            }

            return $resources;
    }

}

?>
