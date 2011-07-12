<?php

class ENeo4jBatchTransaction extends EActiveResource
{

    private $_graphService;

    public $instances=array(); //this is an array of instances used within the transaction
    public $operations=array();

    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    /**
     * We use the rest() function of the graphService object. Define necessary changes there.
     */
    public function rest()
    {
        return CMap::mergeArray(
            $this->getGraphService()->rest(),
            array('resource'=>'batch')
        );
    }

    public function getGraphService()
    {
        if(isset($this->_graphService))
                return $this->_graphService;
        else
            return $this->_graphService=new ENeo4jGraphService;
    }

    /**
     * This method is used to collect all instances that are used within a transaction.
     * It is only called internally.
     * @param ENeo4jPropertyContainer $propertyContainer
     */
    protected function addToInstances(ENeo4jPropertyContainer $propertyContainer)
    {
        $this->instances[$propertyContainer->batchId]=$propertyContainer;
    }

    /**
     * Add a save operation to the transaction. You can either use this for a ENeo4jNode object or a ENeo4jRelationship
     * object. If used with validation this method will throw an ENeo4jTransactionException if one of the models fails validation.
     * @param ENeo4jPropertyContainer $propertyContainer
     * @param boolean $validate Defaults to true meaning that the model is validated before it is added to the batch colleciton
     */
    public function addSaveOperation(ENeo4jPropertyContainer $propertyContainer,$validate=true)
    {
        if($validate && !$propertyContainer->validate())
            throw new ENeo4jTransactionException('Transaction failure. One or more models did not validate!',500);

        $propertyContainer->assignBatchId(count($this->operations));
        $this->addToInstances($propertyContainer);

        //update if not new!
        if(!$propertyContainer->getIsNewResource())
            $this->addUpdateOperation($propertyContainer);
                
        switch($propertyContainer)
        {
            case ($propertyContainer instanceof ENeo4jNode):

                $this->operations[]=array(
                    'method'=>'POST',
                    'to'=>'/'.$propertyContainer->getResource(),
                    'body'=>$propertyContainer->getAttributes(),
                    'id'=>$propertyContainer->batchId
                );
            break;

            case ($propertyContainer instanceof ENeo4jRelationship):

                //first, check if the start and end nodes have a batch id,
                //otherwise this isn't an overall transaction (nodes were created before and can't be referenced with {id})!!
                $startBatch=$propertyContainer->startNode->batchId;
                $endBatch=$propertyContainer->endNode->batchId;

                if(isset($startBatch) && isset($endBatch))
                    $this->operations[]=array(
                        'method'=>'POST',
                        'to'=>'{'.$propertyContainer->getStartNode()->batchId.'}/relationships',
                        'body'=>array(
                            'to'=>'{'.$propertyContainer->getEndNode()->batchId.'}',
                            'type'=>$propertyContainer->type,
                            'data'=>$propertyContainer->getAttributes(),
                        ),
                        'id'=>$propertyContainer->batchId,);

                else
                    $this->operations[]=array(
                        'method'=>'POST',
                        'to'=>'/node/'.$propertyContainer->getStartNode()->getId().'/relationships',
                        'body'=>array(
                            'to'=>$propertyContainer->endNode->self,
                            'type'=>$propertyContainer->type,
                            'data'=>$propertyContainer->getAttributes(),
                        ),
                        'id'=>$propertyContainer->batchId,
                        );
                
            break;

        }

        if($propertyContainer->autoIndexing)
            $this->addAutoIndexOperation($propertyContainer);

    }

    /**
     * Add a update operation to the transaction. This is automatically used when calling addSaveOperation() on a transaction
     * and passing a model that is not new (and will therefore be updated).
     * @param ENeo4jPropertyContainer $propertyContainer
     * @param boolean $validate Defaults to true. Validates the model and throws ENeo4jTransactionException if validation fails.
     */
    protected function addUpdateOperation(ENeo4jPropertyContainer $propertyContainer,$validate=true)
    {
        if($validate && !$propertyContainer->validate())
            throw new ENeo4jTransactionException('Transaction failure. One or more models did not validate!',500);

        $propertyContainer->assignBatchId(count($this->operations));
        $this->addToInstances($propertyContainer);

        $this->operations[]=array(
            'method'=>'PUT',
            'to'=>'/'.$propertyContainer->getResource().'/'.$propertyContainer->getId().'/properties',
            'body'=>$propertyContainer->getAttributes(),
            'id'=>$propertyContainer->batchId
        );

        if($propertyContainer->autoIndexing)
            $this->addAutoIndexOperation($propertyContainer);

    }

    public function addAutoIndexOperation(ENeo4jPropertyContainer $propertyContainer)
    {
        foreach($propertyContainer->autoIndex() as $indexoperation)
            $this->operations[]=$indexoperation;
    }

    public function execute()
    {
        Yii::trace(get_class($this).'.execute()','ext.Neo4jSuite.ENeo4jBatchTransaction');
        try
        {
            if($this->operations) //if there are any operations, send post request, otherwise ignore it as it would return an error by Neo4j
            {
                //clean all batchIds of the objects we used during the transaction
                foreach($this->instances as $instance)
                    $instance->assignBatchId(null);
                return $this->postRequest(null,$this->operations);
            }

        }
        catch (EActiveResourceException $e)
        {
            throw new ENeo4jTransactionException('Transaction failure '.$e->getMessage(),500);
        }
    }

}

?>
