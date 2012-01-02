#INSTALL:

##STEPS

1. Download the EActiveResource extension and add it to your project (only the import part is needed)

2. Download the Neo4Yii extension and configure it this way:
		
		  	'import'=>array(
				'ext.Neo4Yii.*',
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

3. Usage

Example: We want to have nodes that are Persons which are related with relationships of type
_FRIEND_. Some of these friends are old friends (friends >5 years) and some are friends of friends
Here is how you would do that

~~~
[php]
class Person extends ENeo4jNode
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }
    
    public function properties()
    {
        return CMap::mergeArray(parent::properties(),array(
            'name'=>array('type'=>'string'),
            'surname'=>array('type'=>'string'),
            'age'=>array('type'=>'integer'),
            'gender'=>array('type'=>'string'),
        ));
    }
    
    public function rules()
    {
        return array(
            array('name,surname,age,gender','safe'),
            array('age','numerical','integerOnly'=>true),
            array('name','required')
        );
    }
    
    public function traversals()
    {
        return array(
            'friends'=>array(self::HAS_MANY,self::NODE,'out("friend")'),
            'fof'=>array(self::HAS_MANY,self::NODE,'out("friend").out("friend")')
        );
    }
}
~~~

~~~
[php]
class _FRIEND_ extends ENeo4jRelationship
{
    public static function model($className = __CLASS__) {
        return parent::model($className);
    }
    
    public function properties()
    {
        return CMap::mergeArray(parent::properties(),array(
            'forYears'=>array('type'=>'integer'),
        ));
    }
    
    public function rules()
    {
        return array(
            array('forYears','safe'),
            array('forYears','numerical'),
        );
    }
}
~~~

~~~
[php]
			$haensel=new Person;
            
            $haensel->attributes=array(
                'name'=>'Johannes',
                'surname'=>'Bauer',
                'age'=>29,
                'gender'=>'m'
            );
            $haensel->save();
                        
            $bill=new Person;
            $bill->attributes=array(
                'name'=>'Bill',
                'surname'=>'Brown',
                'age'=>26,
                'gender'=>'m'
            );
            $bill->save();
            
            $haensel->addRelationshipTo($bill, '_FRIEND_',array('forYears'=>10));
            
            $susan=new Person;
            $susan->attributes=array(
                'name'=>'Susan',
                'surname'=>'Scissors',
                'age'=>31,
                'gender'=>'f'
            );
            $susan->save();
            
            $haensel->addRelationshipTo($susan, '_FRIEND_',array('forYears'=>4));
            
            $susansFriend=new Person;
            $susansFriend->attributes=array(
                'name'=>'Susans',
                'surname'=>'Friend',
                'age'=>40,
                'gender'=>'m'
            );
            $susansFriend->save();
            
            $susan->addRelationshipTo($susansFriend, '_FRIEND_',array('forYears'=>4));
            
            echo 'Haensels friends:<br>';
            foreach($haensel->friends as $friend)
                echo $friend->name .' '.$friend->surname.'<br>';
            
            echo 'Haensels old friends:<br>';
            foreach($haensel->oldFriends as $oldFriend)
                echo $oldFriend->name .' '.$oldFriend->surname.'<br>';
            
            echo 'friends of Haensels friends:<br>';
            foreach($haensel->fof as $fof)
                echo $fof->name .' '.$fof->surname.'<br>';
                
            /*
            Haensels friends:
			Bill Brown
			Susan Scissors
			Haensels old friends:
			Bill Brown
			friends of Haensels friends:
			Susans Friend
            */
~~