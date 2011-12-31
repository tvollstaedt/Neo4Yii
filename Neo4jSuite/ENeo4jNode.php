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
    
    private $_related=array();
    
    public function __get($name)
    {
        if(isset($this->attributes[$name]))
            return $this->attributes[$name];
        else if(isset($this->getMetaData()->properties[$name]))
            return null;
        else if(isset($this->_related[$name]))
            return $this->_related[$name];
        else if(isset($this->getMetaData()->relations[$name]))
            return $this->getRelated($name);
        else
            return parent::__get($name);      
    }    
    
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    /**
     * Get the rest() info of the PropertyContainer and define the resource as "node"
     * @return <type>
     */
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
        Yii::trace('ENeo4jNode.getRoot()','ext.Neo4jSuite.ENeo4jNode');
        $gremlinQuery=new EGremlinScript;
        $gremlinQuery->setQuery('g.v(0)');
        return ENeo4jNode::model()->populateRecord($this->getConnection()->queryByGremlin($gremlinQuery)->getData());
    }
    
    public function relations()
    {
        return array();
    }
    
    protected function getRelated($name,$refresh=false)
    {
            if(!$refresh && (isset($this->_related[$name]) || array_key_exists($name,$this->_related)))
                    return $this->_related[$name];

            $relations=$this->getMetaData()->relations;
            
            if(!isset($relations[$name]))
                    throw new ENeo4jException(Yii::t('yii','{class} does not have relation "{name}".',
                            array('{class}'=>get_class($this), '{name}'=>$name)));

            Yii::trace('lazy loading '.get_class($this).'.'.$name,'ext.Neo4jSuite.ENeo4jNode');
            $relation=$relations[$name];
            if($this->getIsNewResource() && !$refresh)
                    return $relation[0]==self::HAS_ONE ? null : array();

            unset($this->_related[$name]);

            $query=new EGremlinScript;
            $query->setQuery('g.v('.$this->getId().').'.$relation[2]);
            
            $resultData=$this->getConnection()->queryByGremlin($query)->getData();
            
            $class=$relation[1];
            
            if($relation[0]==self::HAS_ONE && isset($resultData[0]))
                $this->_related[$name]=$class::model()->populateRecord($resultData[0]);
            if($relation[0]==self::HAS_MANY && isset($resultData[0]))
                $this->_related[$name]=$class::model()->populateRecords($resultData);

            if(!isset($this->_related[$name]))
            {
                    if($relation[0]==self::HAS_MANY)
                            $this->_related[$name]=array();
                    else
                            $this->_related[$name]=null;
            }

            return $this->_related[$name];
    }
    
    /**
     * Finds a single property container with the specified id within the modelclass index.
     * @param mixed $id The id.
     * @return ENeo4jPropertyContainer the property container found. Null if none is found.
     */
    public function findById($id)
    {
            Yii::trace(get_class($this).'.findById()','ext.Neo4jSuite.ENeo4jNode');
            $gremlinQuery=new EGremlinScript;

            $gremlinQuery->setQuery('g.v('.$id.')._().filter{it.'.$this->getModelClassField().'=="'.get_class($this).'"}');
            $responseData=$this->getConnection()->queryByGremlin($gremlinQuery)->getData();

            if(isset($responseData[0]))
                return self::model()->populateRecord($responseData[0]);
    }

    /**
     * Returns an array of incoming relationships. You can specify which relationships you want by adding
     * them with a ',' seperator. e.g.: incoming('HAS_ATTRIBUTE,'HAS_NAME'')
     * If no type is pecified all incoming relationships will be returned
     * @param string $types The types you want to get
     * @return array An array of ENeo4jRelationship objects
     */
    public function incoming($types=null)
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
    public function outgoing($types=null)
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
        Yii::trace(get_class($this).'.getRelationships()','ext.Neo4jSuite.ENeo4jNode');
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
     * @param string $type The type of this relationship. something like 'HAS_NAME'
     * @param array $properties An array of properties used for the relationship. e.g.: array('since'=>'2010')
     */
    public function addRelationshipTo(ENeo4jNode $node,$type,$properties=null)
    {
        Yii::trace(get_class($this).'.addRelationshipTo()','ext.Neo4jSuite.ENeo4jNode');
        $relationship=new ENeo4jRelationship;
        $relationship->setStartNode($this);
        $relationship->setEndNode($node);
        $relationship->type=$type;
        $relationship->setAttributes($properties,false);
        $relationship->save();
    }

}

?>
