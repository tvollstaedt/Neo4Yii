#INSTALL:

##STEPS

###1. Download the EActiveResource extension and add it to your project

###2. Download the ENeo4jSuite extension and add it to your project like that
		
		  	'import'=>array(
				'ext.Neo4jSuite.*',
        	)

          	'neo4j'=>array(
                    'class'=>'ENeo4jGraphService',
                    'resources'=>array(
                        'ENeo4jGraphService'=>array(
                            'site'=>'192.168.2.10:7474/db/data',
                            'idProperty'=>'id',
                            'contenttype'=>'application/json',
                            'accepttype'=>'application/json',
                        ),
                    ),
                    'queryCacheID'=>'cache',
                ),

###3. Use the wiki to get some basic concepts