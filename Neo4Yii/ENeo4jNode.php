<?php
/**
 * @author Johannes "Haensel" Bauer
 * @since version 0.1
 * @version 0.1
 */

/**
 * ENeo4jNode represents a node in an Neo4j graph database. Every node is automatically indexed by default
 * This is especially important as we need an attribute (by default:modelclass) to determine how to instantiate
 * the node.
 */
class ENeo4jNode extends ENeo4jPropertyContainer
{
    const HAS_MANY='HAS_MANY';
    const HAS_ONE='HAS_ONE';
    const NODE='ENeo4jNode';
    const RELATIONSHIP='ENeo4jRelationship';
    const PATH='ENeo4jPath';

    private $_traversed=array();
    protected static $_models=array();

    public function __get($name)
    {
        if(isset($this->attributes[$name]))
            return $this->attributes[$name];
        else if(isset($this->getMetaData()->properties[$name]))
            return null;
        else if(isset($this->_traversed[$name]))
            return $this->_traversed[$name];
        else if(isset($this->getMetaData()->traversals[$name]))
            return $this->getTraversed($name);
        else
            return parent::__get($name);
    }

    public static function model($className=__CLASS__)
    {
            if(isset(self::$_models[$className]))
                return self::$_models[$className];
            else
            {
                $model=self::$_models[$className]=new $className(null);
                $model->attachBehaviors($model->behaviors());
                return $model;
            }
    }

    public function rest()
    {
        return CMap::mergeArray(
            parent::rest(),
            array('resource'=>'node')
        );
    }

    public function routes()
    {
        return CMap::mergeArray(
                parent::routes(),
                array(
                    'relationships'=>':site/:resource/:id/relationships'
                )
        );
    }

    /**
     * Returns the root node
     * @return ENeo4jNode The root node
     */
    public function getRoot()
    {
        Yii::trace('ENeo4jNode.getRoot()','ext.Neo4Yii.ENeo4jNode');
        $gremlinQuery=new EGremlinScript;
        $gremlinQuery->setQuery('g.v(0)');
        return ENeo4jNode::model()->populateRecord($this->getConnection()->queryByGremlin($gremlinQuery)->getData());
    }

    /**
     * Define simple traversals with the current node as starting point like this
     * 'traversalName'=>array(self::[HAS_ONE|HAS_MANY],self::[NODE|RELATIONSHIP],'out.in.filter{it.name=="A property value"}')
     * where HAS_ONE expects a single object to be returned while HAS_MANY expects an array of objects ro be returned.
     * Define the expected returntype via NODE or RELATIONSHIP. The third parameter is a gremlin script that will be added to "g.v(currentNodeId)." to only allow traversals
     * with the current node as a starting point.
     * @return array An array with the defined traversal configurations
     */
    public function traversals()
    {
        return array();
    }

    protected function getTraversed($name,$refresh=false)
    {
            if(!$refresh && (isset($this->_traversed[$name]) || array_key_exists($name,$this->_traversed)))
                    return $this->_traversed[$name];

            $traversals=$this->getMetaData()->traversals;

            if(!isset($traversals[$name]))
                    throw new ENeo4jException(Yii::t('yii','{class} does not have traversal definition "{name}".',
                            array('{class}'=>get_class($this), '{name}'=>$name)));

            Yii::trace('lazy loading '.get_class($this).'.'.$name,'ext.Neo4Yii.ENeo4jNode');
            $traversal=$traversals[$name];
            if($this->getIsNewResource() && !$refresh)
                    return $traversal[0]==self::HAS_ONE ? null : array();

            unset($this->_traversed[$name]);

            $query=new EGremlinScript;
            $query->setQuery('g.v('.$this->getId().').'.$traversal[2]);

            $resultData=$this->getConnection()->queryByGremlin($query)->getData();

            $class=$traversal[1];

            if($traversal[0]==self::HAS_ONE && isset($resultData[0]))
                $this->_traversed[$name]=$class::model()->populateRecord($resultData[0]);
            if($traversal[0]==self::HAS_MANY && isset($resultData[0]))
                $this->_traversed[$name]=$class::model()->populateRecords($resultData);

            if(!isset($this->_traversed[$name]))
            {
                    if($traversal[0]==self::HAS_MANY)
                            $this->_traversed[$name]=array();
                    else
                            $this->_traversed[$name]=null;
            }

            return $this->_traversed[$name];
    }

    /**
     * Finds a single property container with the specified id within the modelclass index.
     * @param mixed $id The id.
     * @return ENeo4jPropertyContainer the property container found. Null if none is found.
     */
    public function findById($id)
    {
        if($id===null)
            throw new ENeo4jException ('Id missing!', 500);

        Yii::trace(get_class($this).'.findById()','ext.Neo4Yii.ENeo4jNode');
        $gremlinQuery=new EGremlinScript;

        $gremlinQuery->setQuery('g.v('.$id.')._().filter{it.'.$this->getModelClassField().'=="'.get_class($this).'"}');
        $responseData=$this->getConnection()->queryByGremlin($gremlinQuery)->getData();

        if(isset($responseData[0]))
            return self::model()->populateRecord($responseData[0]);
    }

    /**
     * Returns gremlin filter syntax based on given attribute key/value pair
     * @param array $attributes
     * @return string the resulting filter string
     */
    private function getFilterByAttributes(&$attributes)
    {
        Yii::trace(get_class($this).'.getFilterByAttributes()','ext.Neo4Yii.ENeo4jNode');
        $filter = "";
        foreach($attributes as $key=>$value) {
            if(!is_int($value)) {
                $value = '"' . $value . '"';
            }
            $filter .= ".filter{it.$key == $value}";
        }

        return (empty($filter) ? false : $filter);
    }

    /**
     * Find a single property container with the specified attributes within the modelclass index.
     * @param type $attributes
     * @return type
     */
    public function findByAttributes($attributes)
    {
        Yii::trace(get_class($this).'.findByAttributes()','ext.Neo4Yii.ENeo4jNode');
        $gremlinQuery=new EGremlinScript;

        $gremlinQuery->setQuery('g.V' . $this->getFilterByAttributes($attributes) .
                '.filter{it.'.$this->getModelClassField().'=="'.get_class($this).'"}');
        $responseData=$this->getConnection()->queryByGremlin($gremlinQuery)->getData();

        if(isset($responseData[0]))
            return self::model()->populateRecord($responseData[0]);
        else
            return null;
    }

    /**
     * Find all models of the named class via gremlin query
     * @param type $attributes
     * @param array An array of model objects, empty if none are found
     */
    public function findAllByAttributes($attributes)
    {
        Yii::trace(get_class($this).'.findAllByAttributes()','ext.Neo4Yii.ENeo4jNode');
        $gremlinQuery=new EGremlinScript;

        $gremlinQuery->setQuery('g.V' . $this->getFilterByAttributes($attributes) .
                '.filter{it.'.$this->getModelClassField().'=="'.get_class($this).'"}');
        $responseData=$this->getConnection()->queryByGremlin($gremlinQuery)->getData();

        return self::model()->populateRecords($responseData);
    }

    /**
     * Find all models of the named class via custom gremlin query
     * @param type $query
     */
    public function findAllByQuery($query)
    {
        Yii::trace(get_class($this).'.findAllByQuery()','ext.Neo4Yii.ENeo4jNode');
        $gremlinQuery=new EGremlinScript;

        $gremlinQuery->setQuery($query. '.filter{it.'.$this->getModelClassField().'=="'.get_class($this).'"}');
        $responseData=$this->getConnection()->queryByGremlin($gremlinQuery)->getData();

        return self::model()->populateRecords($responseData);
    }

    /**
     * Finds all models of the named class via gremlin iterator g.V
     * @return array An array of model objects, empty if none are found
     */
    public function findAll()
    {
        Yii::trace(get_class($this).'.findAll()','ext.Neo4Yii.ENeo4jNode');
        $gremlinQuery=new EGremlinScript;

        $gremlinQuery->setQuery('g.V._().filter{it.'.$this->getModelClassField().'=="'.get_class($this).'"}');
        $responseData=$this->getConnection()->queryByGremlin($gremlinQuery)->getData();

        return self::model()->populateRecords($responseData);
    }

    /**
     * Returns an array of incoming relationships. You can specify which relationships you want by adding
     * them with a ',' seperator. e.g.: incoming('HAS_ATTRIBUTE,'HAS_NAME'')
     * If no type is pecified all incoming relationships will be returned
     * @param string $types The types you want to get
     * @return array An array of ENeo4jRelationship objects
     */
    public function inRelationships($types=null)
    {
        return $this->getRelationships($types,$direction='in');
    }

    /**
     * Returns an array of outgoing relationships. You can specify which relationships you want by adding
     * them with a ',' seperator. e.g.: outgoing('HAS_ATTRIBUTE,'HAS_NAME'')
     * If no type is specified all outgoing relationships will be returned
     * @param string $types The types you want to get
     * @return array An array of ENeo4jRelationship objects
     */
    public function outRelationships($types=null)
    {
        return $this->getRelationships($types,$direction='out');

    }

    /**
     * Returns an array of relationships. You can specify which relationships you want by adding
     * them with a ',' seperator. e.g.: incoming('HAS_ATTRIBUTE,'HAS_NAME'')
     * If no type is pecified all relationships will be returned
     * You can also define the direction of the relationships by using one of the three options for direction ('all','in','out')
     * @param string $types The types you want to get
     * @param string $direction The direction of the relationships
     * @return array An array of ENeo4jRelationship objects
     */
    public function getRelationships($types=null,$direction='all')
    {
        Yii::trace(get_class($this).'.getRelationships()','ext.Neo4Yii.ENeo4jNode');
        $uri=$this->getSite().'/'.$this->getResource().'/'.$this->getId().'/relationships';
        if($direction)
            $uri.='/'.$direction;
        if($types)
        {
            if(is_array($types))
            {
                $types=implode('&',$types);
                $uri.='/'.$types;
            }
            else
                $uri.='/'.$types;
        }

        $response=$this->getRequest($uri);

        if($response->hasErrors())
            $response->throwError();

        return ENeo4jRelationship::model()->populateRecords($response->getData());
    }

    /**
     * Add a relationship to another node
     * @param ENeo4jNode $node The node object to connect with (will be the endNode of the relationship)
     * @param string $type The type of this relationship. something like 'HAS_NAME'. If this is the name of an existing relationship class this class will be instantiated, if not ENeo4jRelationship will be used
     * @param array $properties An array of properties used for the relationship. e.g.: array('since'=>'2010')
     */
    public function addRelationshipTo(ENeo4jNode $node,$type,$properties=null)
    {
        Yii::trace(get_class($this).'.addRelationshipTo()','ext.Neo4Yii.ENeo4jNode');

            $relationship=new $type;
            if(!$relationship instanceof ENeo4jRelationship)
                throw new ENeo4jException('Class is not an instance of ENeo4jRelationship',500);

            $relationship->setAttributes($properties);
            $relationship->setStartNode($this);
            $relationship->setEndNode($node);
            $relationship->save();

    }

}

?>
