<?php 

namespace Automater;

require_once 'DBH.php';

	class Model extends BaseObject{
		private $dbh = null;
		
		public function __construct(){
			$db = new DBH();
			$this->dbh = $db->getDbh();
		}
		
		public function getServerList(){
			$sql =<<<'SQL'
                SELECT
					company,
					lc_company,
                    id,
                    server,
					server_id,
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
					site_admin_name,
					in_dev
                FROM
                    server_list
SQL;
			
			$sth = $this->dbh->prepare($sql);
			try{
				$sth->execute();
			}catch(\PDOException $e){
				\error_log($e->getMessage() . ' on ' . __LINE__ . ' in ' . __FILE__);
			}
			
			return $sth->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_ASSOC);
		}
		
		private function cmsLookup($site){
			$qry_p = array($site);
			
			$sql =<<<'SQL'
				SELECT
					cms.name
				FROM
					vtm_sites vtms
				JOIN
					cms
				ON
					cms.id = vtms.cms
				WHERE
					vtms.id = ?
SQL;
			try{
				$sth = $this->dbh->prepare($sql);
				$sth->execute($qry_p);
			}catch(\PDOException $e){
				error_log($e->getMessage() . ' on: ' . $e->getLine());
			}
			
			return $sth->fetchColumn(0);
		}
		
		public function getLiveSiteStats($server,$site){
			$thing = 'https://automaton-3.org.10-1-217-39.causewaynow.com/updates_protected/autobot.php?site=13&disposition=staging&generate=info&key=164f67614406f309d3166269648fd817&cms=drupal&action=get';
		}
		
		private function getServerId($site){
			$qry_p = array($site);
			
			$sql =<<<'SQL'
				SELECT
					server
				FROM
					vtm_sites
				WHERE
					id = ?
SQL;
			try{
				$sth = $this->dbh->prepare($sql);
				$sth->execute($qry_p);
			}catch(\PDOException $e){
				error_log($e->getMessage() . ' on: ' . $e->getLine());
			}
			
			return $sth->fetchColumn(0);
		}
		
		public function triggerZip($server,$site,$disp){
			$cms = self::cmsLookup($site);
			
			$ch = curl_init();
			
			curl_setopt_array($ch, array(
					CURLOPT_URL 			=> self::getServer($site),
					CURLOPT_RETURNTRANSFER 	=> true,
					CURLOPT_POST			=> true,
					CURLOPT_POSTFIELDS		=> array(
							'site'			=> $site,
							'cms'			=> $cms,
							'disposition'	=> $disp,
							'key'			=> '164f67614406f309d3166269648fd817',
							'action'		=> 'zip'
					),
					CURLOPT_SSL_VERIFYHOST	=> false,
					CURLOPT_SSL_VERIFYPEER	=> false,
			));
			
			$content = curl_exec($ch);
			
			curl_close($ch);
			
			return $content;
		}
		
		public function pingAutobot($server,$site,$disp){
			$cms = self::cmsLookup($site);
			
			$ch = curl_init();
			
			curl_setopt_array($ch, array(
					CURLOPT_URL 			=> self::getServer($site),
					CURLOPT_RETURNTRANSFER 	=> true,
					CURLOPT_POST			=> true,
					CURLOPT_POSTFIELDS		=> array(
							'site'			=> $site,
							'cms'			=> $cms,
							'disposition'	=> $disp,
							'key'			=> '164f67614406f309d3166269648fd817',
							'action'		=> 'get'
					),
					CURLOPT_SSL_VERIFYHOST	=> false,
					CURLOPT_SSL_VERIFYPEER	=> false,
			));
			
			if(curl_error($ch)){
				error_log($ch);
			}
			
			$content = curl_exec($ch);
			
			curl_close($ch);
			
			return $content;
		}
		
		public function dbRunBackup($server,$site,$disp){
			$cms = self::cmsLookup($site);
			
			$ch = curl_init();
			
			curl_setopt_array($ch, array(
					CURLOPT_URL 			=> self::getServer($site),
					CURLOPT_RETURNTRANSFER 	=> true,
					CURLOPT_POST			=> true,
					CURLOPT_POSTFIELDS		=> array(
							'site'			=> $site,
							'cms'			=> $cms,
							'disposition'	=> $disp,
							'key'			=> '164f67614406f309d3166269648fd817',
							'action'		=> 'dbb'
					),
					CURLOPT_SSL_VERIFYHOST	=> false,
					CURLOPT_SSL_VERIFYPEER	=> false,
			));
			
			if(curl_error($ch)){
				error_log($ch);
			}
			
			$content = curl_exec($ch);
			
			curl_close($ch);
			
			return $content;
		}
		
		public function checkCookie(){
			if(isset($_COOKIE['automaton_user'])){
				//self::getUserCookie();
				return 'display_base';
			}else{
				return 'display_login';
			}
		}
		
		public function insertNewSite(array $data){
			$qry_p = array(
					$data['name'],
					$data['host'],
					$data['cmss'],
					$data['sver'],
					$data['pver'],
					$data['pver'],
					$data['dblc'],
					$data['suri'],
					$data['puri'],
					$data['gitr'],
					$data['admn']
			);
			
			$placeholder = implode(",", array_fill(0, count($qry_p), "?"));
			
			$qry_db_s = array(
					$data['sdbn'],
					$data['host'],
					"staging"
			);
			
			$qry_db_p = array(
					$data['pdbn'],
					$data['host'],
					"production"
			);
			
			$sql =<<<'SQL'
				INSERT INTO
					%s(
						%s,
						%s,
						%s,
						%s,
						%s,
						%s,
						%s,
						%s,
						%s,
						%s,
						%s
					)
				VALUES
					(%s)
				RETURNING %s				
SQL;

			$sql = sprintf($sql,
						DATABASE::SITES,
						SITE_FIELDS::NAME,
						SITE_FIELDS::HOST,
						SITE_FIELDS::CMSS,
						SITE_FIELDS::SVER,
						SITE_FIELDS::PVER,
						SITE_FIELDS::PVVR,
						SITE_FIELDS::DBLC,
						SITE_FIELDS::SURI,
						SITE_FIELDS::PURI,
						SITE_FIELDS::GITR,
						SITE_FIELDS::ADMN,
						$placeholder,
						SITE_FIELDS::SIID
					);
			
			try{
				$sth = $this->dbh->prepare($sql);
				$sth->execute($qry_p);
			}catch(\PDOException $e){
				\error_log($e->getMessage() . ' on: ' . $e->getLine());
			}
			
			if($sth->rowCount() <= 0){
				throw new \Exception('Site was not properly inserted! Cannot continue');
			}
			
			$site_id = $sth->fetchColumn(0);
			
			\array_push($qry_db_s, $site_id);
			\array_push($qry_db_p, $site_id);
			
			$s_db_sql =<<<'SDB_SQL'
				INSERT INTO
					%s(
							%s,
							%s,
							%s,
							%s
					)
				VALUES
					(?,?,?,?)
SDB_SQL;

			$s_db_sql = sprintf($s_db_sql,
						DATABASE::DATAB,
						SITE_FIELDS::SDBN,
						SITE_FIELDS::HOST,
						SITE_FIELDS::DISP,
						SITE_FIELDS::SITE
					);
			
			try{
				$sth = $this->dbh->prepare($s_db_sql);
				$sth->execute($qry_db_s);
			}catch(\PDOException $e){
				error_log($e->getMessage() . ' on: ' . $e->getLine());
			}

			$p_db_sql =<<<'PDB_SQL'
				INSERT INTO
					%s(
							%s,
							%s,
							%s,
							%s
					)
				VALUES
					(?,?,?,?)
PDB_SQL;

			$p_db_sql = sprintf($p_db_sql,
						DATABASE::DATAB,
						SITE_FIELDS::PDBN,
						SITE_FIELDS::HOST,
						SITE_FIELDS::DISP,
						SITE_FIELDS::SITE
					);
			
			try{
				$sth = $this->dbh->prepare($p_db_sql);
				$sth->execute($qry_db_p);
			}catch(\PDOException $e){
				error_log($e->getMessage() . ' on: ' . $e->getLine());
			}
			
			return $site_id;
		}
		
		public function getCmsVersionById(){
			$sql =<<<'SQL'
				SELECT
					cms,
					cms,
					id, 
					version_name, 
					active, 
					ordinal 
				from 
					cms_version 
				group by 
					cms,
					id, 
					version_name, 
					active, 
					ordinal 
				order by 
					cms, 
					ordinal
SQL;
			try{
				$sth = $this->dbh->prepare($sql);
				$sth->execute();
			}catch(\PDOException $e){
				\error_log($e->getMessage() . ' on: ' . $e->getLine());
			}
			
			return $sth->fetchAll(\PDO::FETCH_ASSOC|\PDO::FETCH_GROUP);
		}
		
		public function getServerSelection(){
			$qry_p = array();
			$sql =<<<'SQL'
				SELECT
					id,
					name
				FROM
					server
				WHERE
					active = true
				ORDER BY
					gui_ordinal
SQL;

			try{
				$sth = $this->dbh->prepare($sql);
				$sth->execute();
			}catch(\PDOException $e){
				error_log($e->getMessage() . ' on: ' . $e->getLine());
			}
			
			return $sth->fetchAll(\PDO::FETCH_ASSOC);
		}
		
		public function getCmsList(){
			$sql =<<<'SQL'
				SELECT
					id,
					name
				FROM
					cms
				WHERE
					active = true
SQL;

			try{
				$sth = $this->dbh->prepare($sql);
				$sth->execute();
			}catch(\PDOException $e){
				\error_log($e->getMessage() . ' on: ' . $e->getLine());
			}
			
			return $sth->fetchAll(\PDO::FETCH_ASSOC);
		}
		
		public function getVtmAdmins(){
			$sql =<<<'SQL'
				SELECT
					id,
					name
				FROM
					vtm_admins
				ORDER BY
					name
SQL;
			try{
				$sth = $this->dbh->prepare($sql);
				$sth->execute();
			}catch(\PDOException $e){
				\error_log($e->getMessage() . ' on: ' . $e->getLine());
			}
			
			return $sth->fetchAll(\PDO::FETCH_ASSOC);
		}
		
		private function getServer($site){//$server){
			$qry_p = array($site);
			
			$sql =<<<'SQL'
				SELECT
					serv.auto_url
				FROM
					vtm_sites vtms
				JOIN
					server serv
				ON
					serv.id = vtms.server
				WHERE
					vtms.id = ?
SQL;
			try{
				$sth = $this->dbh->prepare($sql);
				$sth->execute($qry_p);
			}catch(\PDOException $e){
				\error_log($e->getMessage() . ' on: ' . $e->getLine());
			}
			
			return $sth->fetchColumn(0);
		}
		
		private function getUserCookie(){
			\error_log("setting cookie for user");
		}
		
		public function setUserCookie(){
			\setcookie('automaton_user',$_POST['username_login'],time()+60*60*24*30,'/');
		}
		
		public function getUserList(){
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
			
			return $sth->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_ASSOC);
			
		}
		
		public function getDBList(){
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
			
			return $sth->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_ASSOC);
		}
		
		public function getCMSVList(){
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
			
			return json_encode($sth->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_ASSOC));
		}
		
		public function getSiteUpdateStats(){
			$sql =<<<'SQL'
        			SELECT
						sid,
        				company,
						staging_needs_update,
						needs_update,
						server,
						active,
						in_dev,
						cms
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
		
		public function setStagingLatestDo($site){
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
		
		public function selectSitePlugins($site){
			$qry_p = array($site);
			
			$sql =<<<'SQL'
        		SELECT
        			plug.plugin_name,
					pts.active,
					pts.update_needed
        		FROM
        			vtm_sites site
        		LEFT JOIN
        			plugin_to_site pts
        		ON
        			pts.sid = site.id
        		JOIN
        			plugins plug on plug.pid = pts.pid
        		WHERE
        			site.id = ?
				ORDER BY
					pts.active desc,
					plug.plugin_name
SQL;
			try{
				$sth = $this->dbh->prepare($sql);
				$sth->execute($qry_p);
			}catch(\PDOException $e){
				\error_log($e->getMessage());
			}
			
			return $sth->fetchAll(\PDO::FETCH_ASSOC);
		}
		
		public function siteLookup($comp){
			$qry_p = array($comp);
			
			$sql =<<<'SQL'
				SELECT
					id
				FROM
					vtm_sites
				WHERE
					company = ?
SQL;
			$sth = $this->dbh->prepare($sql);
			
			try{
				$sth->execute($qry_p);
			}catch(\PDOException $e){
				error_log($e->getMessage() . ' on ' . $e->getLine() . ' in ' . __CLASS__);
			}
			
			return $sth->fetchColumn(0);
		}
		
		public function getSiteNotes($site,$note_id){
			$qry_p = array($site);
			if(isset($note_id) && $note_id !== ""){
				\array_push($qry_p, $note_id);
			}
			
			$sql =<<<'SQL'
    			SELECT
    				to_char(note.note_date - interval '7 hours', 'MM/DD/YYYY HH:MI am'::text) note_date_char,
    				note.note_text,
					usr.username,
					note.style
				FROM
					notes note
				JOIN
					system_users usr
				ON
					usr.id = note.usr
				WHERE
					note.sid = ?
				AND
					note.active is TRUE
				%s
				ORDER BY
					note_date asc
SQL;
			$sql = sprintf($sql,
					(isset($note_id) && $note_id !== "") ? "AND nid = ? " : ''
					);
			
			try{
				$sth = $this->dbh->prepare($sql);
				$sth->execute($qry_p);
			}catch(\PDOException $e){
				\error_log($e->getMessage());
			}
			
			$data = $sth->fetchAll(\PDO::FETCH_ASSOC);
			
			$stuff = self::replaceBackticks($data);
			
			return $stuff;
		}
		
		private function replaceBackticks($text){
			
			$rplc = array("<span class='code_blurb'>","</span>");
			$n = 0;
			foreach($text as $tkey => $tbody){
				$new_text = preg_replace_callback(
					"/\`/", 
					function () use(&$n,$rplc){
						return $rplc[$n++ % count($rplc)];
					}, 
					$tbody['note_text']
				);
				
				$text[$tkey]['note_text'] = $new_text;
			}
			
			return $text;
		}
		
		public function appendSiteNote($site,$note,$usr,$bold){
			$qry_p = array($site,$note,$bold,$usr);
			
			$sql =<<<'SQL'
    			INSERT INTO
    				notes(sid,note_text,usr,style)
				SELECT
					?,
					?,
					id,
					(
						SELECT
							CASE WHEN
								? = true
							THEN
								'bold'
							ELSE
								''
						END
					)
				FROM
					system_users
				WHERE
					username = ?
    			RETURNING nid
SQL;
			try{
				$sth = $this->dbh->prepare($sql);
				$sth->execute($qry_p);
			}catch(\PDOException $e){
				\error_log($e->getMessage() . ' on: ' . $e->getLine() . ' in: ' . __CLASS__);
			}
			
			return $sth->fetchColumn(0);
		}
		
		public function getProductionDbList($db_nmbr,$site){
			$qry_p = array($site);
			
			$sql =<<<'SQL'
    			SELECT
					id,
					server,
					name,
					disposition
				FROM
					dbs
				WHERE
					disposition in('staging','production')
				AND
					site = ?
SQL;
			
			$sth = $this->dbh->prepare($sql);
			
			try{
				$sth->execute($qry_p);
			}catch(\PDOException $e){
				\error_log($e->getMessage() . ' on ' . $e->getMessage() . ' in ' . __CLASS__);
			}
			
			$data = $sth->fetchAll(\PDO::FETCH_ASSOC);
			
			return $data;
		}
		
		public function updateProdUpdated($date,$site){
			$qry_p = array($date,$site);
			
			$sql =<<<'SQL'
        			UPDATE
        				vtm_sites
        			SET
        				last_update_prod = ?
					WHERE
						id = ?
SQL;
			try{
				$sth = $this->dbh->prepare($sql);
				$sth->execute($qry_p);
				
				// Toggle the queued boolean too
				self::setQueuedToggle($site);
			}catch(\PDOException $e){
				\error_log($e->getMessage());
			}
		}
		
		public function setQueuedToggle($site){
			$qry_p = array($site,$site);
			
			$sql =<<<'SQL'
        		UPDATE
        			vtm_sites
        		SET
        			update_queued =
        				(
        						SELECT
        							CASE WHEN
        								update_queued = FALSE
        							THEN TRUE
        							ELSE FALSE
        							END
        						FROM
        							vtm_sites WHERE id = ?)
        		WHERE
        			id = ?
SQL;
			try{
				$sth = $this->dbh->prepare($sql);
				$sth->execute($qry_p);
			}catch(\PDOException $e){
				\error_log($e->getMessage());
			}
		}
		
		public function updateStagingUpdated($date,$site){
			$qry_p = array($date,$site);
			
			$sql =<<<'SQL'
        			UPDATE
        				vtm_sites
        			SET
        				last_update_staging = ?
					WHERE
						id = ?
SQL;
			try{
				$sth = $this->dbh->prepare($sql);
				$sth->execute($qry_p);
			}catch(\PDOException $e){
				\error_log($e->getMessage());
			}
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
		
		public function parsePluginsFile($site,$list){
			$return_array = array();
			
			/**
			 * If this is a json formatted list
			 * we're dealing with Drupal. Use a different
			 * parsing method, else use the original
			 * method set up for WP
			 */
			if(substr($list, 0, 1) == "{"){
				$object_list = json_decode($list,true);
				foreach($object_list as $pm => $text){
					$text['site'] = $site;
					
					$insert = self::insertDrupalPlugin($text);
					
					array_push($return_array, array($text['name'] . ' -> ' . $insert));
				}
			}else{
				$list_array = \explode("\n", $list);
				
				foreach($list_array as $line => $text){
					
					$line_splode = \explode("|", $site . $text);
					
					$insert = self::insertPlugin(array_slice($line_splode, 0, 5));
					
					\array_push($return_array, array($line_splode[1] . ' -> ' . $insert));
				}
			}
			
			return $return_array;
		}
		
		private function insertDrupalPlugin(array $plugin){
			$ins_ext = 'insert';
			
			$qry_p = array(
							$plugin['name'],
							$plugin['status'],
							$plugin['version'],
							$plugin['name'],
			);
			
			$sql =<<<'SQL'
				INSERT INTO
					plugins(plugin_name, cms, active_txt, version)
					SELECT
						?,
						1,
						?,
						?
					WHERE NOT EXISTS
						(SELECT
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
				$sth->execute($qry_p);
			}catch(\PDOException $e){
				\error_log($e->getMessage() . ' on: ' . $e->getLine());
			}
			
			$l_qry_p = array($plugin['site']);
			
			$is_active = "";
			$plugin_id = "";
			
			if(\strtolower($plugin['status']) == "enabled"){
				$is_active = "TRUE";
			}elseif(strtolower($plugin['status']) == "not installed" 
					|| 
					strtolower($plugin['status']) == "disabled"){
				$is_active = "FALSE";
			}
			
			if($sth->rowCount() > 0){
				$plugin_id = $sth->fetchColumn(0);
				\array_push(
							$l_qry_p,
							$plugin_id,
							$is_active,
							$plugin['site'],
							$plugin_id
						);
			}else{
				//Plugin exists, get its id
				$ins_ext = "extant";
				$i_qry_p = array($plugin['name']);
				
				try{
					$sth = $this->dbh->prepare("SELECT pid FROM plugins where plugin_name = ?");
					$sth->execute($i_qry_p);
				}catch(\PDOException $e){
					\error_log($e->getMessage());
				}
				
				$plugin_id = $sth->fetchColumn(0);
				array_push(
							$l_qry_p, 
							$plugin_id,
							$is_active, 
							$plugin['site'],
							$plugin_id
						);
			}
			
			$l_sql =<<<'LSQL'
     			INSERT INTO
     				plugin_to_site(sid,pid,active)
				SELECT
					?, 
					?, 
					?
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
				error_log($e->getMessage() . ' on: ' . $e->getLine());
			}
			
			if($sth->rowCount() > 0){
				
			}else{
				$u_qry_p = array($is_active, $plugin['site'], $plugin_id);
				
				$u_sql =<<<'USQL'
        				UPDATE
        					plugin_to_site
        				SET
        					active = ?
        				WHERE
        					sid = ?
						AND
							pid = ?
USQL;
				
				try{
					$sth = $this->dbh->prepare($u_sql);
					$sth->execute($u_qry_p);
				}catch(\PDOException $e){
					\error_log($e->getMessage() . ' on: ' . $e->getLine());
				}
			}
			
			return $ins_ext;
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
	}

?>
