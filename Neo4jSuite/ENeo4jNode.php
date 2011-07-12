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

    private static $_modelIndex;

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
        return 'Yii_node_autoindex';
    }

    public function createModelIndex()
    {
        $index=new ENeo4jNodeIndex;
        $index->name=$this->getModelIndexName();
        $index->config=array(
            'provider'=>'lucene',
            'type'=>'fulltext',
        );
        $index->save();

        return $index;
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

        $index=$this->getModelIndex();
        
        $batchCommands=array();

        if(!$this->getIsNewResource())
            $batchCommands[]=array('method'=>'DELETE','to'=>'/index/node/'.$index->name.'/'.$this->getId());

        foreach($this->getAttributes() as $attribute=>$value)
        {
            if(!is_array($value))
            {
                $batchCommands[]=array('method'=>'POST','to'=>'/index/node/'.$index->name.'/'.urlencode($attribute).'/'.urlencode($value),'body'=>'{'.$this->batchId.'}');
            }
        }
        
        return $batchCommands;
    }

    /**
     * Finds a single property container with the specified id within the modelclass index.
     * @param mixed $id The id.
     * @return ENeo4jPropertyContainer the property container found. Null if none is found.
     */
    public function findById($id)
    {
            Yii::trace(get_class($this).'.findById()','ext.Neo4jSuite.ENeo4jNode');
            $query="start x=(".$id.") where x.".$this->getModelClassField()."='".get_class($this)."' return x";
            $response=$this->getGraphService()->queryByCypher($query);
            if(isset($response['data'][0][0]))
                return $this->populateRecord($response['data'][0][0]);
            else
                return null;
    }

    /**
     * Overrides the ActiveResource method in order to query the property container index for models of the calling finder class
     * To only get back property containers of the same class as the finder instance we have to always add a query for the modelclass attribute
     * If no query is provided all property containers of the same modelclass will be loaded and the first will be returned
     * @return ENeo4jPropertyContainer returns single property container or null if none is found.
     */
    public function findByIndex($query=null)
    {
            Yii::trace(get_class($this).'.find()','ext.Neo4jSuite.ENeo4jNode');

            if($query!=false)
            {
                if($query instanceof ENeo4jLuceneQuery)
                {
                    $query->addStatement($this->getModelClassField().':'.get_class($this),'AND');
                    $queryobject=$query;
                }
                else if(is_string($query))
                {
                    $queryobject=new ENeo4jLuceneQuery;
                    $queryobject->setQueryString($query);
                    $queryobject->addStatement($this->getModelClassField().':'.get_class($this),'AND');
                }
            }
            else
            {
                $queryobject=new ENeo4jLuceneQuery;
                $queryobject->addStatement($this->getModelClassField().':'.get_class($this),'AND');
            }

            $cypherquery='start x=('.$this->getModelIndexName().',"'.$queryobject->getRawQueryString().'") return x limit 1';
            $response=$this->getGraphService()->queryByCypher($cypherquery);

            if(isset($response['data'][0][0]))
                return $this->populateRecord($response['data'][0][0]);
            else
                return null;
    }


    /**
     * Overrides the ActiveResource method in order to query the property container index for all models of the calling finder class
     * To only get back property containers of the same class as the finder instance we have to always add a query for the modelclass attribute
     * If no query is provided all property containers of the same modelclass will be loaded.
     * @return ENeo4jPropertyContainer returns an array of property containers or an empty array if none are found.
     */
    public function findAllByIndex($query=null,$limit=null)
    {
            Yii::trace(get_class($this).'.findAll()','ext.Neo4jSuite.ENeo4jNode');
            
            if($query!=false)
            {
                if($query instanceof ENeo4jLuceneQuery)
                {
                    $query->addStatement($this->getModelClassField().':'.get_class($this),'AND');
                    $queryobject=$query;
                }
                else if(is_string($query))
                {
                    $queryobject=new ENeo4jLuceneQuery;
                    $queryobject->setQueryString($query);
                    $queryobject->addStatement($this->getModelClassField().':'.get_class($this),'AND');
                }
            }
            else
            {
                $queryobject=new ENeo4jLuceneQuery;
                $queryobject->addStatement($this->getModelClassField().':'.get_class($this),'AND');
            }

            if($limit)
                $cypherquery='start x=('.$this->getModelIndexName().',"'.$queryobject->getRawQueryString().'") return x limit '.$limit;
            else
                $cypherquery='start x=('.$this->getModelIndexName().',"'.$queryobject->getRawQueryString().'") return x';
            $response=$this->getGraphService()->queryByCypher($cypherquery);

            $models=array();

            if(isset($response['data'][0][0]))
            {
                foreach($response['data'] as $rep)
                    $models[]=$this->populateRecord($rep[0]);
                return $models;
            }
            else
                return null;
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

            //open a transaction for insert AND autoindexing
            $transaction=$this->getGraphService()->createBatchTransaction();

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
    public function traverse($traversalDescription=null)
    {
        Yii::trace(get_class($this).'.traverse()','ext.Neo4jSuite.ENeo4jNode');
        if($traversalDescription!=null)
            if(is_array($traversalDescription))
            {
                $traverser=new ENeo4jTraversalDescription;
                $traverser->setOptions($traversalDescription);
            }
            else
                $traverser=$traversalDescription;
        else
            $traverser=new ENeo4jTraversalDescription;

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
