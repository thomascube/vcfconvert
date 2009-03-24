#!/usr/bin/php -qC 
<?php

/*
 +-----------------------------------------------------------------------+
 | Commandline vCard converter                                           |
 | Version 0.8.3                                                         |
 |                                                                       |
 | Copyright (C) 2006-2009, Thomas Bruederli - Switzerland               |
 | Licensed under the GNU GPL                                            |
 |                                                                       |
 | Type './convert help' for usage information                           |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <thomas@brotherli.ch>                        |
 +-----------------------------------------------------------------------+

*/

@ini_set('error_reporting', E_ALL&~E_NOTICE); 

require_once('vcard_convert.php');

/**
 * Parse commandline arguments into a hash array
 */
function get_args()
{
	$args = array();
	for ($i=1; $i<count($_SERVER['argv']); $i++)
	{
		$arg = $_SERVER['argv'][$i];
		if ($arg{0} == '-' && $arg{1} != '-')
		{
			for ($j=1; $j < strlen($arg); $j++)
			{
				$key = $arg{$j};
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
Usage: convert [-himpv] [-d delimiter] [-o output_file] -f format file
  -f Target format (ldif,csv,gmail,libdlusb)
  -o Output file (write to stdout by default)
  -d CSV col delimiter
  -h Include header line in CSV output
  -i Convert CSV output to ISO-8859-1 encoding
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

		else if ($format == 'gmail')
			$out = $conv->toGmail();

		else if ($format == 'libdlusb')
			$out = $conv->toLibdlusb();

		else if ($format == 'fritzbox')
			$out = $conv->toFritzBox();
			
		else if ($format == 'csv')
		{
			$delimiter = $opt['d'] ? ($opt['d']=='\t' || $opt['d']=='tab' ? "\t" : $opt['d']) : ";";
			$out = $conv->toCSV($delimiter, isset($opt['h']), isset($opt['i']) ? 'ISO-8859-1' : null);
			
			if (isset($opt['v']) && isset($opt['i']))
				echo "Converting output to ISO-8859-1\n";
		}
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
