#INSTALL:

##STEPS

###1. Download the EActiveResource extension and add it to your project

###2. Download the ENeo4jSuite extension and add it to your project like that
		
		  	'import'=>array(
				'application.lib.Neo4jSuite.*',
        	)

          	'neo4j'=>array(
                    'class'=>'ext.ENeo4jSuite.ENeo4jGraphService',
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
                
###3. Caching
You are now able to use caching. The syntax is basically the same as with CActiveRecord despite
the lack of CDbCacheDependency support:

		//cache the request response for 20 seconds
		MyNodeModel::model()->cache(20)->findById(1);
		//cache the request response of findById() request and 10 following requests for 20 seconds
		MyNodeModel::model()->cache(20,null,10)->findById(1);
		//cache the response for 20 seconds and invalidate using a dependency 
		MyNodeModel::model()->cache(20,$someYiiCacheDependencyObject)->findById(1);