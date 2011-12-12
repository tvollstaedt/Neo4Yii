#Changes:

##Version 0.1:

###1. Updated to EActiveResource 0.4 and Neo4j-1.6.M01 (to support path responses via gremlin)
In order to use this extension you now have to update to EActiveResource 0.4 too. Neo4j-1.5 should
work as long as you don't want to return full paths using a gremlin script

###2. Rewrite of the neo4j component
The graph service is now defined using the same syntax as EActiveResource 0.4. Here is an example:

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
                
###3. Caching
You are now able to use caching. The syntax is basically the same as with CActiveRecord despite
the lack of CDbCacheDependency support:

		//cache the request response for 20 seconds
		MyNodeModel::model()->cache(20)->findById(1);
		//cache the request response of findById() request and 10 following requests for 20 seconds
		MyNodeModel::model()->cache(20,null,10)->findById(1);
		//cache the response for 20 seconds and invalidate using a dependency 
		MyNodeModel::model()->cache(20,$someYiiCacheDependencyObject)->findById(1);