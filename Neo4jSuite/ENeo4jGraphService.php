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
class ENeo4jGraphService extends CApplicationComponent
{
    public $host;
    public $port;
    public $database;

    public function rest()
    {
        return array(
            'site'=>$this->host.':'.$this->port.'/'.$this->database,
            'contenttype'=>'application/json',
            'accepttype'=>'application/json',
        );
    }

    public function createBatchTransaction()
    {
        return new ENeo4jBatchTransaction;
    }
    
}

?>
