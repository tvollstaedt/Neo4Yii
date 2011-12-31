<?php
/**
 * @author Johannes "Haensel" Bauer
 * @since version 0.1
 */
/**
 * Basically the same class as EActiveResourceMetaData but adding relations for Neo4j nodes
 */
class ENeo4jMetaData
{

    public $properties;     //The properties of the resource according to the schema configuration
    public $relations=array();
    
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
                foreach($model->relations() as $name=>$config)
                        $this->addRelation($name,$config);
    }
    
    public function addRelation($name,$config)
        {
                if(isset($config[0],$config[1],$config[2]))
                        $this->relations[$name]=$config;
                else
                        throw new ENeo4jException(Yii::t('ext.','"{class}" has an invalid configuration for relation "{relation}".', array('{class}'=>get_class($this->_model),'{relation}'=>$name)));
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