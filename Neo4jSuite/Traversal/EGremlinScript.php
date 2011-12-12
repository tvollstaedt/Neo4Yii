<?php

class EGremlinScript
{
    //LANGUAGE COONSTRUCTS
    const GREMLIN_STEPSEPARATOR='.';
    const GREMLIN_STEP_G='g';
    const GREMLIN_STEP_V='v';
    const GREMLIN_STEP_IN='in';
    const GREMLIN_STEP_INE='inE';
    const GREMLIN_STEP_OUT='out';
    const GREMLIN_STEP_OUTE='outE';
    const GREMLIN_STEP_AS='as';
    const GREMLIN_STEP_BACK='back';
    
    private $_scriptString='';
    private $_params=array();
    
    public function __construct($scriptString='')
    {
        $this->_scriptString=$scriptString;
    }
    
    public function toString()
    {
        return $this->getScriptString();
    }
    
    protected function getScriptString()
    {
        return $this->_scriptString;
    }
    
    protected function setScriptString($scriptString)
    {
        $this->_scriptString=$scriptString;
    }
    
    protected function addStep($stepString)
    {
        if($this->getScriptString()=='')
            $this->setScriptString($stepString);
        else
            $this->setScriptString($this->getScriptString().self::GREMLIN_STEPSEPARATOR.$stepString);
    }
    
    //ADD CUSTOM STEP
    
    public function setQuery($query)
    {
        $this->setScriptString($query);
        return $this;
    }
    
    //GREMLIN STEPS
    
    public function g()
    {
        $this->addStep(self::GREMLIN_STEP_G);
        return $this;
    }
    
    public function v($param='')
    {
        if($param!='')
            $this->addStep(self::GREMLIN_STEP_V.'("'.$param.'")');
        else
            $this->addStep(self::GREMLIN_STEP_V.'()');
        
        return $this;
    }
    
    public function out($param='')
    {
        if($param!='')
            $this->addStep(self::GREMLIN_STEP_OUT.'("'.$param.'")');
        else
            $this->addStep(self::GREMLIN_STEP_OUT.'()');
        
        return $this;
    }
    
    public function outE($param='')
    {
        if($param!='')
            $this->addStep(self::GREMLIN_STEP_OUTE.'("'.$param.'")');
        else
            $this->addStep(self::GREMLIN_STEP_OUTE.'()');
        
        return $this;
    }
    
    public function in($param='')
    {
        if($param!='')
            $this->addStep(self::GREMLIN_STEP_IN.'("'.$param.'")');
        else
            $this->addStep(self::GREMLIN_STEP_IN.'()');
        
        return $this;
    }
    
    public function inE($param='')
    {
        if($param!='')
            $this->addStep(self::GREMLIN_STEP_INE.'("'.$param.'")');
        else
            $this->addStep(self::GREMLIN_STEP_INE.'()');
        
        return $this;
    }
    
    public function _as($stepName)
    {
        $this->addStep(self::GREMLIN_STEP_AS.'("'.$stepName.'")');
        return $this;
    }
    
    public function back($step)
    {
        if(is_integer($step))
            $this->addStep(self::GREMLIN_STEP_BACK.'('.$step.')');
        else
            $this->addStep(self::GREMLIN_STEP_BACK.'("'.$step.'")');
        return $this;
    }
    
    public function hasParams()
    {
        if(count($this->getParams())>0)
                return true;
        else
            return false;
    }
    
    public function getParams()
    {
        return $this->_params;
    }
    
    public function setParam($param,$value)
    {
        $this->_params[$param]=$value;
    }
}
?>
