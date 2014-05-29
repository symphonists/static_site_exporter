<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(EXTENSIONS . '/static_site_exporter/lib/class.crawler.php');

	Class contentExtensionStatic_Site_ExporterIndex extends AdministrationPage{

		function __construct(){
			parent::__construct();
			$this->setPageType('form');
			$this->setTitle('Symphony &ndash; Static Site Exporter');
		}

		function view(){		
			
			if(isset($_GET['force-unlock'])) {
				Crawler::unlock(true);
			}
			
			$exporter = ExtensionManager::create('static_site_exporter');
			
			Administration::instance()->Page->addScriptToHead(URL . '/extensions/static_site_exporter/assets/getstatus.js', 80);
			
			$this->appendSubheading('Static Site Exporter');
			
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', 'Actions'));			
			
			if(Crawler::isCrawling()){
				
				$div = new XMLElement('div');
				$div->setAttribute('style', 'background-color: #ddc; padding: 10px; margin-bottom: 15px; text-align: center;');
				$div->setAttribute('id', 'crawler-progress');
				
				$average_links = Symphony::Configuration()->get('average-links', 'static-site-exporter');
				$average_time = Symphony::Configuration()->get('average-time', 'static-site-exporter');
				
				$div->appendChild(new XMLElement('h2', 'Indexing is currently in progress', array('style' => 'font-weight: bold')));
				$div->appendChild(new XMLElement('p', $exporter->totalLinks() . (is_numeric($average_links) && $average_links > 1 ? '/' . $average_links : NULL).' pages crawled'));
				
				if($average_time != '') {
					$div->appendChild(new XMLElement('p', 'Estimated ' . number_format(max(0, ($average_time - $exporter->averageTime())), 0, '.', '') . 's remaining'));
				} else {
					$div->appendChild(new XMLElement('p', number_format($exporter->averageTime(), 2, '.', '') . ' sec elapsed'));
				}
				
				$div->appendChild(new XMLElement('p', '<a href="?force-unlock">If crawl is not responding, click here to force completion</a>'));
				
				$group->appendChild($div);
				$this->Form->appendChild($group);
			}
			
			else{
			
				$last_index = $exporter->lastIndexed();
				$last_build = $exporter->lastBuild();
			
				$ul = new XMLElement('ul', NULL, array('id' => 'file-actions', 'class' => 'group'));
			
				$li = new XMLElement('li');
				$div = new XMLElement('div', 'Index Site', array('class' => 'label'));			
				$span = new XMLElement('span');
				
				$span->appendChild(new XMLElement('button', 'Index Site', array('name' => 'action[try-crawl]')));
			
				$div->appendChild($span);
				$li->appendChild($div);		
				$li->appendChild(new XMLElement('p', 'Follows all links, starting from the index page, building a full link list.', array('class' => 'help')));						
				$ul->appendChild($li);
			
				$li = new XMLElement('li');
				$div = new XMLElement('div', 'Generate Static Build', array('class' => 'label'));
				$span = new XMLElement('span');
				$span->appendChild(new XMLElement('button', 'Generate Static Build', array('name' => 'action[export]')));
				$div->appendChild($span);
				$li->appendChild($div);
				$li->appendChild(new XMLElement('p', 'Archives the indexed content for download.', array('class' => 'help')));
				
				if ($last_build['filename']) {
					$li->appendChild(new XMLElement('p', 'Last build generated:</strong> ' . DateTimeObj::get('M d, Y \a\t h:i a', $last_build['creation']) . ' (<a href="'.$exporter->exportDestinationLink().$last_build['filename'].'">download</a>)', array('class' => 'help')));
				}
				
				$ul->appendChild($li);
			
				$group->appendChild($ul);			
				$this->Form->appendChild($group);
			
				$average = $exporter->averageTime();
			
				if($average){
					
					$group = new XMLElement('fieldset');
					$group->setAttribute('class', 'settings');
					$group->appendChild(new XMLElement('legend', 'Statistics'));
				
					$ul = new XMLElement('ul');
					
					$li = new XMLElement('li', '<strong>Last crawl performed:</strong> ' . DateTimeObj::getTimeAgo($last_index));
					$li->setAttribute('title', DateTimeObj::get('M d, Y \a\t h:i a', $last_index));
					$ul->appendChild($li);
					
					$ul->appendChild(new XMLElement('li', '<strong>Total links:</strong> ' . $exporter->totalLinks()));
				
					//$ul->appendChild(new XMLElement('li', '<strong>Last crawl duration:</strong> ' . number_format($average, 2, '.', '') . ' sec'));
					//$ul->appendChild(new XMLElement('li', '<strong>Average crawl time per page:</strong> ' . number_format($average * (1/$exporter->totalLinks()), 2, '.', '') . ' sec'));
				
					$status = $exporter->fetchStatusCodes();
					$ul->appendChild(new XMLElement('li', '<strong>Page status:</strong> ' .  max(0, $status[200]) . ' OK, ' . max(0, $status[404]) . ' missing, ' .  max(0, $status[999]) . ' error (<a href="'.URL.'/symphony/extension/static_site_exporter/log/">last crawl log</a>)'));
					
					$group->appendChild($ul);
				
					$this->Form->appendChild($group);
					
				}
			
			}
			

		}
		
		function action(){
			
			if(Crawler::isCrawling()) {
				
				$this->pageAlert('Crawling is already in progress. You will need to wait until it is complete.', Alert::ERROR);
				
			} else{
				
				$exporter = ExtensionManager::create('static_site_exporter');
				
				if(isset($_POST['action']['try-crawl'])){
					
					$author = Administration::instance()->Author;
					
					## Temporarily toggle auth token on
					if($author->get('auth_token_active') == 'no'){
						require_once(TOOLKIT . '/class.authormanager.php');
						$AuthorManager = new AuthorManager(Administration::instance());
						$author = $AuthorManager->fetchByID($author->get('id'));						
						
						$token_toggled = true;
						$author->set('auth_token_active', 'yes');
						$author->commit();
					}
					
					include_once(TOOLKIT . '/class.gateway.php');
		            $ch = new Gateway;

		            $ch->init();
					$ch->setopt('TIMEOUT', 1);
		            $ch->setopt('URL', URL . '/symphony' . getCurrentPage() . '?action[crawl]=true&auth-token=' . $author->createAuthToken());
		            $ch->exec();
					
					if($token_toggled){
						$author->set('auth_token_active', 'no');
						$author->commit();
						unset($author);
						unset($AuthorManager);
					}
					
					redirect(URL . '/symphony/extension/static_site_exporter/');
					
				}
				
				elseif(isset($_REQUEST['action']['crawl'])){ 
					
					Crawler::lock();
					Symphony::Database()->query("TRUNCATE TABLE `" . Crawler::TABLE . "`");
					Crawler::crawl('/');
					Crawler::unlock(true);
					
					Symphony::Configuration()->set('average-links', $exporter->totalLinks(), 'static-site-exporter');
					Symphony::Configuration()->set('average-time', $exporter->averageTime(), 'static-site-exporter');
					Administration::instance()->saveConfig();

				}
			
				elseif(isset($_POST['action']['export'])){
					
					$force_includes = preg_split('/,/', Symphony::Configuration()->get('force-include', 'static-site-exporter'), -1, PREG_SPLIT_NO_EMPTY);
					
					Crawler::export(Symphony::Configuration()->get('static-site-exporter'), $exporter->exportDestination() . '/' . DateTimeObj::get('Ymd-Hi') . '.zip', $force_includes);

				}
			
			}
		}
		
	}
	
?>