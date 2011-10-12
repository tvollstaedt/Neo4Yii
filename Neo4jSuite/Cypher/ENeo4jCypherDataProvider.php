<?php

class ENeo4jCypherDataProvider extends CDataProvider
{
        
        public $query;

        private $_pagination;
        private $_sort;
        
        public function __construct($config)
        {
                foreach($config as $key=>$value)
                        $this->$key=$value;
        }

        public function getSort()
        {
            if($this->_sort===null)
            {
                $this->_sort=new ENeo4jCypherSort;
                if(($id=$this->getId())!='')
                    $this->_sort->sortVar=$id.'_sort';
            }
            return $this->_sort;
        }

        public function getPagination()
        {
            if($this->_pagination===null)
            {
                $this->_pagination=new ENeo4jCypherPagination;
                if(($id=$this->getId())!='')
                    $this->_pagination->pageVar=$id.'_page';
            }
            return $this->_pagination;
        }

        /**
         * Fetches the data from the persistent data storage.
         * @return array list of data items
         */
        protected function fetchData()
        {

                if(($sort=$this->getSort())!==false)
                {
                    $sort->applyOrder($this->query);
                }

                if(($pagination=$this->getPagination())!==false)
                {
                        $pagination->setItemCount($this->getTotalItemCount());
                        $pagination->applyLimit($this->query);
                }

                $data=ENeo4jGraphService::model()->queryByCypher($this->query);
                $results=array();
                if(isset($data['data']))
                    if(is_array($data['data']))
                        foreach($data['data'] as $resultset)
                            foreach($resultset as $result)
                                $results[]=ENeo4jNode::model()->populateRecord($result);
                return $results;
        }

        /**
         * Fetches the data item keys from the persistent data storage.
         * @return array list of data item keys.
         */
        protected function fetchKeys()
        {
                $keys=array();
                foreach($this->getData() as $i=>$data)
                {
                        $key=$data->getId();
                        $keys[$i]=is_array($key) ? implode(',',$key) : $key;
                }
                return $keys;
        }

        /**
         * Calculates the total number of data items.
         * @return integer the total number of data items.
         */
        protected function calculateTotalItemCount()
        {
                $graph=new ENeo4jGraphService;
                $count=$graph->queryByCypher($this->query.'return count(n)');
                if(isset($count['data'][0][0]))
                    return $count['data'][0][0];
                else
                    return 0;
        }
}
