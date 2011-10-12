<?php

class ENeo4jDbConnection extends CApplicationComponent
{
    public $host;
    public $port;
    public $db;
    
    public function getConnectionString()
    {
        return $this->host.':'.$this->port.'/'.$this->db;
    }

}

?>
