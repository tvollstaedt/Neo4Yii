<?php
class ENeo4jPath
{   
    private $_propertyContainers=array();
    private $_relationships;
    private $_nodes;
    
    public function getPropertyContainers()
    {
        return $this->_propertyContainers;
    }
    
    public function getRelationships()
    {
        if(isset($this->_relationships))
                return $this->_relationships;
        
        $relationships=array();
        foreach($this->_propertyContainers as $propertyContainer)
        {
            if($propertyContainer instanceof ENeo4jRelationship)
                $relationships[]=$propertyContainer;
        }
        
        return $this->_relationships=$relationships;
                
    }
    
    public function getNodes()
    {
        if(isset($this->_nodes))
                return $this->_nodes;
        
        $nodes=array();
        foreach($this->_propertyContainers as $propertyContainer)
        {
            if($propertyContainer instanceof ENeo4jNode)
                $nodes[]=$propertyContainer;
        }
        
        return $this->_nodes=$nodes;
                
    }
    
    protected function addPropertyContainer(ENeo4jPropertyContainer $propertyContainer)
    {
        $this->_propertyContainers[]=$propertyContainer;
    }
    
    public static function populatePaths($data)
    {
        $paths=array();
        if(is_array($data))
        {
            foreach($data as $path)
                $paths[]=self::populatePath($path);
        }
        return $paths;
    }
    
    public static function populatePath($data)
    {
        $path=new self;
        if(is_array($data))
        {
            foreach($data as $propertyContainer)
            {
                //is a node
                if(strpos($propertyContainer['self'],'/node/')>0)
                    $path->addPropertyContainer(ENeo4jNode::model()->populateRecord($propertyContainer));
                //is a relationship
                if(strpos($propertyContainer['self'],'/relationship/')>0)
                    $path->addPropertyContainer(ENeo4jRelationship::model()->populateRecord($propertyContainer));
            }
        }
        return $path;
    } 
        
}
?>
