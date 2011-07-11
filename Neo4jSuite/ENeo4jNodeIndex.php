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
class ENeo4jNodeIndex extends ENeo4jIndex
{

    public static function model($className=__CLASS__)
    {
        return parent::model($className);
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
            throw new EActiveResourceException('The node index cannot be inserted because it is not new.');
        if($this->beforeSave())
        {
            Yii::trace(get_class($this).'.create()','ext.Neo4jSuite.ENeo4jNodeIndex');

            $this->populateRecord($this->postRequest(null,array('name'=>$this->name,'config'=>$this->config)));

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
        Yii::trace(get_class($this).'.queryIndex()','ext.Neo4jSuite.ENeo4jNodeIndex');
        return ENeo4jNode::model()->populateRecords($this->getRequest($this->name.'/'.urlencode($key).'/'.urlencode($value)));
    }

    /**
     * Query the index with a Lucene query
     * @param ENeo4jLuceneQuery $query The query object
     * @return array an array of Neo4jNode objects
     */
    public function query(ENeo4jLuceneQuery $query)
    {
        Yii::trace(get_class($this).'.queryIndex()','ext.Neo4jSuite.ENeo4jNodeIndex');
        try
        {
            return ENeo4jNode::model()->populateRecords($this->getRequest($this->name.'?query='.$query->getQueryString()));
        }
        catch (EActiveResourceException $e)
        {
            throw new ENeo4jException('Error querying the node index. Check the syntax');
        }
    }


}

?>

