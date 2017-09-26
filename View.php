<?php 

namespace Automater;

require_once 'vendor/autoload.php';

	class View extends BaseObject{
		private $loader = null;
		private $twig	= null;
		private $data	= null;
		
		public function __construct(){
			$this->loader = new \Twig_Loader_Filesystem('templates');
			$this->twig		= new \Twig_Environment($this->loader,array(
					'debug'	=> true,
			));
			$this->twig->addExtension(new \Twig_Extension_Debug());
		}
		
		public function setData($data){
			$this->data = $data;
		}
		
		public function renderView($which){
			/**
			 * Make sure there is a view selected
			 */
			try{
				if(!isset($which)){
					throw new \Exception("A view must be selected");
				}
			}catch(\Exception $e){
				\error_log($e->getMessage());
			}
			
			$template = null;
			
			switch($which){
				case 'base':
					$template = self::getBaseView($this->data);
					break;
				case 'login':
					$template = self::getLoginView($this->data);
					break;
				case 'feed':
					$template = self::getFeedView($this->data);
					break;
				default:
					$template = self::getBaseView($this->data);
			}
			
			return $template;
		}
		
		private function getBaseView($data){
			$template = $this->twig->load("index.html");
			
			return $template->render(array(
					'data'	=> $data,
			));
		}
		
		private function getLoginView($data){
			$template = $this->twig->load("login.html");
			
			return $template->render(array(
					'data'	=> $data,
			));
		}
		
		private function getFeedView($data){
			$template = $this->twig->load("feed.html");
			
			return $template->render(array(
					'data'	=> $data,
			));
		}
	}

?>
