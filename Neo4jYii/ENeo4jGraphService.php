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
class ENeo4jGraphService extends EActiveResourceConnection
{             
    public function rest()
    {
        return $this->getResourceConfiguration(get_class($this));
    }
    
    public function getConnectionString()
    {
        $rest=$this->rest();
        return $rest['site'];
    }

    public function createBatchTransaction()
    {
        return new ENeo4jBatchTransaction($this);
    }

    public function queryByCypher($cypher)
    {
        
    }

    public function queryByGremlin(EGremlinScript $gremlin)
    {
        Yii::trace(get_class($this).'.queryByGremlin()','ext.Neo4jYii.ENeo4jGraphService');
        
        $request=new EActiveResourceRequest;
        $request->setContentType('application/json');
        $request->setAcceptType('application/json');
        $request->setUri($this->getConnectionString().'/ext/GremlinPlugin/graphdb/execute_script');
        $request->setMethod('POST');
        $request->setData(array('script'=>$gremlin->toString()));
        $response=$this->sendRequest($request);

        return $response;
    }
    
            
}

?>
