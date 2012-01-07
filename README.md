#INSTALL:

##STEPS

1. Download my EActiveResource extension (https://github.com/Haensel/ActiveResource)
and add it to your extensions folder.

2. Download the Neo4Yii extension, import EActiveResource and Neo4Yii and configure Neo4Yii this way:
		
		  	'import'=>array(
		  		/* ..import stuff... */
		  		'application.extensions.EActiveResource.*',
                'application.extensions.Neo4Yii.*',
        	)

          	'neo4j'=>array(
                    'class'=>'ENeo4jGraphService',
                    'host'=>'192.168.2.10',
                    'port'=>'7474',
                    'db'=>'db/data',
                    'queryCacheID'=>'cache',
                ),

##Usage

Example:
Persons have friends which themselves can also have friends (via a friend relationship).
Each friendship can be defined with the property "forYears". e.g.:Old friends know each other
for more than 5 years. Here is an example of how to use Neo4Yii in such a case.

~~~
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
            'friends'=>array(self::HAS_MANY,self::NODE,'out("_FRIEND_")'),
            'fof'=>array(self::HAS_MANY,self::NODE,'out("_FRIEND_").out("_FRIEND_")'),
            'oldFriends'=>array(self::HAS_MANY,self::NODE,'outE("_FRIEND_").filter{it.forYears>5}.inV')
        );
    }
}
~~~

~~~
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
                
            /* OUTPUT
            Haensels friends:
			Bill Brown
			Susan Scissors
			Haensels old friends:
			Bill Brown
			friends of Haensels friends:
			Susans Friend
            */
~~~