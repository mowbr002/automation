<?php 

namespace Automater;

require_once 'consts.php';
require_once 'cli_colors.php';
require_once 'phpseclib/Net/SSH2.php';
require_once 'phpseclib/Crypt/RSA.php';
require_once 'BaseObject.php';
require_once 'Model.php';
require_once 'View.php';
require_once 'Ajax.php';
require_once 'Url.php';

date_default_timezone_set("America/Los_Angeles");

define('NET_SSH2_LOGGING', NET_SSH2_LOG_COMPLEX);

	class Controller extends BaseObject{
		private $curr_view					= null;
		private $model						= null;
		private $view						= null;
		private $ajax						= null;
		private $url						= null;
		private $_site_list					= null;
		private $_remote_db_user			= null;
		private $_remote_db_pass			= null;
		private $_backup_path				= 'backups/';
		private $_remote_host_to_backup 	= null;
		private $_remote_db_name			= null;
		private $_local_db_backup_tmp_dir	= '';
		private $_remote_server_user_name	= null;
		private $available_sites			= null;
		private $available_users			= null;
		private $available_dbase			= null;
		private $server_connection_str		= "";
		private $cms_version_list			= null;
		private $ssh_connection				= null;
		public $remote_connect_str = "";
		
		private $server_list				= null;
		private $cms_list					= null;
		private $cms_versions				= null;
		private $vtm_admins					= null;
		
		/**
		 * Construct the object
		 */
		public function __construct(){
			$this->model	= new Model();
			$this->view		= new View();
			$this->url		= new Url();
			
			
			$request = $this->url->parseRequest();
			
			if(isset($_POST['ajax']) || isset($_GET['ajax'])){
				/**
				 * Ajax call, short circuit out of the
				 * view logic and GET/POST info for
				 * the ajax request
				 */
				
				$request_method = $_SERVER['REQUEST_METHOD'];
				
				//echo $request_method;
				
				$this->ajax = new Ajax($this->model);
				$this->ajax->handleRequest($request_method);
				
				exit();
			}
			
			/**
			 * fetch server list from database
			 */
			$this->available_sites = $this->model->getServerList();
			$this->available_users = $this->model->getUserList();
			$this->available_dbase = $this->model->getDBList();
			$this->cms_version_list = $this->model->getCMSVList();
			$this->server_list		= $this->model->getServerSelection();
			$this->cms_list			= $this->model->getCmsList();
			$this->cms_versions		= $this->model->getCmsVersionById();
			$this->vtm_admins		= $this->model->getVtmAdmins();
			
			$this->view->setData(array(
					'sites'	=> \json_encode($this->available_sites),
					'users'	=> \json_encode($this->available_users),
					'db_ms'	=> \json_encode($this->available_dbase),
					'servs'	=> \json_encode($this->server_list),
					'cmsys'	=> \json_encode($this->cms_list),
					'vtmad'	=> \json_encode($this->vtm_admins),
					'cmsvi'	=> \json_encode($this->cms_versions),
					'cmsvs'	=> $this->cms_version_list,
					'updts'	=> $this->model->getSiteUpdateStats(),
			));
			
			$logged_in = $this->model->checkCookie();
			
			if(isset($_POST['username_login'])){
				$this->model->setUserCookie();
			}
			
			if($logged_in == 'display_login'){
				$this->curr_view = 'login';
				$data_array = array(
						'action'	=> 'index.php',
						'method'	=> 'post',
				);
				
				$this->view->setData($data_array);
			}elseif($logged_in == "display_base"){
				/**
				 * Check if there was a specific
				 * page requested, return it if so,
				 * otherwise just return the base page
				 */
				if(isset($request)){
					$this->curr_view = $request;
					if($request == "feed"){
						$data_array = array(
								'sites'	=> $this->available_sites,
								'users'	=> $this->available_users,
								'db_ms'	=> $this->available_dbase,
								'cmsvs'	=> \json_decode($this->cms_version_list),
								'updts'	=> \json_decode($this->model->getSiteUpdateStats()),
						);
						$this->view->setData(\json_encode($data_array));
					}
				}else{
					$this->curr_view = 'base';
				}
			}
		}
		
		public function getDisplay(){
			return $this->view->renderView($this->curr_view);
		}
		
		public function getPluginsFromTxt($site){
			return self::parsePluginsFile($site);
		}
		
		public function updateStagingToLatest($site){
			$this->model->setStagingLatestDo($site); //self::setStagingLatestDo($site);
		}
		
		public function getPlugins($site){
			return $this->model->selectSitePlugins($site); //self::selectSitePlugins($site);
		}
		
		public function gatherUpdateStats(){
			return $this->model->getSiteUpdateStats(); // self::getSiteUpdateStats();
		}
		
		public function setProdUpdated($date,$site){
			$this->model->updateProdUpdated($date, $site); //self::updateProdUpdated($date,$site);
		}
		
		public function setStagingUpdated($date,$site){
			$this->model->updateStagingUpdated($date, $site); //self::updateStagingUpdated($date,$site);
		}
		
		public function toggleQueued($site){
			$this->model->setQueuedToggle($site); //self::setQueuedToggle($site);
		}
		
		public function gatherSiteInfo($site){
			return $this->model->siteLookup($site); //self::siteLookup($site);
		}
		
		public function gatherSiteNotes($site,$note_id){
			return $this->model->getSiteNotes($site, $note_id); //self::getSiteNotes($site,$note_id);
		}
		
		public function addSiteNote($site,$note,$usr){
			return $this->model->appendSiteNote($site, $note, $usr); //self::appendSiteNote($site,$note,$usr);
		}
		
		public function dbCopy($db_nmbr){
			self::copyDbToStaging($db_nmbr);
		}
		
		public function getProdStagingDb($db_nmbr,$site){
			return $this->model->getProductionDbList($db_nmbr, $site); //self::getProductionDbList($db_nmbr,$site);
		}
		
		public function dbBacupStageDo($db_name,$bk_user){
			$db_bacup_status = self::stageDbBackup($db_name,$bk_user);
			
			return $db_bacup_status;
		}
		
		public function cmsBackupDo($site,$mode){
			$db_data = self::dbSiteLookup($site);
			
			$serv_ad = $db_data[0]['ip_addr'];
			
			self::connectSsh($serv_ad);
			$cms_backup_status = self::getBackupFiles($site,$mode);
			
			return $cms_backup_status;
		}
		
		public function touchServerFile(){
			self::serverSsh();
		}
		
		public function getAjaxConnectionStr($dbase = ''){
			$qry_p = array($dbase);
			
			$sql =<<<'SQL'
    			SELECT
    				id,
					is_def,
					name,
					serv_id,
					site,
					serv_name,
					ip_addr
    			FROM
    				ajax_str
    			WHERE
    				id = ?
SQL;
			$sth = $this->dbh->prepare($sql);
			
			try{
				$sth->execute($qry_p);
			}catch(\PDOException $e){
				error_log($e->getMessage() . ' on '.__LINE__.' in '.__CLASS__);
			}
			
			$data = $sth->fetchAll(\PDO::FETCH_ASSOC);
			$data[0]['datetime'] = $datetime = date("m_d_Y_his");
			
			return $data[0];
		}
		
		public function getSiteParams($site){
			$qry_p = array($site);
			
			$sql =<<<'SQL'
     			SELECT
     				id,
     				server,
     				cms,
     				cms_version,
     				uri,
     				staging_uri,
     				staging_cms_version,
     				git_repo,
     				system_user,
     				status,
     				vtm_admin,
     				dev_notes,
     				dev_location,
					to_char(last_update_prod, 'MM/DD/YYYY') as prod_updated,
					to_char(last_update_staging, 'MM/DD/YYYY') as stage_updated,
					update_queued
     			FROM
     				vtm_sites
     			WHERE
     				company = ?
SQL;
			
			$sth = $this->dbh->prepare($sql);
			try{
				$sth->execute($qry_p);
			}catch(\PDOException $e){
				error_log($e->getMessage() . ' on ' . __LINE__ . ' in ' . __CLASS__);
			}
			
			$data = $sth->fetchAll(\PDO::FETCH_ASSOC);
			
			return $data[0];
		}
		
		public function initializeDataFields($remote_site_nm,$remote_db_name,$remote_db_user,$remote_db_pass,$_remote_server_user_name,$local_backup_path){
			self::initDataFlds(
					$remote_site_nm,
					$remote_db_name,
					$remote_db_user,
					$remote_db_pass,
					$_remote_server_user_name,
					$local_backup_path
					);
		}
		
		public function initSingleField($field = null,$data = null){
			$this->$field = $data;
		}
		
		public function printServerList(){
			print_r($this->available_sites);
		}
		
		public function fetchBackupSite($field = NULL){
			$val = null;
			
			isset($field) ? $val = $this->available_sites[$this->_remote_host_to_backup][0][$field]
			:
			$val = $this->available_sites[$this->_remote_host_to_backup][0];
			
			return $val;
		}
		
		public function getSiteSelectList(){
			
			
			$list = array();
			foreach ($this->available_sites as $key => $val){
				$list[$key] = $key;
			}
			
			$list_json = \json_encode($this->available_sites);
			
			return array($list,$list_json);
		}
		
		public function getSystemUsers(){
			$list = array();
			
			//print_r($this->available_users);
			
			foreach ($this->available_users as $key => $val){
				$list[$key] = array(
						'username'	=> $val[0]['username'],
						'is_def'	=> $val[0]['is_def']
				);
			}
			
			return $list;
		}
		
		public function getSystemDbases(){
			return $this->available_dbase;
			
		}
		
		public function fetchSystemDbasesJson(){
			return json_encode($this->available_dbase);
		}
		
		public function fetchCMSVList(){
			return $this->cms_version_list;
		}
		
		public function getSiteUpdateLog($site,$record = null,$dbase = null){
			$qry_p = array($site);
			
			$sql =<<<'SQL'
    			SELECT
    				staged_on,
    				last_run,
    				username,
    				server,
					db_name
    			FROM
    				bklogs
				WHERE
					company = ?
					%s
				LIMIT
					6
SQL;
			
			if(isset($dbase)){
				array_push($qry_p, $dbase);
				
				$sql = sprintf($sql,
						"AND db = ? "
						);
			}elseif(isset($record)){
				array_push($qry_p, $record);
				
				$sql = sprintf($sql,"AND id = ? ");
			}else{
				$sql = sprintf($sql,"");
			}
			
			$sth = $this->dbh->prepare($sql);
			
			try{
				$sth->execute($qry_p);
			}catch(\PDOException $e){
				\error_log($e->getMessage() . ' on ' . $e->getLine() . ' in ' . __CLASS__);
			}
			
			return $sth->fetchAll(\PDO::FETCH_ASSOC);
		}
		
		
		/**
		 * Private functions below
		 */
		
		private function parsePluginsFile($site){
			$file = "plugins.txt";
			$return_array = array();
			
			foreach(file($file) as $line => $text){
				
				$line_splode = \explode("|", $site . $text);
				
				$insert = self::insertPlugin(array_slice($line_splode, 0, 5));
				
				\array_push($return_array, array($line_splode[1] . ' -> ' . $insert));
			}
			
			return $return_array;
		}
		
		private function insertPlugin(array $plugin){
			$ins_ext = 'insert';
			
			$p_qry_p = array(
					trim($plugin[1]),
					trim($plugin[2]),
					trim($plugin[3]),
					trim($plugin[4]),
					trim($plugin[1])
			);
			
			$sql =<<<'SQL'
        		INSERT INTO
					plugins(plugin_name , active_txt, update, version)
					SELECT
						?,
						?,
						?,
						?
					WHERE NOT EXISTS(
						SELECT
							1
						FROM
							plugins
						WHERE
							plugin_name = ?
					)
				RETURNING
					pid
SQL;
			
			try{
				$sth = $this->dbh->prepare($sql);
				$sth->execute($p_qry_p);
			}catch(\PDOException $e){
				\error_log($e->getMessage());
			}
			
			$l_qry_p = array($plugin[0]);
			
			$is_active = "";
			$plugin_id = "";
			
			if(trim($plugin[2]) == "active"){
				$is_active = "TRUE";
			}elseif(trim($plugin[2]) == "inactive"){
				$is_active = "FALSE";
			}
			
			if($sth->rowCount() > 0){
				$plugin_id = $sth->fetchColumn(0);
				\array_push($l_qry_p, $plugin_id,$is_active, trim($plugin[3]), trim($plugin[0]), $plugin_id);
			}else{
				//Plugin exists, get its id
				$ins_ext = "extant";
				$i_qry_p = array(trim($plugin[1]));
				
				try{
					$sth = $this->dbh->prepare("SELECT pid FROM plugins where plugin_name = ?");
					$sth->execute($i_qry_p);
				}catch(\PDOException $e){
					\error_log($e->getMessage());
				}
				
				$plugin_id = $sth->fetchColumn(0);
				array_push($l_qry_p, $plugin_id,$is_active, trim($plugin[3]), trim($plugin[0]), $plugin_id);
			}
			
			
			
			$l_sql =<<<'LSQL'
     			INSERT INTO
     				plugin_to_site(sid,pid,active,update_needed)
				SELECT
					?, ?, ?, ?
				WHERE
					NOT EXISTS(
						SELECT
							1
						FROM
							plugin_to_site
						WHERE
							sid = ?
						AND
							pid = ?
					)
				RETURNING
					pts
LSQL;
			try{
				$sth = $this->dbh->prepare($l_sql);
				$sth->execute($l_qry_p);
			}catch(\PDOException $e){
				error_log($e->getMessage());
			}
			
			if($sth->rowCount() > 0){
				
			}else{
				$u_qry_p = array($is_active, trim($plugin[3]), trim($plugin[0]), $plugin_id);
				
				$u_sql =<<<'USQL'
        				UPDATE
        					plugin_to_site
        				SET
        					active = ?,
							update_needed = ?
        				WHERE
        					sid = ?
						AND
							pid = ?
USQL;
				
				try{
					$sth = $this->dbh->prepare($u_sql);
					$sth->execute($u_qry_p);
				}catch(\PDOException $e){
					\error_log($e->getMessage());
				}
			}
			
			return $ins_ext;
		}
		
		private function setStagingLatestDo($site){
			$qry_p = array($site);
			
			$sql =<<<'SQL'
    			UPDATE
    				site_status
    			SET
    				staging_version = 'latest'
				WHERE
					id = ?
SQL;
			
			try{
				$sth = $this->dbh->prepare($sql);
				$sth->execute($qry_p);
				return true;
			}catch(\PDOException $e){
				\error_log($e->getMessage());
			}
		}
		
		
		
		
		
		
		
		
		
		private function getSiteUpdateStats(){
			$sql =<<<'SQL'
        			SELECT
						sid,
        				company,
						staging_needs_update,
						needs_update,
						server,
						active
        			FROM
        				update_stats
SQL;
			try{
				$sth = $this->dbh->prepare($sql);
				$sth->execute();
			}catch(\PDOException $e){
				\error_log($e->getMessage());
			}
			
			return \json_encode($sth->fetchAll(\PDO::FETCH_ASSOC));
		}
		
		
		
		
		
		
		
		private function buildServerConnectionStr(){
			$datetime = date("m_d_Y_his");
			
			$cmd_ssh_base = 'ssh '.$this->_remote_server_user_name .'@'.$this->available_sites[$this->_remote_host_to_backup][0]['ip_addr'].' ';
			$cmd_sql_user = 'mysqldump -u '.$this->_remote_db_user.' ';
			$cmd_sql_pass = "-p'" .  $this->_remote_db_pass  ."' ";
			$cmd_sql_dbnm = $this->_remote_db_name.' ';
			$cmd_sql_cmpr = '| gzip -c ';
			$cmd_sql_dest = '> '.$this->_local_db_backup_tmp_dir.'/'.$this->_remote_db_name.'_'.$datetime.'.sql.gz';
			
			$this->remote_connect_str = $cmd_ssh_base . $cmd_sql_user . $cmd_sql_pass . $cmd_sql_dbnm . $cmd_sql_cmpr . $cmd_sql_dest;
		}
		
		private function initDataFlds(
				$remote_site_nm,
				$remote_db_name,
				$remote_db_user,
				$remote_db_pass,
				$_remote_server_user_name,
				$local_backup_path)
		{
			$this->_remote_db_user = $remote_db_user;
			$this->_remote_db_pass = $remote_db_pass;
			$this->_remote_host_to_backup = $remote_site_nm;
			$this->_remote_db_name = $remote_db_name;
			$this->_remote_server_user_name = $_remote_server_user_name;
			$this->_local_db_backup_tmp_dir = $local_backup_path;
			
			self::buildServerConnectionStr();
		}
		
		private function getCMSVList(){
			$sql =<<<'SQL'
        	SELECT
        		cms.name,
        		cmsv.version_name,
				cmsv.active
        	FROM
        		cms
        	JOIN
        		cms_version cmsv
        	ON
        		cmsv.cms = cms.id
        	ORDER BY
        		cmsv.ordinal desc
SQL;
			$sth = $this->dbh->prepare($sql);
			try{
				$sth->execute();
			}catch(\PDOException $e){
				error_log($e->getMessage() . ' on ' . __LINE__ . ' in ' . __CLASS__);
			}
			
			//return
			
			$this->cms_version_list = json_encode($sth->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_ASSOC));
		}
		
		private function getServerList(){
			$sql =<<<'SQL'
                SELECT
					company,
					lc_company,
                    id,
                    server,
                    cms,
                    cms_version,
                    uri,
                    staging_uri,
                    staging_cms_version,
                    git_repo,
                    system_user,
                    status,
                    vtm_admin,
                    addr,
					db,
					is_def,
					active,
					last_update_prod,
					last_update_staging,
					update_queued,
					site_admin_name
                FROM
                    server_list
SQL;
			
			$sth = $this->dbh->prepare($sql);
			try{
				$sth->execute();
			}catch(\PDOException $e){
				\error_log($e->getMessage() . ' on ' . __LINE__ . ' in ' . __FILE__);
			}
			
			$this->available_sites = $sth->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_ASSOC);
		}
		
		
		
		private function getUserList(){
			$sql =<<<'SQL'
    			SELECT
    				id,
    				username,
					is_def
    			FROM
    				system_users
    			ORDER BY
    				username
SQL;
			$sth = $this->dbh->prepare($sql);
			
			try{
				$sth->execute();
			}catch(\PDOException $e){
				error_log($e->getMessage() . ' on ' . __LINE__ . ' in ' . __CLASS__);
			}
			
			$this->available_users = $sth->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_ASSOC);
			
		}
		
		private function getDBList(){
			$sql =<<<'SQL'
				SELECT
					company,
					id,
					server_id,
					server,
					is_def,
					name,
					site,
					rds
				FROM
					db_list
SQL;
			
			
			$sth = $this->dbh->prepare($sql);
			
			try{
				$sth->execute();
			}catch (\PDOException $e){
				\error_log($e->getMessage() . ' on '. $e->getLine() . ' in '. __CLASS__);
			}
			
			$this->available_dbase = $sth->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_ASSOC);
			
		}
		
		private function connectSsh($ip_addr){
			$this->ssh_connection = new \Net_SSH2($ip_addr);
			
			$key = new \Crypt_RSA();
			$key->loadKey('');
			$this->ssh_connection->setTimeout(100000);
			
			if(!$this->ssh_connection->login('jmowbray',$key)){
				
				echo $this->ssh_connection->getLog();
				exit('SSH login failed...');
			}
		}
		
		private function serverSsh($ip_addr,$db_string,$db_dir_name){
			$this->ssh_connection->exec("mkdir /var/tmp/sql_backup_files/$db_dir_name");
			
			$this->ssh_connection->setTimeout(2);
			
			$this->ssh_connection->exec($db_string);
		}
		
		private function getBackupFiles($site,$mode){
			$date = date("m_d_Y_his_");
			
			if(!isset($site)){
				
				try{
					throw new \Exception("No site specified");
				}catch(Exception $e){
					error_log($e->getMessage());
					die("died");
				}
				
				$site = 'vtmgroup.com';
			}
			
			$qry_p = array($site);
			
			$sql =<<<'SQL'
    			SELECT
    				prod_file_location,
    				stage_file_location,
					vhost_root
    			FROM
    				vtm_sites site
    			WHERE
    				id = ?
SQL;
			$sth = $this->dbh->prepare($sql);
			
			try{
				$sth->execute($qry_p);
			}catch(\PDOException $e){
				\error_log($e->getMessage());
			}
			
			$info = $sth->fetchAll(\PDO::FETCH_ASSOC);
			
			$staging = $info[0]['stage_file_location'];
			$production = $info[0]['prod_file_location'];
			$vhost_root = $info[0]['vhost_root'];
			$disposition = "/staging/";
			
			$backup_string = "";
			
			if($mode == "production"){
				$disposition = "/production/";
				$prod_str = BUDIR::ROOT_DIR.$vhost_root.'/production/'.$date.$vhost_root;
				$production_tar = "tar --ignore-failed-read -zcf $prod_str.tar.gz $production";
				
				$backup_string = $production_tar;
				
				\error_log($backup_string);
			}elseif($mode == "staging"){
				$staging_str = BUDIR::ROOT_DIR.$vhost_root.'/staging/'.$date.$vhost_root;
				$staging_tar = "tar --ignore-failed-read -zcf $staging_str.tar.gz $staging";
				
				$backup_string = $staging_tar;
				
				\error_log($backup_string);
			}
			
			
			try{
				$this->ssh_connection->exec("mkdir /var/tmp/cms_backup_files/".$vhost_root.$disposition);
			}catch(\Exception $e){
				\error_log($e->getMessage());
			}
			
			$this->ssh_connection->setTimeout(2);
			
			try{
				//$this->ssh->exec("mkdir /var/tmp/cms_backup_files/$site");
				$this->ssh_connection->exec($backup_string);
			}catch(\Exception $e){
				\error_log($e->getMessage());
			}
			
		}
		
		private function dbSiteLookup($site_id){
			//\error_log($site_id);
			$qry_p = array($site_id);
			
			$sql =<<<'SQL'
    			SELECT
    				dbs.name,
					serv.id,
					serv.ip_addr,
					serv.db_username,
					dbs.site
    			FROM
					vtm_sites site
				JOIN
    				dbs
				ON
					dbs.site = site.id
				JOIN
					server serv
				ON
					serv.id = site.server
    			WHERE
    				site.id = ?
SQL;
			$sth = $this->dbh->prepare($sql);
			
			try{
				$sth->execute($qry_p);
			}catch(\PDOException $e){
				\error_log($e->getMessage() . ' on ' . __LINE__ . ' in ' . __CLASS__);
			}
			
			$db_data = $sth->fetchAll(\PDO::FETCH_ASSOC);
			
			return $db_data;
		}
		
		
		
		private function dbNameLookup($db_id){
			$qry_p = array($db_id);
			
			$sql =<<<'SQL'
    			SELECT
    				dbs.name,
					serv.id,
					host(ipa.addr) as ip_addr,
					serv.db_username,
					dbs.site,
					dbs.rds,
					host.host
    			FROM
    				dbs
				JOIN
					server serv
				ON
					serv.id = dbs.server
				JOIN
					ip_to_server its
				ON
					its.sid = serv.id
				JOIN
					ip_addrs ipa
				ON
					ipa.id = its.ipid
				JOIN
					db_host host on host.id = dbs.db_host
    			WHERE
    				dbs.id = ?
SQL;
			$sth = $this->dbh->prepare($sql);
			
			try{
				$sth->execute($qry_p);
			}catch(\PDOException $e){
				\error_log($e->getMessage() . ' on ' . __LINE__ . ' in ' . __CLASS__);
			}
			
			$db_data = $sth->fetchAll(\PDO::FETCH_ASSOC);
			
			return $db_data;
		}
		
		private function copyDbToStaging($db_nmbr){
			$db_data = self::dbNameLookup($db_nmbr);
			
			$db_user = $db_data[0]['db_username'];
			$db_name = $db_data[0]['name'];
			$db_dest = $db_name . '_copy';
			
			$copy_str =<<<'STR'
mysqldbcopy --not-null-blobs --source=%s:%s@localhost --destination=%s:%s@localhost %s:%s
STR;
			$copy_str = sprintf($copy_str,
					$db_user,
					$this->db_pass_array[1],
					$db_user,
					$this->db_pass_array[1],
					$db_name,
					$db_dest
					);
			
			error_log($copy_str);
		}
		
		private function stageDbBackup($db_nmbr,$bk_user){
			$status = false;
			
			//$db_user = 'root';
			$db_data = self::dbNameLookup($db_nmbr);
			
			/* $db_pass_array = array(
			 1	=>	'1EpJ5tGJkrRvdEFd6Q1I'
			 ); */
			
			$db_host = "";
			$is_rdbs = $db_data[0]['rds'];
			
			if(isset($is_rdbs)){
				$db_host = " --host " . $db_data[0]['host'] . " --databases ";
			}
			
			
			
			
			$db_bdat = date("m_d_Y_his");
			$db_name = $db_data[0]['name'];
			$db_bdir = "/var/tmp/sql_backup_files/$db_name";
			$serv_ad = $db_data[0]['ip_addr'];
			$serv_id = $db_data[0]['id'];
			$db_user = $db_data[0]['db_username'];
			$db_site = $db_data[0]['site'];
			
			$db_pass = $this->db_pass_array[$serv_id];	//$db_pass_array[$serv_id];
			
			$backup_str =<<<'STR'
        		mysqldump -u %s -p%s %s %s | gzip -c > %s/%s_%s.sql.gz
STR;
			
			$backup_str = trim(sprintf($backup_str,
					$db_user,
					$db_pass,
					$db_host,
					$db_name,
					$db_bdir,
					$db_bdat,
					$db_name
					));
			
			$qry_p = array($serv_id,$db_site,$db_nmbr,$backup_str,$bk_user);
			
			$sql =<<<'SQL'
        		INSERT INTO
        			backup_log(uid,plesk_server,site_id,db,backup_string)
        		SELECT
					id,
					?,
					?,
					?,
					?
				FROM
					system_users
				WHERE
					username = ?
				RETURNING
					id
SQL;
			$sth = $this->dbh->prepare($sql);
			
			try{
				$sth->execute($qry_p);
				$status = TRUE;
			}catch(\PDOException $e){
				error_log($e->getMessage() . ' on ' . $e->getLine() . ' in ' . __CLASS__);
			}
			
			$last_insert = $sth->fetchColumn(0);
			
			$updt_qry_p = array($last_insert);
			
			$status = $last_insert;
			
			exit();
			
			die();
			self::connectSsh($serv_ad);
			self::serverSsh($serv_ad, $backup_str, $db_name);
			
			
			$updt_sql =<<<'UPDT'
     			UPDATE
     				backup_log
     			SET
     				last_run = now()
     			WHERE
     				id = ?
UPDT;
			$sth = $this->dbh->prepare($updt_sql);
			try{
				$sth->execute($updt_qry_p);
			}catch(\PDOException $e){
				error_log($e->getMessage() . ' on ' . $e->getLine() . ' in ' . __CLASS__);
			}
			
			$return_array = array(
					'status'	=> $status,
					'site'		=> $db_site
			);
			
			return $return_array;
		}
	}

?>
