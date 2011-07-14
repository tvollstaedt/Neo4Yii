<?php
/**
 * @author Johannes "Haensel" Bauer
 * @since version 0.1
 * @version 0.1
 */

/**
 * ENeo4jNodeIndex represents a node index and inherits from ENeo4jIndex.
 * Querying the index will alway return nodes.
 */
class ENeo4jNodeAutoIndex extends ENeo4jIndex
{
    public static $configuration=array(
        'name'=>'Yii_node_autoindex',
        'config'=>array(
            'type'=>'fulltext',
            'provider'=>'lucene',
        ));

    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function init()
    {
        $this->config=self::$configuration;
        $this->name=self::$configuration['name'];
        $this->type=self::$configuration['config']['type'];
    }

    public function afterConstruct()
    {
        $this->create();
    }

    public function rest()
    {
        return CMap::mergeArray(
            $this->getGraphService()->rest(),
            array('resource'=>'index/node')
        );
    }

    /**
     * Create a node index
     * @param array $attributes An array of attributes to be used for creation
     * @return boolean true on success, false on failure
     */
    public function create($attributes=null)
    {
        if(!$this->getIsNewResource())
            throw new EActiveResourceException('The autonode index cannot be inserted because it is not new.');
        if($this->beforeSave())
        {
            Yii::trace(get_class($this).'.create()','ext.Neo4jSuite.ENeo4jNodeAutoIndex');

            if($attributes)
                $this->postRequest(null,$attributes);
            else
                $this->postRequest(null,self::$configuration);

            $this->afterSave();
            $this->setIsNewResource(false);
            $this->setScenario('update');
            return true;

        }
        return false;
    }

    /**
     * Lookup a node by a key=>value pair. This is not a query, but an exact lookup, so no Lucene specific
     * syntax can be used.
     * @param string $key The key to be searched for
     * @param string $value The value to be searched for
     * @return array an array of ENeo4jNode objects
     */
    public function exactLookup($key,$value)
    {
        Yii::trace(get_class($this).'.queryIndex()','ext.Neo4jSuite.ENeo4jNodeAutoIndex');
        return ENeo4jNode::model()->populateRecords($this->getRequest(self::$configuration['name'].'/'.urlencode($key).'/'.urlencode($value)));
    }

    /**
     * Query the index with a Lucene query
     * @param ENeo4jLuceneQuery $query The query object
     * @return array an array of Neo4jNode objects
     */
    public function query(ENeo4jLuceneQuery $query,$limit=null)
    {
        Yii::trace(get_class($this).'.query()','ext.Neo4jSuite.ENeo4jNodeAutoIndex');
        if($limit)
            $cypherquery='start x=('.self::$configuration['name'].',"'.$query->getRawQueryString().'") return x limit '.$limit;
        else
            $cypherquery='start x=('.self::$configuration['name'].',"'.$query->getRawQueryString().'") return x';

        try
        {
            $response=$this->getGraphService()->queryByCypher($cypherquery);
        }
        catch(EActiveResourceRequestException $e)
        {
            throw new ENeo4jException('Error querying the index. Check the syntax!');
        }
        

        $models=array();

        if(isset($response['data'][0][0]))
        {
            foreach($response['data'] as $rep)
                $models[]=ENeo4jNode::model()->populateRecord($rep[0]);
            return $models;
        }
        else
            return null;
    }


}

?>

