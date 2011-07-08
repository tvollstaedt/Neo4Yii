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

    /**
     * Defines the name of the index used for autoIndexing. Oerwrite if you wanna use a customized index
     * @return string The name of the index for autoIndexing
     */
    public function getModelIndexName()
    {
        return 'node_auto_index';
    }

   public function getModelIndex()
    {
        if(isset($this->autoIndexModel))
            return $this->autoIndexModel;
        else
            return $this->autoIndexModel=ENeo4jNodeIndex::model()->findbyId($this->getModelIndexName());
    }
    /**
     * This method indexes a node and all its attributes. The index used is specified in getModelIndexName() and defaults
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
            $index=new ENeo4jNodeIndex;
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
                        $index->deleteRequest($index->name.'/'.$attribute.'/'.$this->self);
                    $index->addToIndex($this,$attribute,$value);
                }
        }

        if(!$this->getisNewResource())
            $index->deleteRequest($index->name.'/'.$this->getId().'/'.$this->self);
        $index->addToIndex($this,'id',$this->getId());
    }

    /**
     * Nodes are created differently to relationships, so we override the ActiveResource method here.
     * @param array $attributes The attributes to be used when creating the node
     * @return boolean true on success, false on failure
     */
    public function create($attributes=null)
     {
        if(!$this->getIsNewResource())
            throw new EActiveResourceException('The node cannot be inserted because it is not new.');
        if($this->beforeSave())
        {
            Yii::trace(get_class($this).'.create()','ext.Neo4jSuite.ENeo4jNode');

            $returnedmodel=$this->populateRecord($this->postRequest(null,$this->getAttributes()));

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
     * If no type is pecified all outgoing relationships will be returned
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
        return ENeo4jRelationship::model()->populateRecords(ENeo4jRelationship::model()->customGetRequest($uri));
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

    /**
     * Other than the finder methods this method traverses the graph and doesn't search the index.
     * Therefore EVERY kind of node or relationship can be returned. Even mixtured models.
     * To define a traversal you have to create an instance of ENeo4jTraversalDescription to define how to traverse
     * through the grapg
     * @param ENeo4jTraversalDescription $traversalDescription The traversal description object
     * @return array an array of ENeo4jPropertyContainers according to their modelclass field.
     */
    public function traverse($traversalDescription)
    {
        Yii::trace(get_class($this).'.traverse()','ext.Neo4jSuite.ENeo4jNode');
        if(is_array($traversalDescription))
        {
            $traverser=new ENeo4jTraversalDescription;
            $traverser->setOptions($traversalDescription);
        }
        else
            $traverser=$traversalDescription;
        switch($traverser->getReturnType())
        {
            case ENeo4jTraversalDescription::RETURN_NODES:
                return ENeo4jNode::model()->populateRecords($this->postRequest($this->getId(),$traverser->toArray(),'/traverse/node'));
            case ENeo4jTraversalDescription::RETURN_RELATIONSHIPS:
                return ENeo4jRelationship::model()->populateRecords($this->postRequest($this->getId(),$traverser->toArray(),'/traverse/relationship'));
            default:
                throw new ENeo4jException('Returntype not implemented');

        }


    }

}

?>
