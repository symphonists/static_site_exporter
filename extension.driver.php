<?php

	require_once(EXTENSIONS . '/static_site_exporter/lib/class.crawler.php');

	Class extension_static_site_exporter extends Extension{
		
		public function fetchNavigation(){
			return array(
				array(
					'location' => 'System',
					'name' => 'Static Site Exporter',
					'link' => '/'
					)
			);
		}

		public function getSubscribedDelegates(){
			return array(
						array(
							'page' => '/system/preferences/',
							'delegate' => 'AddCustomPreferenceFieldsets',
							'callback' => 'appendPreferences'
						),
						
						array(
							'page' => '/system/preferences/',
							'delegate' => 'Save',
							'callback' => 'savePreferences'
						),						
					);
		}
		
		public function savePreferences($context){
			
			$pairs = array();
			
			$conf = array_map('trim', $context['settings']['static-site-exporter']);
			
			$force_include = preg_split('/[\r\n]+/i', $conf['force-include'], -1, PREG_SPLIT_NO_EMPTY);
			$force_include = array_map('trim', $force_include);
			
			$context['settings']['static-site-exporter'] = array(
				'force-include' => implode(',', $force_include),
				'include-404' => (isset($conf['include-404']) ? 'yes' : 'no'),
				'index-file-name' => $conf['index-file-name'],
				'export-location' => rtrim($conf['export-location'], '/ ')
			);

		}

		public function appendPreferences($context){
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', 'Static Site Exporter'));

			$ul = new XMLElement('ul');
			$ul->setAttribute('class', 'group');
				
			$li = new XMLElement('li');
			$label = Widget::Label('Index File Name');
			$label->appendChild(Widget::Input('settings[static-site-exporter][index-file-name]', General::Sanitize(Administration::Configuration->get('index-file-name', 'static-site-exporter'))));		
			$li->appendChild($label);	
						
			$ul->appendChild($li);	
			
			$li = new XMLElement('li');
			
			$label = Widget::Label('Export Location');
			$label->appendChild(new XMLElement('i', 'Optional'));
			$label->appendChild(Widget::Input('settings[static-site-exporter][export-location]', General::Sanitize(Administration::Configuration->get('export-location', 'static-site-exporter'))));		
			$li->appendChild($label);
			$li->appendChild(new XMLElement('p', 'Leave blank for default.', array('class' => 'help', 'title' => EXTENSIONS.'/static_site_exporter/exports/')));	
				
			$ul->appendChild($li);					
						
			$fieldset->appendChild($ul);
			
			$ul = new XMLElement('ul');
			$ul->setAttribute('class', 'group');

			$force_include = preg_replace('/,/i', "\r\n", Administration::Configuration->get('force-include', 'static-site-exporter'));

			$li = new XMLElement('li');	
			$label = Widget::Label('Force Include');
			$label->appendChild(new XMLElement('i', 'Optional'));
			$label->appendChild(Widget::Textarea('settings[static-site-exporter][force-include]', 5, 50, General::sanitize($force_include)));		
			$li->appendChild($label);
			$li->appendChild(new XMLElement('p', 'Relative to <code>'.DOCROOT.'</code>. Can be files or folders. Folders will be traversed and all sub-folders added. Separate each with a new line.', array('class' => 'help')));				
			$ul->appendChild($li);
									
			$li = new XMLElement('li');
			$label = Widget::Label();
			$input = Widget::Input('settings[static-site-exporter][include-404]', 'yes', 'checkbox');
			if(Symphony::Configuration()->get('include-404', 'static-site-exporter') == 'yes') $input->setAttribute('checked', 'checked');
			$label->setValue($input->generate() . ' Include 404 pages in export archive');
			$li->appendChild($label);		
			//$li->appendChild(new XMLElement('p', 'Checking this will leave <code>404 Not Found</code> pages in the archive.', array('class' => 'help')));	
			
			$ul->appendChild($li);			
					
			$fieldset->appendChild($ul);
			
			$context['wrapper']->appendChild($fieldset);				
		}
		
		public function fetchLinks(){
			return Symphony::Database()->fetch("SELECT `last_indexed`, `time_to_index`, `status`, `url` FROM `".Crawler::TABLE."` ORDER BY `last_indexed` ASC");
		}
		
		public function averageTime(){
			return Symphony::Database()->fetchVar('sum', 0, "SELECT SUM(`time_to_index`) AS `sum` FROM `".Crawler::TABLE."`");
		}
		
		public function totalLinks(){
			return Symphony::Database()->fetchVar('count', 0, "SELECT COUNT(*) AS `count` FROM `".Crawler::TABLE."`");
		}
		
		public function lastIndexed(){
			return Symphony::Database()->fetchVar('timestamp', 0, "SELECT UNIX_TIMESTAMP(`last_indexed`) AS `timestamp` FROM `".Crawler::TABLE."` ORDER BY `last_indexed` DESC LIMIT 1");
		}
		
		public function exportDestination(){
			$dest = Symphony::Configuration()->get('export-location', 'static-site-exporter');
			
			if(empty($dest)){
				return MANIFEST . '/tmp';
			}
			else {
				return DOCROOT . '/' . Symphony::Configuration()->get('export-location', 'static-site-exporter');
			} 
			
			return $dest;
		}
		
		public function exportDestinationLink(){
			return str_replace(DOCROOT, URL, $this->exportDestination()) . '/';
		}
		
		public function lastBuild(){

			$dir = new DirectoryIterator($this->exportDestination());
			
			$match = NULL;
			
			foreach ($dir as $file){
				
				if(is_array($match) && $file->getMTime() < $match['creation']) continue;
				
				if(!$file->isDot() && !$file->isDir() && preg_match('/\.zip$/i', $file->getFilename())){
					$match = array('filename' => $file->getFilename(), 'creation' => $file->getMTime());
				}
			}
			
			return $match;
			
		}
		
		public function fetchStatusCodes(){
			$codes = Symphony::Database()->fetch("SELECT `status`, COUNT(`status`) AS `count` FROM `".Crawler::TABLE."` GROUP BY `status`");
			
			$tmp = array();
			foreach($codes as $row){
				$tmp[$row['status']] = $row['count'];
			}
			
			return $tmp;
		}
		
		public function install(){
			
			Symphony::Configuration()->set('include-404', 'no', 'static-site-exporter');
			Symphony::Configuration()->set('index-file-name', 'index.html', 'static-site-exporter');
					
			Administration::instance()->saveConfig();
			
			return Symphony::Database()->query("
			
				CREATE TABLE `".Crawler::TABLE."` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `url` varchar(255) NOT NULL,
				  `last_indexed` datetime default NULL,
				  `time_to_index` float default NULL,
				  `contents` text,
				  `status` int(4) unsigned default '200',
				  PRIMARY KEY  (`id`),
				  UNIQUE KEY `url` (`url`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
			
			");
		}
		
		public function uninstall(){
			Symphony::Configuration()->remove('static-site-exporter');			
			Administration::instance()->saveConfig();
						
			return Symphony::Database()->query('DROP TABLE `'.Crawler::TABLE.'`');			
		}

	}
	
?>