<?php
/**
 * @author Johannes "Haensel" Bauer, a lot of ideas and thoughts from https://github.com/onewheelgood/Neo4J-REST-PHP-API-client
 * @since version 0.1
 * @version 0.1
 */

/**
 * ENeo4jTraversalDescription is used to specify how to traverse a graph
 */
class ENeo4jTraversalDescription
{

        const BREADTH_FIRST = 'breadth first';
	const DEPTH_FIRST = 'depth first';

	const RETURN_NODES = "node";
	const RETURN_RELATIONSHIPS = "relationship";
	const RETURN_PATH = "path";

	const DIRECTION_BOTH = 'all';
	const DIRECTION_INCOMING = 'in';
	const DIRECTION_OUTGOING = 'out';

	private $_startnode;
        private $_returntype;
        private $_traversalDescription=array();
	private $_order;
	private $_uniqueness;
	private $_relationships;
	private $_pruneEvaluator;
	private $_returnFilter;
	private $_maxDepth;

        private static $_allowedOptions=array(
            'startnode'         =>'_startnode',
            'returntype'        =>'_returntype',
            'order'             =>'_order',
            'uniqueness'        =>'_uniqueness',
            'relationships'     =>'_relationships',
            'prune evaluator'   =>'_pruneEvaluator',
            'return filter'     =>'_returnFilter',
            'max depth'         =>'_maxDepth',
        );

        public function __construct()
        {
            if(!$this->_returntype)
                $this->setReturnType(self::RETURN_NODES);
        }

        /**
         * Sets the traversalDescription according to the array input. Only options that are defined
         * self::$_allowedOptions are set.
         * @param array $data The options to be set as parameter=>value pairs.
         */
        public function setOptions($data)
        {
            foreach($data as $option=>$value)
                if(key_exists($option, $variable=self::$_allowedOptions))
                {
                    $this->$variable[$option]=$value;
                    $this->_traversalDescription[$variable[$option]] = $this->$variable[$option];
                }
                else
                    throw new ENeo4jTraversalException('Option "'.$option.'" not recognized. Check the spelling');
        }

        /**
         * Adds a relationship type to your description
         * @param string $type The type of relationship you want to add
         * @param string $direction The direction of the relationship ('all','out','in'). Defaults to all.
         */
	public function addRelationshipType($type, $direction=NULL)
	{
		if ( $direction ) {
			$this->_relationships[] = array( 'type' => $type, 'direction' => $direction );
		} else {
			$this->_relationships[] = array( 'type' => $type );
		}

		$this->_traversalDescription['relationships'] = $this->_relationships;
	}

        /**
         * Getter for _returntype
         * @return string The returntype
         */
        public function getReturnType()
        {
            return $this->_returntype;
        }

        /**
         * Setter for _returntype
         * @param string $returntype The returntype to set
         */
        public function setReturnType($returntype)
        {
            $this->_returntype=$returntype;
        }

        /**
         * Setter for for the startnode
         * @param string $id The id of the startnode
         */
        public function setStartNode($id)
        {
                $this->_startnode=$id;
        }

        /**
         * Getter for the startnode
         * @return string the id of the startnode
         */
        public function getStartNode()
        {
                return $this->_startnode;
        }

        /**
         * Set a uniqueness to your description
         * @param string $uniqueness
         */
        public function setUniqueness($uniqueness)
        {
                $this->_uniqueness = $uniqueness;
		$this->_traversalDescription['uniqueness'] = $this->_uniqueness;
        }

        /**
         * Set a traversal order to your description
         * @param string $order
         */
        public function setTraversalOrder($order)
        {
                $this->_order = $order;
		$this->_traversalDescription['order'] = $this->_order;
        }

        /**
         * Set a prune evaluator to your description
         * @param string $language The language used ('builtin' or 'javascript')
         * @param string $body The body of the evaluator
         */
	public function setPruneEvaluator($language, $body) {
		$this->_pruneEvaluator['language'] = $language;
		$this->_pruneEvaluator['body'] = $body;
		$this->_traversalDescription['prune evaluator'] = $this->_pruneEvaluator;
	}

        /**
         * Set a returnfilter to your description
         * @param string $language The language used ('builtin' or 'javascript')
         * @param string $body The body of the filter
         */
	public function setReturnFilter($language, $body) {
		$this->_returnFilter['language'] = $language;
		$this->_returnFilter['body'] = $body;
		$this->_traversalDescription['return filter'] = $this->_returnFilter;
	}

        /**
         * Add a node property filter to your description e.g.: addNodePropertyFilter('modelclass','Person')
         * @param string $key The key field
         * @param string $value The value to match
         */
        public function addNodePropertyFilter($key,$value)
        {
                $body="position.endNode().getProperty('".$key."',0)=='".$value."'";
                $this->setReturnFilter('javascript', $body);
        }

        /**
         * Set the maximum traversal depth to your description. This usually defaults to 1
         * @param int $depth The traversal depth
         */
	public function setMaxDepth($depth) {
		$this->_maxDepth = $depth;
		$this->_traversalDescription['max depth'] = $this->_maxDepth;
	}

        /**
         * Builds an array out of the traversal description
         * @return <type>
         */
        public function toArray()
        {
                return $this->_traversalDescription;
        }

}
?>