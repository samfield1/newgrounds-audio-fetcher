#!/usr/bin/php
<?php
	## Install :: apt-get install wget id3v2 php5
	## If you don't want to use wget IE you just wanna do a pure php download then just find and replacae wgetBase with your patch

	// Define defaults
	$wgetBase = 'wget -c -q -U "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.89 Safari/537.1" -O '; // wget base cmd
	$id3 = 'id3v2'; // Your id3 tagging program
	define('TAB', "\t"); // A tab
	$targetYear = false;	// The year we're going to download or false for all
	$downloadLimit = false; // Limit the total downloads

	// Kick out credits
	echo 'Newgrounds Audio Portal Downloader',PHP_EOL,
		'By GingerPaul55',PHP_EOL;

	// Prepare usage
	$file = current($_SERVER['argv']);

	// Tidy up
	unset($_SERVER['argv'][0]);

	// See what options are being set via command line flags
	$shortopts = "y:l:h";	// Year & Limit
	$longopts  = array(
		"year:",	// Year
		"limit:",	// Limit
		"help"		// Help/useage
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
		if (!is_dir($downloadDir)) {
			mkdir($downloadDir);
		}

		// Find the artist artwork
		$matches = array();
		preg_match('/http:\/\/uimg.ngfiles.com\/icons\/(\d*)\/(\d*)_small.jpg/', $data, $matches);
		if (isset($matches[1])) {
			echo TAB,'Downloading Album Art',PHP_EOL;
			$artwork = 'http://uimg.ngfiles.com/profile/'.$matches[1].'/'.$matches[2].'.jpg';
			$sys = $wgetBase.escapeshellarg($downloadDir.'album-art.jpg').' '.escapeshellarg($artwork).' &'; // Fork it
			exec($sys, $lines, $code);
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
					echo TAB,TAB,TAB,str_pad('Working on Track '. ($i + 1). ' of '. $t. ' ('.$files[$i]['name'].')...', 56, ' '),TAB;

					// Download the file
					$code = false;
					$lines= array();
					$sys = $wgetBase.escapeshellarg($files[$i]['file']).' '.escapeshellarg('http://www.newgrounds.com/audio/download/'.$files[$i]['ngid']);
					$line = exec($sys, $lines, $code);
					if ($code !== 0) {
						echo TAB, TAB, TAB, 'Possible error with command: ', $sys;
					}

					// wget returned a non-zero. An error
					if ($code !== 0) {
						echo 'Failed to download file', PHP_EOL;
						++$i;
						continue;
					}

					// Change the Meta data
					$sys = $id3; // Base Command
					$sys.= ' -A '.escapeshellarg('Newgrounds Audio Portal - '.$username);	// Album
					$sys.= ' -t '.escapeshellarg($files[$i]['name']);			// Track Name

					// if we're using id3v2
					if (strpos($id3,'2') !== false) {
						$sys.= ' -T '.escapeshellarg($files[$i]['lid'].'/'.$t);		// Track/Total Tracks
						$sys.= ' --TPOS '.escapeshellarg($files[$i]['disc']);		// Include disc number
					} else {
						$sys.= ' -T '.escapeshellarg($files[$i]['lid']);		// Track
					}
					$sys.= ' -a '.escapeshellarg($username);				// Artist
					$sys.= ' -y '.escapeshellarg($files[$i]['year']);			// Year
					$sys.= ' -c '.escapeshellarg('Downloaded using GingerPauls NG batch downloader'); // Comments
					if (isset($id3GenreCodes[$files[$i]['type']])) { // If we have the ID of the genre
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
