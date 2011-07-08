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
    public $autoIndexing=true; //true by default, meaning all attributes (especially the modelclass attribute) will automatically indexed
    public $autoIndexModel; //the index object

    private $_graphService; //The graphService all nodes or relationships belong to.
    
    /**
     * Overrides the ActiveResource method.
     * @return array The Configuration() array of the Graph Service.
     */
    public function rest()
    {
        return CMap::mergeArray(
                $this->getGraphService()->rest(),
                array(
                    'idProperty'=>'self',
                    'container'=>'data',
                )
        );
    }

    /**
     * After saving a node/relationship we want to add it to the index
     */
    public function afterSave() {
        if($this->autoIndexing)
            $this->autoIndex();
        parent::afterSave();
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
     * Getter for the graphService object which itself is a subclass of ActiveResource
     * @return ENeo4jGraphService The graphService object
     */
    public function getGraphService()
    {
        if(isset($this->_graphService))
                return $this->_graphService;
        else
            return new ENeo4jGraphService;
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
     * Overrides the ActiveResource method to enable usage of the graphService.
     * @return string the content type as specified in the graph object
     */
    public function getContentType()
    {
        return $this->getMetaData()->contenttype;
    }

    /**
     * Overrides the ActiveResource method to enable usage of the graphService.
     * @return string the accept type as specified in the graph object
     */
    public function getAcceptType()
    {
        return $this->getMetaData()->accepttype;
    }

    /**
     * Overrides the ActiveResource method to enable usage of the graphService.
     * @return string the site as specified in the graph object
     */
    public function getSite()
    {
        return $this->getMetaData()->site;
    }

    /**
     * Overrides the ActiveResource method to enable usage of the graphService.
     * @return string the resource as specified in the graph object
     */
    public function getResource()
    {
        return $this->getMetaData()->resource;
    }

    /**
     * Overrides the ActiveResource method to enable usage of the graphService.
     * @return string the container as specified in the graph object
     */
    public function getContainer()
    {
        return $this->getMetaData()->container;
    }

    /**
     * Overrides the ActiveResource method to enable usage of the graphService.
     * @return string the idProperty as specified in the graph object
     */
    public function idProperty()
    {
        return $this->getMetaData()->idProperty;
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
                    $this->updateById($this->getId(),$this->getAttributes($attributes));
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
                $this->putRequest($id,$attributes,'/properties');
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
                $this->deleteRequest($id);
            else
                throw EActiveResourceException(Yii::t('ext.Neo4jSuite.ENeo4jPropertyContainer','The property container could not be found'));
    }

    /**
     * Finds a single property container with the specified id within the modelclass index.
     * @param mixed $id The id.
     * @return ENeo4jPropertyContainer the property container found. Null if none is found.
     */
    public function findById($id)
    {
            Yii::trace(get_class($this).'.findById()','ext.Neo4jSuite.ENeo4jPropertyContainer');

            $index=$this->getModelIndex();

            $query=new ENeo4jLuceneQuery;

            $query->addStatement('id:'.$id,'AND');
            $query->addStatement($this->getModelClassField().':'.get_class($this),'AND');

            $response=$index->query($query);

            if($response)
                return $response[0];
            else
                throw new EActiveResourceRequestNotFoundException('Node not found', 404);

    }

    /**
     * Overrides the ActiveResource method in order to query the property container index for models of the calling finder class
     * To only get back property containers of the same class as the finder instance we have to always add a query for the modelclass attribute
     * If no query is provided all property containers of the same modelclass will be loaded and the first will be returned
     * @return ENeo4jPropertyContainer returns single property container or null if none is found.
     */
    public function find($query=null)
    {
            Yii::trace(get_class($this).'.find()','ext.Neo4jSuite.ENeo4jPropertyContainer');
            $index=$index=$this->getModelIndex();;
            if($query!=false)
            {
                if($query instanceof ENeo4jLuceneQuery)
                {
                    $query->addStatement($this->getModelClassField().':'.get_class($this),'AND');
                    $response=$index->query($query);
                }
                else if(is_string($query))
                {
                    $queryobject=new ENeo4jLuceneQuery;
                    $queryobject->setQueryString($query);
                    $queryobject->addStatement($this->getModelClassField().':'.get_class($this),'AND');

                    $response=$index->query($queryobject);
                }
            }
            else
                $response=$index->exactLookup($this->getModelClassField(),get_class($this));

            if(isset($response[0]))
                return $response[0];
            else
                return null;
    }


    /**
     * Overrides the ActiveResource method in order to query the property container index for all models of the calling finder class
     * To only get back property containers of the same class as the finder instance we have to always add a query for the modelclass attribute
     * If no query is provided all property containers of the same modelclass will be loaded.
     * @return ENeo4jPropertyContainer returns an array of property containers or an empty array if none are found.
     */
    public function findAll($query=null)
    {
            Yii::trace(get_class($this).'.findAll()','ext.Neo4jSuite.ENeo4jPropertyContainer');
            $index=$index=$this->getModelIndex();
            if($query instanceof ENeo4jLuceneQuery)
                {
                    $query->addStatement(array($this->getModelClassField(),array('AND',get_class($this))));
                    $response=$index->query($query);
                }
                else if(is_string($query))
                {
                    $queryobject=new ENeo4jLuceneQuery;
                    $queryobject->setQueryString($query);
                    $queryobject->addStatement($this->getModelClassField().':'.get_class($this),'AND');

                    $response=$index->query($queryobject);
                }
            else
                $response=$index->exactLookup($this->getModelClassField(),get_class($this));
            return $response;
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
