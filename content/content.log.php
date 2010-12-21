<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(EXTENSIONS . '/static_site_exporter/lib/class.crawler.php');

	Class contentExtensionStatic_Site_ExporterLog extends AdministrationPage{

		function __construct(&$parent){
			parent::__construct($parent);
			$this->setPageType('form');			
			$this->setTitle('Symphony &ndash; Site Exporter &ndash; Crawl Log');
		}
		
		private static function __httpStatusCodeToText($code){
			switch($code){
				case '404': return 'Missing';
				case '200': return 'OK';
				case '301': return 'Moved Permanently';
				case '304': return 'Not Modified';
				case '999': return 'ERROR';
				default: return NULL;
			}
		}
		
		function view(){
			
			include_once(TOOLKIT . '/class.htmlpage.php');

			$Page = new HTMLPage();

			$Page->Html->setElementStyle('html');

			$Page->Html->setDTD('<!DOCTYPE html>');
			$Page->Html->setAttribute('xml:lang', 'en');
			$Page->addElementToHead(new XMLElement('meta', NULL, array('http-equiv' => 'Content-Type', 'content' => 'text/html; charset=UTF-8')), 0);

			$Page->addStylesheetToHead(URL . '/extensions/static_site_exporter/assets/log.css', 'screen', 30);

			$Page->addHeaderToPage('Content-Type', 'text/html; charset=UTF-8');
			$Page->setTitle('Symphony &ndash; Site Exporter &ndash; Crawl Log');			
			
			$exporter = Administration::instance()->ExtensionManager->create('static_site_exporter');
			
			$links = $exporter->fetchLinks();	

			$Page->Body->appendChild(new XMLElement('h2', 'Crawl Log &ndash; '.DateTimeObj::get('c', strtotime($links[0]['last_indexed']))));
			
			$aTableHead = array(

				array('Status', 'col'),
				array('Location', 'col'),
				array('Render Time', 'col')

			);	

			$aTableBody = array();

			foreach($links as $l){
				
				$td1 = Widget::TableData(self::__httpStatusCodeToText($l['status']), 'status');
				$td2 = Widget::TableData(Widget::Anchor($l['url'], URL.$l['url']));
				$td3 = Widget::TableData($l['time_to_index'], 'render-time');
				
				$class = NULL;
				if($l['status'] == 404) $class = 'missing';
				elseif($l['status'] == 999) $class = 'error';
				
				$aTableBody[] = Widget::TableRow(array($td1, $td2, $td3), $class);

			}
			

			$table = Widget::Table(
								Widget::TableHead($aTableHead), 
								NULL, 
								Widget::TableBody($aTableBody)
						);
			
			$table->setAttributeArray(array('cellpadding' => '0', 'cellspacing' => '0'));
			
			$Page->Body->appendChild($table);
			
			print $Page->generate();

			exit();	
			
		}
		
	}
	
?>