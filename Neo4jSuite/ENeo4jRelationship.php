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
     * Defines the name of the index used for autoIndexing. Oerwrite if you wanna use a customized index
     * @return string The name of the index for autoIndexing
     */
    public function getModelIndexName()
    {
        return 'relationship_auto_index';
    }

    public function getModelIndex()
    {
        if(isset($this->autoIndexModel))
            return $this->autoIndexModel;
        else
            return $this->autoIndexModel=ENeo4jRelationshipIndex::model()->findbyId($this->getModelIndexName());
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
        //don't worry if the index doesn't exist. We'll create one
        try
        {
            $index=$this->getModelIndex();
        }
        catch(EActiveResourceRequestNotFoundException $e)
        {
            $index=new ENeo4jRelationshipIndex;
            $index->name=$this->getModelIndexName();
            $index->config=array(
                'provider'=>'lucene',
                'type'=>'fulltext',
            );
            $index->save();
            $this->autoIndex();
        }

        foreach($this->getAttributes() as $attribute=>$value)
        {
                if(!is_array($value))
                {
                    //delete all old values first!!!
                    if(!$this->getisNewResource())
                        $index->deleteRequest($index->name.'/'.$attribute.'/'.$this->getId());
                    $index->addToIndex($this,$attribute,$value);
                }
        }

        if(!$this->getisNewResource())
        {
            $index->deleteRequest($index->name.'/'.$this->getId().'/'.$this->getId());
            $index->deleteRequest($index->name.'/'.$this->type.'/'.$this->getId());
        }
        
        $index->addToIndex($this,$this->idProperty(),$this->getId());
        $index->addToIndex($this,'type',$this->type);
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

    /**
     * Relationships are created differently to nodes, so we override the ActiveResource method here.
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

            $uri=$this->startNode->getSite().'/'.$this->startNode->getResource().'/'.$this->startNode->getId().'/relationships';
            $returnedmodel=$this->populateRecord($this->customPostRequest(
                $uri,
                array(
                    'to'=>$this->endNode->self,
                    'data'=>$this->getAttributes(),
                    'type'=>$this->type
                    )
                )
            );

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
     * Sets the start node object
     * @param ENeo4jNode $startnode
     */
    public function setStartNode(ENeo4jNode $startnode)
    {
        $this->_startNode=$startnode;
    }

    /**
     * Sets the end node object
     * @param ENeo4jNode $endnode
     */
    public function setEndNode(ENeo4jNode $endnode)
    {
        $this->_endNode=$endnode;
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
            return $this->_startNode=ENeo4jNode::model()->populateRecord(ENeo4jNode::model()->getRequest(end(explode('/',$this->start))));
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
            return $this->_endNode=ENeo4jNode::model()->populateRecord(ENeo4jNode::model()->getRequest(end(explode('/',$this->end))));
    }

}

?>
