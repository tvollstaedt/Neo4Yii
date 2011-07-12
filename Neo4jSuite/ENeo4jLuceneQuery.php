<?php
/**
 * @author Johannes "Haensel" Bauer
 * @since version 0.1
 * @version 0.1
 */

/**
 * ENeo4jLuceneQuery is a very simple class used to define basic Lucene queries used by the finder methods of
 * ENeo4jPropertyContainers.
 */
class ENeo4jLuceneQuery
{

    public $defaultOperator='AND'; //If not specified we will always combine statements with AND

    private $_query=''; //The actual query as a string

    /**
     * Add a statement to your Lucene query
     * @param string $statement The statement as string. You have to use the Lucene syntax here. e.g.: "name:Haensel" or "year:[2010 TO 2011]"
     * @param string $operator The operator to combine this statement with existing ones. Defaults to 'AND' if not specified
     */
    public function addStatement($statement,$operator=null)
    {
        if($this->_query)
            if($operator!=null) //Operator set
                $this->_query.=' '.$operator.' '.$statement;
            else
                $this->_query.=' '.$this->defaultOperator.' '.$statement;
        else
            $this->_query.=$statement;
    }

    /**
     * Sets the query string (overwrites any existing string!)
     * @param string $queryString
     */
    public function setQueryString($queryString)
    {
        $this->_query=$queryString;
    }

    /**
     * Get the urlencoded query string
     * @return string The urlencoded query string
     */
    public function getQueryString()
    {
        return urlencode($this->getRawQueryString());
    }

    public function getRawQueryString()
    {
        return $this->_query;
    }

}

?>
