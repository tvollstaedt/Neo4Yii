<?php
/**
 * @author Johannes "Haensel" Bauer
 * @since version 0.1
 */
/**
 * Basically the same class as EActiveResourceMetaData but adding traversals for Neo4j nodes
 */
class ENeo4jMetaData
{

    public $properties;     //The properties of the resource according to the schema configuration
    public $traversals=array();
    
    public $attributeDefaults=array();
    
    public $schema;

    private $_model;

    public function __construct($model)
    {
            $this->_model=$model;

            if(($resourceConfig=$model->rest())===null)
                    throw new EActiveResourceException(Yii::t('ext.EActiveResource','The resource "{resource}" configuration could not be found in the activeresource configuration.',array('{resource}'=>get_class($model))));
           
            $this->schema=new EActiveResourceSchema($resourceConfig,$model->properties());
                                                
            $this->properties=$this->schema->properties;

            foreach($this->properties as $name=>$property)
            {
                if($property->defaultValue!==null)
                        $this->attributeDefaults[$name]=$property->defaultValue;
            }
            
            if($model instanceof ENeo4jNode)
                foreach($model->traversals() as $name=>$config)
                        $this->addTraversal($name,$config);
    }
    
    public function addTraversal($name,$config)
    {
            if(isset($config[0],$config[1],$config[2]))
                    $this->traversals[$name]=$config;
            else
                    throw new ENeo4jException(Yii::t('ext.','"{class}" has an invalid configuration for traversal "{traversal}".', array('{class}'=>get_class($this->_model),'{traversal}'=>$name)));
    }
    
    public function setProperties($properties)
    {
        foreach($properties as $property=>$propertyConfig)
        {
           $propertyObject=new EActiveResourceProperty($propertyConfig);
           $this->properties[$property]=$propertyObject;
        }
    }
    
    public function getSchema()
    {
        return $this->schema;
    }
}

?>