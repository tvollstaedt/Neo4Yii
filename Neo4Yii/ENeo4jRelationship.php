<?php
/**
 * @author Johannes "Haensel" Bauer
 * @since version 0.1
 * @version 0.1
 */

/**
 * ENeo4jRelationship represents a relationship in an Neo4j graph database
 */
class ENeo4jRelationship extends ENeo4jPropertyContainer
{
    public $start; //start uri
    public $end; //end uri

    private $_startNode; //a container for the startNode object
    private $_endNode; //a container for the endNode object
    private $_type;

    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    /**
     * Sets the type according to the classname
     */
    public function init()
    {
        parent::init();
        $this->_type=get_class($this);
    }

    /**
     * Get information of the PropertyContainer class and extend by adding the relationship resource
     * @return <type>
     */
    public function rest()
    {
        return CMap::mergeArray(
            parent::rest(),
            array('resource'=>'relationship')
        );
    }

    /**
     * Relationships are created differently to nodes, so we override the ActiveResource method here.
     * @param array $attributes The attributes to be used when creating the relationship
     * @return boolean true on success, false on failure
     */
    public function create($attributes=null)
    {
        if(!$this->getIsNewResource())
            throw new ENeo4jException('The relationship cannot be inserted because it is not new.',500);

        //check if one of the vital infos isn't there
        if($this->endNode->self==null || $this->_type==null || $this->startNode==null)
                throw new ENeo4jException('You cannot save a relationship without defining type, startNode and endNode',500);

        if($this->beforeSave())
        {
            Yii::trace(get_class($this).'.create()','ext.Neo4Yii.ENeo4jRelationship');

            $response=$this->postRequest($this->getSite().'/node/'.$this->startNode->getId().'/relationships',array(),array(
                        'to'=>$this->endNode->getId(),
                        'type'=>$this->_type,
                        'data'=>$this->getAttributesToSend($attributes)

            ));

            $responseData=$response->getData();

            $returnedmodel=$this->populateRecord($response->getData());

            if($returnedmodel)
            {
                $id=$this->idProperty();
                $this->$id=$returnedmodel->getId();
            }

            $this->afterSave();
            $this->setIsNewResource(false);
            $this->setScenario('update');
            return true;

        }
        return false;
    }

    /**
     * Finds a single relationship with the specified id.
     * @param mixed $id The id.
     * @return ENeo4jRelationship the relationship found. Null if none is found.
     */
    public function findById($id)
    {
            Yii::trace(get_class($this).'.findById()','ext.Neo4Yii.ENeo4jRelationship');
            $gremlinQuery=new EGremlinScript;
            $gremlinQuery->setQuery('g.e('.$id.')._().filter{it.'.$this->getModelClassField().'=="'.get_class($this).'"}');
            $response=$this->getConnection()->queryByGremlin($gremlinQuery);
            $responseData=$response->getData();
            if(isset($responseData[0]))
            {
                $model=$this->populateRecords($responseData);
                return $model[0];
            }
            else
                return null;
    }

    /**
     * Finds all models of the named class via gremlin iterator g.E
     * @return array An array of model objects, empty if none are found
     */
    public function findAll()
    {
        Yii::trace(get_class($this).'.findAll()','ext.Neo4Yii.ENeo4jRelationship');
        $gremlinQuery=new EGremlinScript;

        $gremlinQuery->setQuery('g.E._().filter{it.'.$this->getModelClassField().'=="'.get_class($this).'"}');
        $responseData=$this->getConnection()->queryByGremlin($gremlinQuery)->getData();

        return self::model()->populateRecords($responseData);
    }

    /**
     * Setter for the startNode object
     * @param ENeo4jNode $node
     */
    public function setStartNode(ENeo4jNode $node)
    {
        $this->_startNode=$node;
    }

    /**
     * Setter for the endNode object
     * @param ENeo4jNode $node
     */
    public function setEndNode(ENeo4jNode $node)
    {
        $this->_endNode=$node;
    }

    /**
     * Get the start node object
     * @return ENeo4jNode The node
     */
    public function getStartNode()
    {
        if(isset($this->_startNode))
            return $this->_startNode;
        else
        {
            Yii::trace(get_class($this).' is lazyLoading startNode','ext.Neo4Yii.ENeo4jRelationship');
            $gremlinQuery=new EGremlinScript;
            $gremlinQuery->setQuery('g.e('.$this->getId().').outV');

            $responseData=$this->getConnection()->queryByGremlin($gremlinQuery)->getData();

            if(isset($responseData[0]))
                return $this->_startNode=ENeo4jNode::model()->populateRecord($responseData[0]);
        }
    }

    /**
     * Get the end node object
     * @return ENeo4jNode The node
     */
    public function getEndNode()
    {
        if(isset($this->_endNode))
            return $this->_endNode;
        else
        {
            Yii::trace(get_class($this).' is lazyLoading endNode','ext.Neo4Yii.ENeo4jRelationship');
            $gremlinQuery=new EGremlinScript;
            $gremlinQuery->setQuery('g.e('.$this->getId().').inV');

            $responseData=$this->getConnection()->queryByGremlin($gremlinQuery)->getData();

            if(isset($responseData[0]))
                return $this->_endNode=ENeo4jNode::model()->populateRecord($responseData[0]);
        }
    }

}

?>
