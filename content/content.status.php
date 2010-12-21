<?php

	require_once(EXTENSIONS . '/static_site_exporter/lib/class.crawler.php');

	Class contentExtensionStatic_Site_exporterStatus extends AjaxPage{

		public function view(){
			
			$exporter = Administration::instance()->ExtensionManager->create('static_site_exporter');
	
			if(Crawler::isCrawling()){
				
				$average_links = Symphony::Configuration()->get('average-links', 'static-site-exporter');
				$average_time = Symphony::Configuration()->get('average-time', 'static-site-exporter');
				
				$p1 = new XMLElement('h2', 'Indexing is currently in progress', array('style' => 'font-weight: bold;'));
				$p2 = new XMLElement('p', $exporter->totalLinks() . (is_numeric($average_links) && $average_links > 1 ? '/' . $average_links : NULL).' pages crawled');
				
				if($average_time != '')
					$p3 = new XMLElement('p', 'Estimated ' . number_format(max(0, ($average_time - $exporter->averageTime())), 1, '.', '') . ' sec remaining');
					
				else
					$p3 = new XMLElement('p', number_format($exporter->averageTime(), 2, '.', '') . ' sec elapsed');
				
				
				$p4 = new XMLElement('p', '<a href="?force-unlock">If crawl is not responding, click here to flag as complete.</a>');
			
				$this->_Result->setValue(General::sanitize($p1->generate(true) . $p2->generate(true) . $p3->generate(true) . $p4->generate(true)));
			}
			
			else $this->_Result->setValue(General::sanitize('<h2 id="complete">Done<h2><p><a href="?force-unlock">Click here to return</a></p>'));
			
			
		}
		
	}

?>