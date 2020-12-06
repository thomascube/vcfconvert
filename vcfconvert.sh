#!/usr/bin/env php -qC 
<?php

/*
 +-----------------------------------------------------------------------+
 | Commandline vCard converter                                           |
 | Version 0.9.0                                                         |
 |                                                                       |
 | Copyright (C) 2006-2013, Thomas Bruederli - Switzerland               |
 | Licensed under the GNU GPL                                            |
 |                                                                       |
 | Type './vcfconvert.sh help' for usage information                     |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <thomas@brotherli.ch>                        |
 +-----------------------------------------------------------------------+

*/

@ini_set('error_reporting', E_ALL &~ E_NOTICE);

require_once('vcard_convert.php');
require_once('utils.php');

/**
 * Parse commandline arguments into a hash array
 */
function get_args()
{
	$args = array();
	for ($i=1; $i<count($_SERVER['argv']); $i++)
	{
		$arg = $_SERVER['argv'][$i];
		if ($arg[0] == '-' && $arg[1] != '-')
		{
			for ($j=1; $j < strlen($arg); $j++)
			{
				$key = $arg[$j];
				$value = $_SERVER['argv'][$i+1]{0} != '-' ? preg_replace(array('/^["\']/', '/["\']$/'), '', $_SERVER['argv'][++$i]) : true;
				$args[$key] = $value;
			}
		}
		else
			$args[] = $arg;
	}

	return $args;
}

// read commandline arguments
$opt = get_args();
$usage = <<<EOF
Usage: vcfconvert.sh [-hilmpv] [-d delimiter] [-c utf-8] [-b identifier] [-o output_file] -f format <file>
  -f Target format (ldif,ldap,csv,gmail,libdlusb,fritzbox,img)
  -b LDAP identifier added to dn:
  -l Generate just a list of DN objects (only works with -b)
  -o Output file (write to stdout by default)
  -d CSV col delimiter
  -h Include header line in CSV output
  -i Convert CSV output to ISO-8859-1 encoding (deprecated, use -c instead)
  -c Character encoding for CSV output
  -n Line endings for CSV output: 'n', 'r' or 'rn'
  -m Only convert cards with an e-mail address
  -p Only convert cards with phone numbers
  -v Verbose output

EOF;

// show help
if ($opt[0] == 'help')
	die($usage);

// read arguments
$file = array_pop($opt);
$format = $opt['f'] ? $opt['f'] : 'ldif';

if (empty($file))
	die("Not enough arguments!\n$usage");

// instantiate a parser object
$conv = new vcard_convert(array('mailonly' => isset($opt['m']), 'phoneonly' => isset($opt['p'])));

// parse a vCard file
if ($conv->fromFile($file))
{
	if (isset($opt['v']))
		echo "Detected $conv->file_charset encoding\n";
	if (isset($opt['v']) && isset($opt['m']))
		echo "Only convert vCards with an e-mail address\n";
		
		if ($format == 'ldif')
			$out = $conv->toLdif();

		else if ($format == 'ldap')
			$out = $conv->toLdif($opt['b'], isset($opt['l']) ? 'dn' : null);

		else if ($format == 'gmail')
			$out = $conv->toGmail();

		else if ($format == 'libdlusb')
			$out = $conv->toLibdlusb();

		else if ($format == 'fritzbox')
			$out = $conv->toFritzBox();
			
		else if ($format == 'csv')
		{
			if (!isset($opt['c']) && isset($opt['i']))
				$opt['c'] = 'ISO-8859-1';
			$delimiter = isset($opt['d']) ? ($opt['d']=='\t' || $opt['d']=='tab' ? "\t" : $opt['d']) : ";";
			$out = $conv->toCSV($delimiter, isset($opt['h']), isset($opt['c']) ? strtoupper($opt['c']) : null, $opt['n']);
			
			if (isset($opt['v']) && isset($opt['c']))
				echo "Converting output to " . strtoupper($opt['c']) . PHP_EOL;
		}
		else if ($format == 'img')
			$out = $conv->toImages('tmp');

		else
			die("Unknown output format\n");
		
		// write to output file
		if ($opt['o'])
		{
			if ($fp = @fopen($opt['o'], 'w'))
			{
				fwrite($fp, $out);
				fclose($fp);
				echo "Wrote ".$conv->export_count." cards to $opt[o]\n";
			}
			else
				die("Cannot write to $opt[o]; permission denied\n");
		}
		else
			echo $out;
}
else
	echo "Cannot parse $file\n";


?>
