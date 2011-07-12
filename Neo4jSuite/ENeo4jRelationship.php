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
     * Finds a single relationship with the specified id. Other than the node findById() method this method
     * currently uses 2 requests. One to determine if the relationship is of the same class as the finder and the second
     * to actually query for the relationship.
     * @param mixed $id The id.
     * @return ENeo4jRelationship the relationship found. Null if none is found.
     */
    public function findById($id)
    {
            Yii::trace(get_class($this).'.findById()','ext.Neo4jSuite.ENeo4jRelationship');
            try
            {
                //this is super ugly but there is no easy way to use Cypher, so use 2 classic requests, one for the modelclass property and then for the relationship
                $modelclass=$this->getRequest($id.'/properties/'.$this->getModelClassField());
                if($modelclass==get_class($this))
                    return $this->populateRecord($this->getRequest($id));
                else
                    return null; //the id was found, but isn't of the same class as the finder
            }
            catch(EActiveResourceRequestNotFoundException $e)
            {
                return null;
            }
    }

    /**
     * Finds a single relationship using a Lucene query. CAUTION: this method is implemented using
     * a standard Lucene query that can't currently be limited. That means that your query can return a large
     * number of results while only the first result in the array will be returned. The problem is that this could
     * be very exhaustive for the server, so use with taste.
     * @param mixed $query The lucene query. Can either be a ENeo4jLuceneQuery object or a string.
     * @return ENeo4jRelationship returns single relationship or null if none is found.
     */
    public function findByIndex($query=null)
    {
            Yii::trace(get_class($this).'.find()','ext.Neo4jSuite.ENeo4jRelationship');
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
     * Finds relationships using a Lucene query. CAUTION: this method is implemented using
     * a standard Lucene query that can't currently be limited. That means that your query can return a large
     * number of results. This could be very exhaustive for the server, so use with taste.
     * @param mixed $query The lucene query. Can either be a ENeo4jLuceneQuery object or a string.
     * @return array returns an array of ENeo4jRelationships or null if none is found
     */
    public function findAllByIndex($query=null)
    {
            Yii::trace(get_class($this).'.findAll()','ext.Neo4jSuite.ENeo4jPropertyRelationship');
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
