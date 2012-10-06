Newgrounds Audio Fetcher
=
Downloads all the songs by an artists in one line.

Usage
-
Usage: ./main.php [options] artist-username-1 artist-username-2 ...

  - Option,          GNU long option,     Meaning
  -  -h,             --help,              Display this help message
  -  -l,             --limit,             Limit the total number of MP3 downloads
  -  -y,             --year,              Limit the downloads to this year
  -  -s,             --simulate,          Don't save anything

Example: ./main.php --year 2012 f-777 waterflame dimrain47

Requirements
-
 - Wget
 - PHP
 - ID3 or ID3v2

Debain one-liner installer
`sudo apt-get install php5 wget id3v2`

Improvements
-
In future versions it would be nice to remove the need for wget. At the moment it's used to send a custom user agent and to download the album art in a thread.
