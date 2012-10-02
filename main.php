#!/usr/bin/php
<?php
	## Install repos :: apt-get install wget id3 php5

	// Define the base of the wget command and what a tab is
	$wgetBase = 'wget -c -q -U "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.89 Safari/537.1" -O ';
	define('TAB', "\t");

	// Kick out credits
	echo 'Newgrounds Audio Portal Downloader',PHP_EOL,
		'By GingerPaul55',PHP_EOL;

	// Prepare usage
	$file = $_SERVER['argv'];

	// Tidy up
	unset($_SERVER['argv'][0]);

	// No user? - Show usage
	if ($_SERVER['argc'] < 2) {
		echo 'Usage: ./', $file[0], ' artist-username-1 artist-username-2',PHP_EOL;
		die(1);
	}

	// Load genre ids
	$sys = 'id3 -L';
	$code = false;
	$lines = array();
	$data = exec($sys, $lines, $code);
	$id3GenreCodes = array();
	foreach ($lines as $line) {
		$parts = explode(':', $line);
		$id3GenreCodes[strtolower(trim($parts[1]))] = (int)$parts[0];
	}

	// rename
	$usernames = &$_SERVER['argv'];

	// Lets get jiggy
	foreach ($usernames as $username) {

		echo 'Downloading audio tracks for ',$username, PHP_EOL;

		// Tidy up username for use with NG
		$usernameFiltered = str_replace(array('/', '.', '~'), '_', strtolower(trim($username)));

		// Download the index file
		$data = file_get_contents('http://'.$usernameFiltered.'.newgrounds.com/audio/');

		// Validate that we did download a file and that it wasn't an error
		if (strlen($data) < 200 || strpos($data, '<title>Newgrounds - Error</title>') !== false) {
			echo 'Unable to view tracks for "',$username,'"',PHP_EOL;
			continue;
		}

		$matches = array();
		preg_match('/<title>([^<]*)\'s Audio<\/title>/', $data, $matches);
		if (isset($matches[1])) {
			$username = $matches[1];
		}

		echo TAB,'Preparing to download files for ',$username,PHP_EOL;
		if (!is_dir($usernameFiltered)) {
			mkdir('downloads/'.$usernameFiltered);
		}

		// Check for no submissions
//		if (strpos('does not have any audio submission', $data) === false) {
//			echo 'User "',$username,'" does not have any audio submissions.',PHP_EOL;
//			continue;
//		}

		// Filter out the header
		$data = substr($data, strpos($data, '<div class="fatcol">'));

		// Filter out the footer
		$data = substr($data, 0, strpos($data, '<br style="clear: both" />'));

		// Now remove whitespace and new lines
		$count = 0;
		do {
			$data = str_replace(array(PHP_EOL, '  ', "\t"), '', $data, $count);
		} while ($count > 0);

		$sections = explode('</h2>', $data);

		preg_match('/<h2 class="audio">(\d{4}) Submissions/', $sections[0], $year);
		if (isset($year)) {
			$year = $year[1];
		}

		unset($sections[0], $data);

		$d = 1;
		foreach ($sections as $section) {

			echo TAB,TAB,'Downloading tracks uploaded in ',$year,PHP_EOL;

			// Now look for our links
			$matches = array();
			$preg = preg_match_all('/<a href="http:\/\/www.newgrounds.com\/audio\/listen\/(\d*)">([^<]*)<\/a><\/td><td>([^<]*)/', $section, $matches);

			$t = count($matches[1]);
			$files = array();
			$i = 0;
			while ($i < $t) {
				$files[$i] = array(
						'lid' => $i + 1,					// Local ID (Track ID)
						'ngid' => (int)$matches[1][$i],				// Newgrounds ID
						'name' => html_entity_decode($matches[2][$i]),		// Song Name
						'type' => $matches[3][$i],				// Song Type
						'disc' => $d,						// Disc number
						'year' => $year,					// Newgrounds Year
						'file' => 'downloads/'.$usernameFiltered.'/'.$d.' - '.$i.' - '.str_replace(array('/', '.', '~'), '_',html_entity_decode($matches[2][$i])).'.mp3'	// User - Disc - Track.mp3
					);
				echo TAB,TAB,TAB,'Working on Track ', $i + 1, ' of ', $t, ' (',$files[$i]['name'],')...',PHP_EOL;
				$code = false;
				$lines= array();
				$sys = $wgetBase.escapeshellarg($files[$i]['file']).' '.escapeshellarg('http://www.newgrounds.com/audio/download/'.$files[$i]['ngid']);
				$line = exec($sys, $lines, $code);
				if ($code !== 0) {
					echo TAB,TAB,TAB,'Failed to download file', PHP_EOL;
					continue;
				}
				$sys = 'id3';
				$sys.= ' -A '.escapeshellarg('Newgrounds Audio Portal - '.$username);
				$sys.= ' -t '.escapeshellarg($files[$i]['name']);
				$sys.= ' -T '.escapeshellarg($files[$i]['lid']);
				$sys.= ' -a '.escapeshellarg($username);
				$sys.= ' -y '.escapeshellarg($files[$i]['year']);
				$sys.= ' -c '.escapeshellarg('Downloaded using GingerPauls NG batch downloader');
				if (isset($id3GenreCodes[$files[$i]['type']])) {
					$sys.= ' -g '.escapeshellarg($id3GenreCodes[$files[$i]['type']]);
				} else {
					$sys.= ' -g 12';
				}
				$sys.= ' '.escapeshellarg($files[$i]['file']);
				$code = false;
				$lines = array();
				$data = exec($sys, $lines, $code);

				++$i;
			}

			preg_match('/<h2 class="audio">(\d{4}) Submissions/', $section, $year);
			if (isset($year[1])) {
				$year = $year[1];
			}
			++$d;
		}
		echo TAB,'Completed processing for ',$username,PHP_EOL;
		unset($year);
	}
?>
