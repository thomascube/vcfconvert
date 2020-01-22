<?php

/*
 +-----------------------------------------------------------------------+
 | vCard to LDIF/CSV Converter                                           |
 | Version 0.9.0                                                         |
 |                                                                       |
 | Copyright (C) 2006-2013, Thomas Bruederli - Switzerland               |
 | Licensed under the GNU GPL                                            |
 |                                                                       |
 | Redistribution and use in source and binary forms, with or without    |
 | modification, are permitted provided that the following conditions    |
 | are met:                                                              |
 |                                                                       |
 | o Redistributions of source code must retain the above copyright      |
 |   notice, this list of conditions and the following disclaimer.       |
 | o Redistributions in binary form must reproduce the above copyright   |
 |   notice, this list of conditions and the following disclaimer in the |
 |   documentation and/or other materials provided with the distribution.|
 | o The names of the authors may not be used to endorse or promote      |
 |   products derived from this software without specific prior written  |
 |   permission.                                                         |
 |                                                                       |
 | THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS   |
 | "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT     |
 | LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR |
 | A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT  |
 | OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, |
 | SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT      |
 | LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, |
 | DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY |
 | THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT   |
 | (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE |
 | OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.  |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <thomas@brotherli.ch>                        |
 +-----------------------------------------------------------------------+

*/

// suppress php notices
@ini_set('error_reporting', E_ALL&~E_NOTICE);

// include the converter class file
require_once('vcard_convert.php');
require_once('utils.php');


if (!empty($_FILES['_vcards']))
{
	// instantiate a parser object
	$conv = new vcard_convert(array(
		'mailonly' => !empty($_POST['_mailonly']),
		'phoneonly' => !empty($_POST['_phoneonly']),
		'accesscode' => preg_replace('/[^1-9]/', '', $_POST['_accesscode']),
	));
	
	// check for errors
	if ($err = $_FILES['_vcards']['error'])
	{
		$GLOBALS['error_msg'] = ($err == UPLOAD_ERR_INI_SIZE || $err == UPLOAD_ERR_FORM_SIZE) ?
			"The uploaded file was too big! Maximum file size allowed: ".show_bytes(parse_bytes(ini_get('upload_max_filesize'))) :
			"Upload failed, please try again";
	}
	// parse the vCard file
	else if ($conv->fromFile($_FILES['_vcards']['tmp_name']))
	{
		$ext = $_POST['_format'] == 'gmail' ? 'csv' : ($_POST['_format'] == 'img' ? 'zip' : $_POST['_format']);
		$fname = asciiwords(preg_replace('/\.[a-z]+$/i', '', $_FILES['_vcards']['name']));

		header(sprintf('Content-Type: text/%s', $ext));
		header(sprintf('Content-Disposition: attachment; filename="%s.%s"', $fname, $ext));

		if ($_POST['_format'] == 'ldif')
		{
			print $conv->toLdif();
			exit;
		}
		else if ($_POST['_format'] == 'ldap')
		{
			// Clean the input dn modifier from dangerous chars
			$dnID = substr(preg_replace('/[^\da-z=,_ -]/i', '', $_POST['_dn']), 0, 255);
			print $conv->toLdif($dnID ? $dnID : "", null, $_POST['_encoding']);
			exit;
		}
		else if ($_POST['_format'] == 'gmail')
		{
			print $conv->toGmail();
			exit;
		}	
		else if ($_POST['_format'] == 'fritzbox')
		{
			print $conv->toFritzBox();
			exit;
		}	
		else if ($_POST['_format'] == 'csv')
		{
			$header = $_POST['_header'] === '1' ? true : false;
			$delimiter = $_POST['_delimiter'] == 'tab' ? "\t" : $_POST['_delimiter'];
			print $conv->toCSV($delimiter, $header, $_POST['_encoding'], $_POST['_newlines']);
			exit;
		}
		// extract all images from the vcard file
		else if ($_POST['_format'] == 'img')
		{
			mkdir($tmpdir = __DIR__ . '/tmp/'.md5(mt_rand())); // Diretory safe naming
			if ($conv->toImages($tmpdir))
			{
				shell_exec('cd ' . escapeshellarg($tmpdir) . "; zip $fname *");
				$zipfile = "$tmpdir/$fname.zip";
			}
			if ($zipfile && is_readable($zipfile))
			{
				header('Content-Type: application/zip', true);
				readfile($zipfile);
				$success = true;
			}
			else
			{
				$GLOBALS['error_msg'] = "No images were found in this file.";
				header('Content-Type: text/html', true);
				header('Content-Disposition: inline', true);
			}

			shell_exec('rm -rf '.escapeshellarg($tmpdir));
			
			if ($success)
				exit;
		}
	}
	else
		$GLOBALS['error_msg'] = "Could not parse vCard file. Either it is empty or of a format not supported.";
}


include('page.html');
?>
