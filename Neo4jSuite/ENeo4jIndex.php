<?php
/**
 * @author Johannes "Haensel" Bauer
 * @since version 0.1
 * @version 0.1
 */

/**
 * ENeo4jIndex is the base class for ENeo4jNodeIndex and ENeo4jRelationshipIndex and provides the basic functionalities both
 * indices have in common.
 * When creating an index you always have to specify a name. If you want to create a customized index add an attribute
 * called config and define it as an array with the parameters used to create the index
 * (e.g.: $index->config=array('provider'=>'lucene','type'=>'fulltext'))
 */
abstract class ENeo4jIndex extends EActiveResource
{

    public $name; //name of the index
    public $config=array(); //the config array for customized indices

    private $_graphService; //the graphService object for all rest specific information
    
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    /**
     * We use the rest() function of the graphService object. Define necessary changes there.
     */
    public function rest()
    {
        $this->getGraphService()->rest();
    }

    /**
     * Getter for the graphService object
     * @return ENeo4jGraphService
     */
    public function getGraphService()
    {
        if(isset($this->_graphService))
                return $this->_graphService;
        else
            return $this->_graphService=Yii::app()->neo4jSuite;
    }

    /**
     * Overrides the EActiveResource update()
     * Indices cannot be updated, so throw an ENeo4jException if one dares to do that
     */
    public function update($attributes=null)
    {

            throw new ENeo4jException('Unable to update index', 403);
    }

    /**
     * Overrides the EActiveResource updatebyId()
     * Indices cannot be updated, so throw an ENeo4jException if one dares to do that
     */
    public function updateById($id,$attributes=null)
    {

            throw new ENeo4jException('Unable to update index', 403);
    }


    /**
     * Finds a an index with the specified id/name.
     * @param mixed $indexname The name of the index
     * @return ENeo4jIndex the index found. Null if none is found.
     */
    public function findById($indexname)
    {
            Yii::trace(get_class($this).'.findById()','ext.Neo4jSuite.ENeo4jIndex');
            $allindices=$this->getRequest();
            if(!$allindices)
                throw new EActiveResourceRequestNotFoundException ("Index $indexname not found");
            if(array_key_exists($indexname,$allindices))
                return $this->populateRecord(CMap::mergeArray(array('name'=>$indexname),$allindices[$indexname]));
            else
                throw new EActiveResourceRequestNotFoundException ("Index $indexname not found");
    }

    /**
     * Deletes the index with the specified name.
     * @param mixed $id The name of the index
     */
    public function deleteById($id)
    {
            Yii::trace(get_class($this).'.deleteById()','ext.Neo4jSuite.ENeo4jIndex');
            $this->deleteRequest($id);
    }

    /**
     * Add a property container to the index
     * @param ENeo4jPropertyContainer $propertyContainer The property container object (either a node or a relationship)
     * @param string $key The key to be used for indexing
     * @param string $value The value to be used for indexing
     */
    public function addToIndex($propertyContainer,$key,$value)
    {
        Yii::trace(get_class($this).'.addToIndex()','ext.Neo4jSuite.ENeo4jIndex');
        $this->postRequest($this->name.'/'.urlencode($key).'/'.urlencode($value),$propertyContainer->self);
    }

    public function deletePropertyContainerEntry($id)
    {
        if($id)
            $this->deleteRequest($this->name.'/'.$id);
        else
            throw new ENeo4jException('Id cannot be null',500);
    }

}

?>

