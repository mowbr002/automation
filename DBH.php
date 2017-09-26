<?php 

namespace Automater;

include_once 'consts.php';
include_once 'BaseObject.php';

class DBH extends BaseObject{
	private $dbh = null;
	
	public function __construct(){
		date_default_timezone_set('America/Los_Angeles');
		$db_name = 'automation';
		$db_host = 'chef-dev-server-postgres.c3d5rka3voul.us-west-2.rds.amazonaws.com';
		$db_user = '';
		$db_pass = '';
		
		try {
			$this->dbh = new \PDO("pgsql:dbname=$db_name;host=$db_host;",$db_user,$db_pass, array(
					\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
			));
		}catch(\PDOException $e){
            error_log($e->getMessage() . ' on ' . $e->getLine());
            echo 'db connection failed';
		}
	}
	
	public function getDbh(){
		return $this->dbh;
	}
}

?>
