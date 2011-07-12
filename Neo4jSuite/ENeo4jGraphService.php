<?php
/**
 * @author Johannes "Haensel" Bauer
 * @since version 0.1
 * @version 0.1
 */

/**
 * ENeo4jGraphService provides some basic information about how the client is able to connect to the graph service
 * All parameters used in rest() are used by the classes extending ENeo4jPropertyContainer and ENeo4jIndex so don't
 * mess around with it unless you know what you are doing
 */
class ENeo4jGraphService extends EActiveResource
{
    
    private $_connection;

    /**
     * Constructor.
     */
    public function __construct($scenario='insert')
    {
        $this->_connection=Yii::app()->neo4j;

        if($scenario===null) // internally used by populateRecord() and model()
		return;

	$this->setScenario($scenario);
        $this->setIsNewResource(true);

	$this->init();

	$this->attachBehaviors($this->behaviors());
	$this->afterConstruct();
    }


    public function rest()
    {
        return array(
            'site'=>$this->getHost().':'.$this->getPort().'/'.$this->getDb(),
            'contenttype'=>'application/json',
            'accepttype'=>'application/json',
        );
    }

    public function getConnection()
    {
        return $this->_connection;
    }

    public function getHost()
    {
        return $this->getConnection()->host;
    }

    public function getPort()
    {
        return $this->getConnection()->port;
    }

    public function getDb()
    {
        return $this->getConnection()->db;
    }

    public function createBatchTransaction()
    {
        return new ENeo4jBatchTransaction;
    }

    public function queryByCypher($cypher)
    {
        return $this->postRequest('ext/CypherPlugin/graphdb/execute_query',array('query'=>$cypher));
    }
    
}

?>
