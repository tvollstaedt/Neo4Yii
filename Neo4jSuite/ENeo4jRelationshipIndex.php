<?php
/**
 * @author Johannes "Haensel" Bauer
 * @since version 0.1
 * @version 0.1
 */

/**
 * ENeo4jNodeIndex represents a relationship index and inherits from ENeo4jIndex.
 * Querying the index will alway return nodes.
 */
class ENeo4jRelationshipIndex extends ENeo4jIndex
{

    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function rest()
    {
        return CMap::mergeArray(
            $this->getGraphService()->rest(),
            array('resource'=>'index/relationship')
        );
    }

    /**
     * Create a relationship index
     * @param array $attributes An array of attributes to be used for creation
     * @return boolean true on success, false on failure
     */
    public function create($attributes=null)
    {
        if(!$this->getIsNewResource())
            throw new EActiveResourceException('The relationship index cannot be inserted because it is not new.');
        if($this->beforeSave())
        {
            Yii::trace(get_class($this).'.create()','ext.Neo4jSuite.ENeo4jRelationshipIndex');

            $this->populateRecord($this->postRequest(null,array('name'=>$this->name,'config'=>$this->config)));

            $this->afterSave();
            $this->setIsNewResource(false);
            $this->setScenario('update');
            return true;

        }
        return false;
    }

    /**
     * Deletes rows with the specified id.
     * @param integer $id primary key value(s).
     */
    public function deleteById($id)
    {
            Yii::trace(get_class($this).'.deleteById()','ext.Neo4jSuite.ENeo4jRelationshipIndex');
            $this->deleteRequest($id);
    }

    /**
     * Lookup a relationship by a key=>value pair. This is not a query, but an exact lookup, so no Lucene specific
     * syntax can be used.
     * @param string $key The key to be searched for
     * @param string $value The value to be searched for
     * @return array an array of ENeo4jRelationship objects
     */
    public function exactLookup($key,$value)
    {
        Yii::trace(get_class($this).'.queryIndex()','ext.Neo4jSuite.ENeo4jRelationshipIndex');
        return ENeo4jRelationship::model()->populateRecords($this->getRequest($this->name.'/'.urlencode($key).'/'.urlencode($value)));
    }

    /**
     * Query the index with a Lucene query
     * @param ENeo4jLuceneQuery $query The query object
     * @return array an array of Neo4jRelationship objects
     */
    public function query(ENeo4jLuceneQuery $query)
    {
        Yii::trace(get_class($this).'.queryIndex()','ext.Neo4jSuite.ENeo4jRelationshipIndex');
        return ENeo4jRelationship::model()->populateRecords($this->getRequest($this->name.'?query='.$query->getQueryString()));
    }


}

?>
