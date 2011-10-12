<?php

class ENeo4jCypherSort extends CComponent
{
        private $_baseQuery;
        /**
         * @var boolean whether the sorting can be applied to multiple attributes simultaneously.
         * Defaults to false, which means each time the data can only be sorted by one attribute.
         */
        public $multiSort=false;
        /**
         * @var string the name of the model class whose attributes can be sorted.
         * The model class must be a child class of {@link CActiveRecord}.
         */
        public $modelClass;
        /**
         * @var array list of attributes that are allowed to be sorted.
         * For example, array('user_id','create_time') would specify that only 'user_id'
         * and 'create_time' of the model {@link modelClass} can be sorted.
         * By default, this property is an empty array, which means all attributes in
         * {@link modelClass} are allowed to be sorted.
         *
         * This property can also be used to specify complex sorting. To do so,
         * a virtual attribute can be declared in terms of a key-value pair in the array.
         * The key refers to the name of the virtual attribute that may appear in the sort request,
         * while the value specifies the definition of the virtual attribute.
         *
         * In the simple case, a key-value pair can be like <code>'user'=>'user_id'</code>
         * where 'user' is the name of the virtual attribute while 'user_id' means the virtual
         * attribute is the 'user_id' attribute in the {@link modelClass}.
         *
         * A more flexible way is to specify the key-value pair as
         * <pre>
         * 'user'=>array(
         *     'asc'=>'first_name, last_name',
         *     'desc'=>'first_name DESC, last_name DESC',
         *     'label'=>'Name'
         * )
         * </pre>
         * where 'user' is the name of the virtual attribute that specifies the full name of user
         * (a compound attribute consisting of first name and last name of user). In this case,
         * we have to use an array to define the virtual attribute with three elements: 'asc',
         * 'desc' and 'label'.
         *
         * The above approach can also be used to declare virtual attributes that consist of relational
         * attributes. For example,
         * <pre>
         * 'price'=>array(
         *     'asc'=>'item.price',
         *     'desc'=>'item.price DESC',
         *     'label'=>'Item Price'
         * )
         * </pre>
         *
         * Note, the attribute name should not contain '-' or '.' characters because
         * they are used as {@link separators}.
         *
         * Starting from version 1.1.3, an additional option named 'default' can be used in the virtual attribute
         * declaration. This option specifies whether an attribute should be sorted in ascending or descending
         * order upon user clicking the corresponding sort hyperlink if it is not currently sorted. The valid
         * option values include 'asc' (default) and 'desc'. For example,
         * <pre>
         * 'price'=>array(
         *     'asc'=>'item.price',
         *     'desc'=>'item.price DESC',
         *     'label'=>'Item Price',
         *     'default'=>'desc',
         * )
         * </pre>
         *
         * Also starting from version 1.1.3, you can include a star ('*') element in this property so that
         * all model attributes are available for sorting, in addition to those virtual attributes. For example,
         * <pre>
         * 'attributes'=>array(
         *     'price'=>array(
         *         'asc'=>'item.price',
         *         'desc'=>'item.price DESC',
         *         'label'=>'Item Price',
         *         'default'=>'desc',
         *     ),
         *     '*',
         * )
         * </pre>
         * Note that when a name appears as both a model attribute and a virtual attribute, the position of
         * the star element in the array determines which one takes precedence. In particular, if the star
         * element is the first element in the array, the model attribute takes precedence; and if the star
         * element is the last one, the virtual attribute takes precedence.
         */
        public $attributes=array();
        /**
         * @var string the name of the GET parameter that specifies which attributes to be sorted
         * in which direction. Defaults to 'sort'.
         */
        public $sortVar='sort';
        /**
         * @var string the tag appeared in the GET parameter that indicates the attribute should be sorted
         * in descending order. Defaults to 'desc'.
         */
        public $descTag='desc';
        /**
         * @var mixed the default order that should be applied to the query criteria when
         * the current request does not specify any sort. For example, 'name, create_time DESC' or
         * 'UPPER(name)'.
         *
         * Starting from version 1.1.3, you can also specify the default order using an array.
         * The array keys could be attribute names or virtual attribute names as declared in {@link attributes},
         * and the array values indicate whether the sorting of the corresponding attributes should
         * be in descending order. For example,
         * <pre>
         * 'defaultOrder'=>array(
         *     'price'=>true,
         * )
         * </pre>
         *
         * Please note when using array to specify the default order, the corresponding attributes
         * will be put into {@link directions} and thus affect how the sort links are rendered
         * (e.g. an arrow may be displayed next to the currently active sort link).
         */
        public $defaultOrder;
        /**
         * @var string the route (controller ID and action ID) for generating the sorted contents.
         * Defaults to empty string, meaning using the currently requested route.
         */
        public $route='';
        /**
         * @var array separators used in the generated URL. This must be an array consisting of
         * two elements. The first element specifies the character separating different
         * attributes, while the second element specifies the character separating attribute name
         * and the corresponding sort direction. Defaults to array('-','.').
         */
        public $separators=array('-','.');
        /**
         * @var array the additional GET parameters (name=>value) that should be used when generating sort URLs.
         * Defaults to null, meaning using the currently available GET parameters.
         * @since 1.0.9
         */
        public $params;

        private $_directions;

        /**
         * Constructor.
         * @param string $modelClass the class name of data models that need to be sorted.
         * This should be a child class of {@link CActiveRecord}.
         */
        public function __construct($modelClass=null)
        {
                $this->modelClass=$modelClass;
        }

        /**
         * Modifies the query criteria by changing its {@link CDbCriteria::order} property.
         * This method will use {@link directions} to determine which columns need to be sorted.
         * They will be put in the ORDER BY clause. If the criteria already has non-empty {@link CDbCriteria::order} value,
         * the new value will be appended to it.
         * @param CDbCriteria $criteria the query criteria
         */
        public function applyOrder(&$query)
        {
                if(!isset($this->_baseQuery))
                        $this->_baseQuery=$query;
                $order=$this->getOrderBy();
                if(!empty($order))
                {
                    if(isset($this->_baseQuery))
                        $query=$this->_baseQuery.$order;
                    else
                        $query.=$order;
                }
        }

        /**
         * @return string the order-by columns represented by this sort object.
         * This can be put in the ORDER BY clause of a SQL statement.
         * @since 1.1.0
         */
        public function getOrderBy()
        {
                $directions=$this->getDirections();
                if(empty($directions))
                        return is_string($this->defaultOrder) ? $this->defaultOrder : '';
                else
                {
                        
                        $orders=array();
                        foreach($directions as $attribute=>$descending)
                        {
                                $definition=$this->resolveAttribute($attribute);
                                if(is_array($definition))
                                {
                                        if($descending)
                                                $orders[]=isset($definition['desc']) ? $definition['desc'] : " order by n.$attribute DESC";
                                        else
                                                $orders[]=isset($definition['asc']) ? $definition['asc'] : "order by n.$attribute";
                                }
                                else if($definition!==false)
                                {
                                        
                                        $orders[]=$descending ? " order by n.$attribute DESC":" order by n.$attribute";
                                }
                        }
                        return $orders[0];
                }
        }

        /**
         * Generates a hyperlink that can be clicked to cause sorting.
         * @param string $attribute the attribute name. This must be the actual attribute name, not alias.
         * If it is an attribute of a related AR object, the name should be prefixed with
         * the relation name (e.g. 'author.name', where 'author' is the relation name).
         * @param string $label the link label. If null, the label will be determined according
         * to the attribute (see {@link resolveLabel}).
         * @param array $htmlOptions additional HTML attributes for the hyperlink tag
         * @return string the generated hyperlink
         */
        public function link($attribute,$label=null,$htmlOptions=array())
        {
                if($label===null)
                        $label=$this->resolveLabel($attribute);
                if(($definition=$this->resolveAttribute($attribute))===false)
                        return $label;
                $directions=$this->getDirections();
                if(isset($directions[$attribute]))
                {
                        $class=$directions[$attribute] ? 'desc' : 'asc';
                        if(isset($htmlOptions['class']))
                                $htmlOptions['class'].=' '.$class;
                        else
                                $htmlOptions['class']=$class;
                        $descending=!$directions[$attribute];
                        unset($directions[$attribute]);
                }
                else if(is_array($definition) && isset($definition['default']))
                        $descending=$definition['default']==='desc';
                else
                        $descending=false;

                if($this->multiSort)
                        $directions=array_merge(array($attribute=>$descending),$directions);
                else
                        $directions=array($attribute=>$descending);

                $url=$this->createUrl(Yii::app()->getController(),$directions);

                return $this->createLink($attribute,$label,$url,$htmlOptions);
        }

        /**
         * Resolves the attribute label for the specified attribute.
         * This will invoke {@link CActiveRecord::getAttributeLabel} to determine what label to use.
         * If the attribute refers to a virtual attribute declared in {@link attributes},
         * then the label given in the {@link attributes} will be returned instead.
         * @param string $attribute the attribute name.
         * @return string the attribute label
         */
        public function resolveLabel($attribute)
        {
                $definition=$this->resolveAttribute($attribute);
                if(is_array($definition))
                {
                        if(isset($definition['label']))
                                return $definition['label'];
                }
                else if(is_string($definition))
                        $attribute=$definition;
                
                return $attribute;
        }

        /**
         * Returns the currently requested sort information.
         * @return array sort directions indexed by attribute names.
         * The sort direction is true if the corresponding attribute should be
         * sorted in descending order.
         */
        public function getDirections()
        {
                if($this->_directions===null)
                {
                        $this->_directions=array();
                        if(isset($_GET[$this->sortVar]))
                        {
                                $attributes=explode($this->separators[0],$_GET[$this->sortVar]);
                                foreach($attributes as $attribute)
                                {
                                        if(($pos=strrpos($attribute,$this->separators[1]))!==false)
                                        {
                                                $descending=substr($attribute,$pos+1)===$this->descTag;
                                                if($descending)
                                                        $attribute=substr($attribute,0,$pos);
                                        }
                                        else
                                                $descending=false;

                                        if(($this->resolveAttribute($attribute))!==false)
                                        {
                                                $this->_directions[$attribute]=$descending;
                                                if(!$this->multiSort)
                                                        return $this->_directions;
                                        }
                                }
                        }
                        if($this->_directions===array() && is_array($this->defaultOrder))
                                $this->_directions=$this->defaultOrder;
                }
                return $this->_directions;
        }

        /**
         * Returns the sort direction of the specified attribute in the current request.
         * @param string $attribute the attribute name
         * @return mixed the sort direction of the attribut. True if the attribute should be sorted in descending order,
         * false if in ascending order, and null if the attribute doesn't need to be sorted.
         */
        public function getDirection($attribute)
        {
                $this->getDirections();
                return isset($this->_directions[$attribute]) ? $this->_directions[$attribute] : null;
        }

        /**
         * Creates a URL that can lead to generating sorted data.
         * @param CController $controller the controller that will be used to create the URL.
         * @param array $directions the sort directions indexed by attribute names.
         * The sort direction is true if the corresponding attribute should be
         * sorted in descending order.
         * @return string the URL for sorting
         */
        public function createUrl($controller,$directions)
        {
                $sorts=array();
                foreach($directions as $attribute=>$descending)
                        $sorts[]=$descending ? $attribute.$this->separators[1].$this->descTag : $attribute;
                $params=$this->params===null ? $_GET : $this->params;
                $params[$this->sortVar]=implode($this->separators[0],$sorts);
                return $controller->createUrl($this->route,$params);
        }

        public function resolveAttribute($attribute)
        {
                return $attribute;
        }

        /**
         * Creates a hyperlink based on the given label and URL.
         * You may override this method to customize the link generation.
         * @param string $attribute the name of the attribute that this link is for
         * @param string $label the label of the hyperlink
         * @param string $url the URL
         * @param array $htmlOptions additional HTML options
         * @return string the generated hyperlink
         */
        protected function createLink($attribute,$label,$url,$htmlOptions)
        {
                return CHtml::link($label,$url,$htmlOptions);
        }
}
