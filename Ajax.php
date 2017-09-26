<?php 

namespace Automater;

	class Ajax extends BaseObject{
		private $model = null;
		
		/**
		 * 
		 * @param unknown $model
		 */
		public function __construct(Model $model){
			$this->model = $model;
			
		}
		
		/**
		 * 
		 * @param unknown $method
		 * @throws \Exception
		 */
		public function handleRequest($method){
			$func = "";
			$result = null;
			
			if(\strtolower($method) == "post"){
				$func = $_POST['func'];
			}elseif(\strtolower($method) == "get"){
				$func = $_GET['func'];
			}
			
			$params = $_REQUEST;
			
			
			switch($func){
				case 'insert_new_site':
					$site_data = array(
						'name'	=> $_POST['name'],
						'host'	=> $_POST['host'],
						'cmss'	=> $_POST['cmss'],
						'sver'	=> $_POST['sver'],
						'pver'	=> $_POST['pver'],
						'dblc'	=> $_POST['dblc'],
						'suri'	=> $_POST['suri'],
						'puri'	=> $_POST['puri'],
						'sdbn'	=> $_POST['sdbn'],
						'pdbn'	=> $_POST['pdbn'],
						'gitr'	=> $_POST['gitr'],
						'admn'	=> $_POST['admn']
					);
					$result = $this->model->insertNewSite($site_data);
					break;
				case 'autobot_zip':
					$result = $this->model->triggerZip($_REQUEST['server'],$_REQUEST['site'],$_REQUEST['disp']);
					break;
				case 'ping_autobot':
					$result = $this->model->pingAutobot(
									$_REQUEST['server'],
									$_REQUEST['site'],
									$_REQUEST['disp']
					);
					break;
				case 'autobot_dbb':
					$result = $this->model->dbRunBackup($_REQUEST['site'],$_REQUEST['disp']);
					break;
				case "db_backup_do":
					$result = $auto->dbBacupStageDo($_POST['db_name'],$_POST['db_user']);
					break;
				case "staging_cms_backup_do":
					$result = $auto->cmsBackupDo($_POST['site'],$_POST['mode']);
					break;
				case "production_copy_db_do":
					$result = $auto->dbCopy($_POST['db_nmbr']);
					break;
				case "add_note_to_site":
					$result = $this->model->appendSiteNote($_POST['site'],$_POST['note'],$_POST['user'],$_POST['bold']);
					break;
				case "staging_updated_date":
					$result = $this->model->updateStagingUpdated($_POST['date'],$_POST['site']);
					break;
				case "prod_updated_date":
					$result = $this->model->updateProdUpdated($_POST['date'],$_POST['site']);
					break;
				case "toggle_queued":
					$result = $this->model->setQueuedToggle($_POST['site']);
					break;
				case "update_staging_cms_latest":
					$result = $this->model->setStagingLatestDo($_POST['site']);
					break;
				case "get_site_params":
					$result = $this->model->getSiteParams($_GET['site']);
					break;
				case "get_site_backup_log":
					$result = $this->model->getSiteUpdateLog(
							$_GET['site'],
							(isset($_GET['recd'])) ? $_GET['recd'] : null,
							(isset($_GET['dbase'])) ? $_GET['dbase'] : null
					);
					break;
				case "get_production_staging_db":
					$result = $this->model->getProductionDbList($_GET['db_nmbr'],$_GET['site']);
					break;
				case "gather_site_info":
					$result = $this->model->siteLookup($_GET['site']);
					break;
				case "gather_site_notes":
					$result = $this->model->getSiteNotes($_GET['site'],$_GET['note_id']);
					break;
				case "get_site_plugins":
					$result = $this->model->selectSitePlugins($_GET['site']);
					break;
				case "parse_site_plugins":
					$result = $this->model->parsePluginsFile($_POST['site'],$_POST['list']);
					break;
				default:
					throw new \Exception("Ooops, no ajax function specified: $func");
				}
				
				if(is_array($result)){
					$result = json_encode($result);
				}
				
				echo $result;
			}
		}
?>
