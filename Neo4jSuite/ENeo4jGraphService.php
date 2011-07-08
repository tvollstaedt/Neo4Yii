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

    public function rest()
    {
        return array(
            'site'=>'http://localhost:7474/db/data',
            'contenttype'=>'application/json',
            'accepttype'=>'application/json',
        );
    }

    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }
    
}

?>
