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
    private $startNode; //a container for the startNode object. Lazily loaded via __get()
    private $endNode; //a container for the endNode object. Lazily loaded via __get()

    private static $_modelIndex;

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

    public function getModelIndex()
    {
        if(isset(self::$_modelIndex))
            return self::$_modelIndex;
        else
            return self::$_modelIndex=$this->createModelIndex();
    }

    /**
     * Defines the name of the index used for autoIndexing. Oerwrite if you wanna use a customized index
     * @return string The name of the index for autoIndexing
     */
    public function getModelIndexName()
    {
        return 'Yii_relationship_autoindex';
    }

    public function createModelIndex()
    {
        $index=new ENeo4jRelationshipIndex;
        $index->name=$this->getModelIndexName();
        $index->config=array(
            'provider'=>'lucene',
            'type'=>'fulltext',
        );
        $index->save();

        return $index;
    }
    
    /**
     * This method indexes a relationship and all its attributes. The index used is specified in getModelIndexName() and defaults
     * to modelclass_index. We need that to isntantiate objects according to the modelclass field. When indexing an attribute
     * we have to take care to delete old values as this isn't done automatically. Additionally we add the id of the node as this isn't
     * a property.
     * If the index isn't found it will be created using a fulltext index wth Lucene as provider.
     */
    public function autoIndex()
    {
        
        $index=$this->getModelIndex();

        $batchCommands=array();

        if(!$this->getIsNewResource())
            $batchCommands[]=array('method'=>'DELETE','to'=>'/index/relationship/'.$index->name.'/'.$this->getId());

        foreach($this->getAttributes() as $attribute=>$value)
        {
            if($value instanceof ENeo4jNode)throw new CException(print_r($attribute));
            if(!is_array($value))
            {
                $batchCommands[]=array('method'=>'POST','to'=>'/index/relationship/'.$index->name.'/'.urlencode($attribute).'/'.urlencode($value),'body'=>'{'.$this->batchId.'}');
            }
        }

        //also add the type of the relationship which isn't a property
        $batchCommands[]=array('method'=>'POST','to'=>'/index/relationship/'.$index->name.'/type/'.urlencode($this->type),'body'=>'{'.$this->batchId.'}');
        return $batchCommands;
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
            $this->startNode=$value;
        if ($name=='endNode')
            $this->endNode=$value;
        else
            parent::__get($name);
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

            $transaction=Yii::app()->neo4jSuite->createBatchTransaction();

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
     * Setter for the startNode object
     * @param ENeo4jNode $node
     */
    public function setStartNode(ENeo4jNode $node)
    {
        $this->startNode=$node;
    }

    /**
     * Setter for the endNode object
     * @param ENeo4jNode $node
     */
    public function setEndNode(ENeo4jNode $node)
    {
        $this->endNode=$node;
    }

    /**
     * Get the start node object
     * @return ENeo4jNode The node
     */
    public function getStartNode()
    {
        if(isset($this->startNode))
            return $this->startNode;
        else
        {
            Yii::trace(get_class($this).' is lazyLoading startNode','ext.Neo4jSuite.ENeo4jRelationship');
            return $this->startNode=ENeo4jNode::model()->populateRecord(ENeo4jNode::model()->getRequest(end(explode('/',$this->start))));
        }
    }

    /**
     * Get the end node object
     * @return ENeo4jNode The node
     */
    public function getEndNode()
    {
        if(isset($this->endNode))
            return $this->endNode;
        else
        {
            Yii::trace(get_class($this).' is lazyLoading endNode','ext.Neo4jSuite.ENeo4jRelationship');
            return $this->endNode=ENeo4jNode::model()->populateRecord(ENeo4jNode::model()->getRequest(end(explode('/',$this->end))));
        }
    }

}

?>
