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
class ENeo4jGraphService
{
    
    private $_connection;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->_connection=Yii::app()->neo4j;
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
        Yii::trace(get_class($this).'.queryByCypher()','ext.Neo4jSuite.ENeo4jGraphService');
        try{
            $request=new EActiveResourceRequest();
            $request->setUri($this->getHost().':'.$this->getPort().'/'.$this->getDb().'/'.'ext/CypherPlugin/graphdb/execute_query');
            $request->setMethod('POST');
            $request->setAcceptType('application/json');
            $request->setContentType('application/json');
            $request->setData(array('query'=>$cypher));
            return $request->run();
        }
        catch(EActiveResourceException $e)
        {
            throw new ENeo4jScriptException('Error executing script',500);
        }
    }

    public function queryByGremlin(EGremlinScript $gremlin)
    {
        Yii::trace(get_class($this).'.queryByGremlin()','ext.Neo4jSuite.ENeo4jGraphService');
        try{
            $request=new EActiveResourceRequest();
            $request->setUri($this->getHost().':'.$this->getPort().'/'.$this->getDb().'/'.'ext/GremlinPlugin/graphdb/execute_script');
            $request->setMethod('POST');
            $request->setAcceptType('application/json');
            $request->setContentType('application/json');
            $request->setData(array('script'=>$gremlin->toString()));
            
            return $request->run();
        }
        catch(EActiveResourceException $e)
        {
            throw new ENeo4jScriptException('Error executing script',500);
        }
    }
    
}

?>
