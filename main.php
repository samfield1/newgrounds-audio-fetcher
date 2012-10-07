#!/usr/bin/php
<?php
	## Install :: apt-get install id3v2 php5 php5-curl

	// Define defaults
	$id3 = 'id3v2';		// Your id3 tagging program
	define('TAB', "\t");	// A tab
	define('cReturn', "\r");// Return to current line start
	$targetYear = false;	// The year we're going to download or false for all
	$downloadLimit = false; // Limit the total downloads
	$simulationMode = false;// If true we don't save anything
	$downloadText = '';	// Download text

	$i = 0;
	function downloadFileCallback ($downloadTotal, $downloadCompleted, $uploadTotal, $uploadCompleted) {
		global $downloadText;
		if ($downloadTotal === 0) {
			echo cReturn, $downloadText, 'Starting...';
		} else {
			echo cReturn, $downloadText, round(($downloadCompleted / $downloadTotal) * 100, 2), '%           ';
		}
	}

	// Our download function
	function downloadFile($url = false, $target = false, $text = false, $completed = false, $failed = false) {
		if (!is_string($url) || !is_string($target)) {
			return false; // Somethings wrong
		}

		global $downloadText;
		if (is_string($target)) {
			$downloadText = $text.TAB;
		} else {
			$downloadText = '';
		}

		if (!is_string($completed)) {
			$completed = 'Completed!'.PHP_EOL;
		}

		if (!is_string($failed)) {
			$failed = 'Failed!'.PHP_EOL;
		}

		$target = fopen($target, 'w');
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);		// 2 seconds
		curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 1200);	// 20 mins
		curl_setopt($ch, CURLOPT_TIMEOUT, 600);			// 10 mins per download max
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.89 Safari/537.1");
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);		// Follow redirects
		curl_setopt($ch, CURLOPT_FILE, $target);		// Write data to this file
		curl_setopt($ch, CURLOPT_NOPROGRESS, false);		// Make sure we can fire progress function
		curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, 'downloadFileCallback'); // This is progress functions
		curl_setopt($ch, CURLOPT_BUFFERSIZE, 153600);		// 150kb, lower = more updates
		$result = curl_exec($ch);
		curl_close($ch);
		fclose($target);
		if ($result) {
			echo cReturn, $downloadText, $completed;
		} else { // Bugger
			echo cReturn, $downloadText, $failed;
		}
		return $result;
	}

	// Kick out credits
	echo 'Newgrounds Audio Portal Downloader',PHP_EOL,
		'By GingerPaul55',PHP_EOL;

	// Prepare usage
	$file = current($_SERVER['argv']);

	// Tidy up
	unset($_SERVER['argv'][0]);

	// See what options are being set via command line flags
	$shortopts = "y:l:hs";	// Year & Limit
	$longopts  = array(
		"year:",	// Year
		"limit:",	// Limit
		"help",		// Help/usage
		"simulate",	// SimulationMode
	);
	$options = getopt($shortopts, $longopts);

	// Loop though any flags set
	foreach ($options as $option => $value) {
		if ($option == 'y' || $option == 'year') {
			$targetYear = (int)$value; // Update the year
			if ($targetYear > date('Y') || $targetYear < 1995) {
				echo 'The Year (', $targetYear, ') cannot be used because must be between 1995 and ', date('Y'), PHP_EOL;
				die(1);
			}
			array_splice($_SERVER['argv'], 0, 2);
		} elseif($option == 'l' || $option == 'limit') {
			$downloadLimit = (int)$value; // Update the max downloads
			if ($downloadLimit < 1) {
				echo 'The limit (', $value, ') cannot be used because it must be 1 or higher', PHP_EOL;
				die(1);
			}
			array_splice($_SERVER['argv'], 0, 2);
		} elseif($option == 'h' || $option == 'help') {
			$_SERVER['argv'] = array(); // reset everything
			break; // No more options help overrides everything
		} elseif($option == 'simulation' || $option == 's') {
			$simulationMode = true;
			array_splice($_SERVER['argv'], 0, 1);
		} else {
			echo 'Unknown option (',$option,')',PHP_EOL;
			die(1);
		}
	}

	// No users? - Show usage
	if (count($_SERVER['argv']) === 0) {
		echo 'Usage: ', $file, ' [options] artist-username-1 artist-username-2 ...', PHP_EOL, PHP_EOL;
		echo str_pad('Option', 16, ' '), str_pad('GNU long option', 20, ' '), 'Meaning',PHP_EOL;
		echo str_pad(' -h', 16, ' '), str_pad('--help', 20, ' '), 'Display this help message', PHP_EOL;
		echo str_pad(' -l', 16, ' '), str_pad('--limit', 20, ' '), 'Limit the total number of MP3 downloads', PHP_EOL;
		echo str_pad(' -y', 16, ' '), str_pad('--year', 20, ' '), 'Limit the downloads to this year', PHP_EOL;
		echo str_pad(' -s', 16, ' '), str_pad('--simulate', 20, ' '), 'Don\'t save anything', PHP_EOL;
		die(0);
	}

	// Load genre ids
	$sys = $id3.' -L';
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

	// Count all the files we download
	$fileCounter = 0;

	// Lets get jiggy
	foreach ($usernames as &$username) {

		// Fix common mistakes
		$parts = explode('://', $username);
		$username = isset($parts[1]) ? $parts[1] : $parts[0];
		$parts = explode('.newgrounds.com', $username);
		$username = $parts[0];

		echo 'Preparing downloads for ',$username, PHP_EOL;

		// Clean up the username
		$usernameFiltered = trim(strtolower($username));

		// Is this a valid username?
		if(!preg_match("/^[a-z0-9][a-z0-9\-]+[a-z0-9]$/", $usernameFiltered)) {
			echo TAB, 'Username is invalid: ',$usernameFiltered,PHP_EOL;
			continue;
		}

		// Download the index file
		$data = file_get_contents('http://'.$usernameFiltered.'.newgrounds.com/audio/');

		// Validate that we did download a file and that it wasn't an error
		if (strlen($data) < 200 || strpos($data, '<title>Newgrounds - Error</title>') !== false) {
			echo TAB,'Unable to view tracks for "',$username,'"',PHP_EOL;
			continue;
		}

		$matches = array();
		preg_match('/<title>([^<]*)\'s Audio<\/title>/', $data, $matches);
		if (isset($matches[1])) {
			$username = $matches[1];
		}

		echo TAB,'Downloading files for ',$username,PHP_EOL;

		// Check for no submissions
		if (strpos($data, 'does not have any audio submission') !== false) {
			echo TAB,'User "',$username,'" does not have any audio submissions.',PHP_EOL;
			continue;
		}

		// Store the mp3s in a new folder
		$downloadDir = 'downloads/'.$usernameFiltered.'/';
		if (!is_dir($downloadDir) && $simulationMode === false) {
			mkdir($downloadDir);
		}

		// Find the artist artwork
		$matches = array();
		preg_match('/http:\/\/uimg.ngfiles.com\/icons\/(\d*)\/(\d*)_small.jpg/', $data, $matches);
		if (isset($matches[1])) {
			echo TAB,'Downloading Album Art';
			if ($simulationMode) {
				echo TAB, 'Simulated!',PHP_EOL;
			} else {
				$artwork = 'http://uimg.ngfiles.com/profile/'.$matches[1].'/'.$matches[2].'.jpg';
				downloadFile($artwork, $downloadDir.'album-art.jpg', TAB.'Downloading Album Art... ');
			}
		}

		// Filter out the header
		$data = substr($data, strpos($data, '<div class="fatcol">'));

		// Filter out the footer
		$data = substr($data, 0, strpos($data, '<br style="clear: both" />'));

		// Now remove whitespace and new lines
		$count = false;
		do {
			$data = str_replace(array(PHP_EOL, '  ', TAB), '', $data, $count);
		} while ($count > 0);

		$sections = explode('</h2>', $data);

		// Find the first year
		preg_match('/<h2 class="audio">(\d{4}) Submissions/', $sections[0], $year);
		if (isset($year)) {
			$year = (int)$year[1];
		}

		// Clean up
		unset($sections[0], $data);

		$d = 1; // Disc / Year Counter
		foreach ($sections as $section) {
			if ($targetYear !== false && $year !== $targetYear) {
				echo TAB,TAB, 'Skipping year (', $year, ') because it doesn\'t match our target year (', $targetYear, ')',PHP_EOL;
			} else {
				echo TAB,TAB,'Downloading tracks uploaded in ', $year, PHP_EOL;

				// Now look for our links
				$matches = array();
				$preg = preg_match_all('/<a href="http:\/\/www.newgrounds.com\/audio\/listen\/(\d*)">([^<]*)<\/a><\/td><td>([^<]*)/', $section, $matches);

				$t = count($matches[1]);
				$files = array();
				$i = 0;
				// Foreach audio URL download an mp3
				while ($i < $t) {
					// Build file locations
					$file = $downloadDir;		// Start in download dir
					$file.= $d . '-' . ($i + 1);	// Add the Disc and track numbers
					$file.= ' '.str_replace(array('/', '.', '~', '\'', '"', '?', "\x00", '\\'), '_',html_entity_decode($matches[2][$i])).'.mp3'; // add friendly name

					// Put in an array so future devs can easily make it threadable
					$files[$i] = array(
							'lid' => $i + 1,				// Local ID (Track ID)
							'ngid' => (int)$matches[1][$i],			// Newgrounds ID
							'name' => html_entity_decode($matches[2][$i]),	// Song Name
							'type' => $matches[3][$i],			// Song Type
							'disc' => $d,					// Disc number
							'year' => $year,				// Newgrounds Year
							'file' => $file				// File (Disc-Track Title)
						);
					$text = TAB.TAB.TAB.str_pad('Working on Track '. ($i + 1). ' of '. $t. ' ('.$files[$i]['name'].')...', 56, ' ');
					echo $text;

					// Simulation mode
					if ($simulationMode) {
						echo 'Simulated!',PHP_EOL;
						++$i;
						continue;
					}

					// Download the file
					if(!downloadFile('http://www.newgrounds.com/audio/download/'.$files[$i]['ngid'], $files[$i]['file'], $text, '')) {
						++$i;
						continue;
					}

					// Change the Meta data
					$sys = $id3; // Base Command
					$sys.= ' -A '.escapeshellarg('Newgrounds Audio Portal - '.$username);			// Album
					$sys.= ' -t '.escapeshellarg($files[$i]['name']);					// Track Name

					// if we're using id3v2
					if (strpos($id3,'2') !== false) {
						$sys.= ' -T '.escapeshellarg($files[$i]['lid'].'/'.$t);				// Track/Total Tracks
						$sys.= ' --TPOS '.escapeshellarg($files[$i]['disc']);				// Include disc number
					} else {
						$sys.= ' -T '.escapeshellarg($files[$i]['lid']);				// Track
					}
					$sys.= ' -a '.escapeshellarg($username);						// Artist
					$sys.= ' -y '.escapeshellarg($files[$i]['year']);					// Year
					$sys.= ' -c '.escapeshellarg('Downloaded using GingerPauls NG batch downloader');	// Comments
					if (isset($id3GenreCodes[$files[$i]['type']])) { 		// If we have the ID of the Genre
						$sys.= ' -g '.escapeshellarg($id3GenreCodes[$files[$i]['type']]); // use it
					} else {
						$sys.= ' -g 12'; // else use other
					}
					$sys.= ' '.escapeshellarg($files[$i]['file']); // The audio file we're changing
					$code = false;
					$lines = array();
					$data = exec($sys, $lines, $code);
					if ($code !== 0 || count($lines) > 0) {
						echo TAB, TAB, TAB, 'Possible error with command: ', $sys;
					}

					echo 'Completed!',PHP_EOL;

					// Next URL/file
					++$i;
					++$fileCounter;

					if ($downloadLimit === $fileCounter) {
						echo 'Download limit (',$downloadLimit,') reached!', PHP_EOL;
						break 3;
					}
				}
			}
			// Find out what the next year the user uploaded a file in
			preg_match('/<h2 class="audio">(\d{4}) Submissions/', $section, $year);
			if (isset($year[1])) {
				$year = (int)$year[1];
			}

			// Another year = Another disc
			++$d;
		}

		// Finished downloading everything for this user.
		echo TAB, 'Completed processing for ', $username, PHP_EOL;

		// Clean up
		unset($year);
	}

	echo 'Finished downloading ', $fileCounter, ' files!',PHP_EOL;
?>
