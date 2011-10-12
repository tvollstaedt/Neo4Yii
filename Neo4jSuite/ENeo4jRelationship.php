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
    public $type;
    private $_startNode; //a container for the startNode object. Lazily loaded via __get()
    private $_endNode; //a container for the endNode object. Lazily loaded via __get()

    public static function model($className=__CLASS__)
    {
        return parent::model($className);
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
     * Enable lazy loading for start and end nodes
     */
    public function __get($name)
    {
        if($name=='startNode')
            return $this->getStartNode();
        if ($name=='endNode')
            return $this->getEndNode();
        else
            return parent::__get($name);
    }

    public function __set($name,$value)
    {

        if($name=='startNode')
            $this->_startNode=$value;
        else if ($name=='endNode')
            $this->_endNode=$value;
        else
            parent::__set($name,$value);
    }
    
    /**
     * Relationships are created differently to nodes, so we override the ActiveResource method here.
     * CAUTION: This is a transactional method, meaning that creating and indexing are done via a ENeo4jBatchTransaction
     * @param array $attributes The attributes to be used when creating the relationship
     * @return boolean true on success, false on failure
     */
    public function create($attributes=null)
    {
        if(!$this->getIsNewResource())
            throw new EActiveResourceException('The relationship cannot be inserted because it is not new.',500);

        //check if one of the vital infos isn't there
        if($this->endNode->self==null || $this->type==null)
                throw new ENeo4jException('You cannot save a relationship without defining type, startNode and endNode',500);

        if($this->beforeSave())
        {
            Yii::trace(get_class($this).'.create()','ext.Neo4jSuite.ENeo4jRelationship');

            $transaction=$this->getGraphService()->createBatchTransaction();

            //send by reference, because we want to assign a batchId!
            $transaction->addSaveOperation($this);

            $response=$transaction->execute();

            $returnedmodel=$this->populateRecord($response[0]['body']);

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
            Yii::trace(get_class($this).'.findById()','ext.Neo4jSuite.ENeo4jRelationship');
            $gremlinquery='g.e('.$id.').filter{it.'.$this->getModelClassField().'=="'.get_class($this).'"}';
            $response=$this->getGraphService()->queryByGremlin($gremlinquery);
            if(isset($response[0]))
            {
                $model=$this->populateRecords($response);
                return $model[0];
            }
            else
                return null;
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
            Yii::trace(get_class($this).' is lazyLoading startNode','ext.Neo4jSuite.ENeo4jRelationship');
            return $this->_startNode=ENeo4jNode::model()->populateRecord(ENeo4jNode::model()->getRequest(end(explode('/',$this->start))));
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
            Yii::trace(get_class($this).' is lazyLoading endNode','ext.Neo4jSuite.ENeo4jRelationship');
            return $this->_endNode=ENeo4jNode::model()->populateRecord(ENeo4jNode::model()->getRequest(end(explode('/',$this->end))));
        }
    }

}

?>
