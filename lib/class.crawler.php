<?php
	
	Class Crawler {
		
		const TABLE = 'tbl_static_site_exporter_index';
		
		public static function isCrawling($lock='crawl.lock'){
			return is_file(TMP . '/' . $lock);
		}
		
		public static function lock($lock='crawl.lock'){
			if(self::isCrawling($lock)) return false;
			
			return file_put_contents(TMP . '/' . $lock, getmypid());
		}
		
		public static function unlock($force=false, $lock='crawl.lock'){
			if(!self::isCrawling($lock)) return true;
			
			if(!$force && file_get_contents(TMP . '/' . $lock) != getmypid()) return false;
			
			unlink(TMP . '/' . $lock);
			
			return true;
		}
		
		public static function render($url='/'){
			
			include_once(TOOLKIT . '/class.gateway.php');
            $ch = new Gateway;
            
            $ch->init();
            $ch->setopt('URL', URL . '/' . ltrim($url, '/'));
			$ch->setopt('RETURNHEADERS', 1);
			
            $result = $ch->exec();
			
			$parts = preg_split('/(\r\n){2,}/', $result, -1, PREG_SPLIT_NO_EMPTY);
			
			$content = array_pop($parts);
			$headers = array_pop($parts);
			
			return array($headers, $content);
						
		}
		
		// private static function __processCustomPairReplacement(&$subject, $key, $val){
		// 	
		// 	if(!preg_match('/<\!--\s+ExportReplace\s+'.$key.'-begin\s+-->/i', $subject)) return;
		// 	
		// 	$subject = preg_replace('/<\!--\s+ExportReplace\s+'.$key.'-begin\s+-->([\w\W]+)<\!--\s+ExportReplace\s+'.$key.'-end\s+-->/i', 
		// 				 		    "<!-- ExportReplace $key-begin -->$val<!-- ExportReplace $key-end -->",
		// 				 			$subject);
		// 
		// }
		
		public static function export($config, $destination=NULL, array $force_includes=array()){
			
			$result = Symphony::Database()->fetchCol('id', "SELECT `id` FROM `".self::TABLE."`" . ($config['include-404'] == 'yes' ? NULL : " WHERE `status` != '404'"));

			require_once(EXTENSIONS . '/static_site_exporter/lib/class.archivezip.php');
			
			$archive = new ArchiveZip;
			
			$pairs = $ssi_includes = array();
			
			// if(file_exists(EXTENSIONS . '/static_site_exporter/lib/inc.ssi_replace_pairs.php')) 
			// 	include(EXTENSIONS . '/static_site_exporter/lib/inc.ssi_replace_pairs.php');
				
			if(file_exists(EXTENSIONS . '/static_site_exporter/lib/inc.string_replace_pairs.php')) 
				include(EXTENSIONS . '/static_site_exporter/lib/inc.string_replace_pairs.php');
			
			// echo '<pre>'; var_dump($result); exit;

			foreach($result as $id){
				$row = Symphony::Database()->fetchRow(0, "SELECT * FROM `".self::TABLE."` WHERE `id` = '$id' LIMIT 1");
				
				if(is_array($pairs) && !empty($pairs)){
					$row['contents'] = str_replace(array_keys($pairs), array_values($pairs), $row['contents']);				
				}
				
				$contents = urldecode($row['contents']);

				// if(is_array($ssi_includes) && !empty($ssi_includes)){
				// 	foreach($ssi_includes as $key => $val){
				// 		self::__processCustomPairReplacement($row['contents'], $key, $val);
				// 	}				
				// }

				if(preg_match('/\/[^.\/]+\.[^\/]+$/i', $row['url'])){
					// $archive->addFromString($row['contents'], $row['url']);
					$archive->addFromString($contents, $row['url']);
				}
				elseif (preg_match('/rss/i', $row['url'])) {
					// $archive->addFromString($row['contents'], $row['url'] . '/index.xml');
					$archive->addFromString($contents, $row['url'] . '/index.xml');
				}
				else {
					// $archive->addFromString($row['contents'], $row['url'] . '/' . $config['index-file-name']);
					$archive->addFromString($contents, $row['url'] . '/' . $config['index-file-name']);
				}		
			}

			if(is_array($force_includes) && !empty($force_includes)){
				foreach($force_includes as $item){
					
					$item = '/' . trim($item, '/');
					
					$path = DOCROOT . $item;
					
					if(!file_exists($path) && !is_dir($path)) continue;
										
					if(is_dir($path)) $archive->addDirectory($path, DOCROOT, ArchiveZip::IGNORE_HIDDEN);
					else $archive->addFromFile($path, $item);					
					
				}
			}

			return $archive->save($destination);

		}
		
		public static function isPageContainsError($string){
			return (stripos($string, 'Symphony-Error-Type') !== false);
		}
		
		public static function getStatusFromHeaders($string){
			preg_match('/^HTTP\/[^\s]+\s*(\d+)/i', $string, $matches);
			return $matches[1];
		}
		
		public static function crawl($seed, array $ignore=array()){			
			Symphony::Database()->flush();
			Symphony::Database()->flushLog();
			
			$start = precision_timer();
			
			$self_page_contents = ($is_file ? file_get_contents(DOCROOT . $seed) : self::render($seed));
			
			$status = self::getStatusFromHeaders($self_page_contents[0]);
			
			if($status != 404 && self::isPageContainsError($self_page_contents[0])) $status = '999';

			// $contents = MySQL::cleanValue($self_page_contents[1]);
			$contents = urlencode($self_page_contents[1]);

			$time = precision_timer('stop', $start);

			$sql = "INSERT INTO `".self::TABLE."` 
			 		VALUES (NULL, '$seed', NOW(), '$time', '$contents', '$status') 
			 		ON DUPLICATE KEY UPDATE `time_to_index` = '$time', `last_indexed` = NOW(), `contents` = '$contents', `status` = '$status'";

			Symphony::Database()->query('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
			Symphony::Database()->query($sql);
			
			$self_page_links = self::parseForLinks($self_page_contents[1], $ignore);

			if(empty($self_page_links)) return;
			
			$ignore = array_merge($ignore, $self_page_links);

			$arrayObject = new ArrayObject($self_page_links);
			$Iterator = $arrayObject->getIterator();
			
			$bits = parse_url(URL);
			
			$root_path = str_replace($bits['host'] . (!empty($bits['port']) ? ':'.$bits['port'] : NULL), '', DOMAIN);
			
			$new_links = array();
			
			while($Iterator->valid()) {
				
				$bits = parse_url($Iterator->current());
				
				$seed = str_replace($root_path, '', $bits['path']);
				
				$sql = "SELECT `id` FROM `".self::TABLE."` WHERE `url` = '$seed' LIMIT 1";
				
				if(!preg_match('/^\/symphony\//i', $seed) && !Symphony::Database()->fetchVar('id', 0, $sql))
					self::crawl($seed, $ignore, $table);
				
				$Iterator->next();
			}
			
		}
		
		public static function parseForLinks($string, array $ignore=array()){
			
			preg_match_all('/(href|src)="([^"#]+)/i', $string, $matches);
			
			$list = array();

			$matches = General::array_remove_duplicates($matches[2]);
			
			foreach($matches as $url){
				
				$bits = parse_url($url);
				
				if($bits['path'] == $url){
					if($url{0} == '/') $url = URL . $url;
					else continue;
				}
				
				if('?' . $bits['query'] == $url) continue;
				elseif(in_array($url, $ignore)) continue;
				elseif($url == URL || substr($url, 0, strlen(URL)) != URL) continue;
						
				$list[] = $url;
						
			}
			
			return $list;
			
		}
		
	}


?>