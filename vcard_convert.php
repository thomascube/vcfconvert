<?php

/*
 +-----------------------------------------------------------------------+
 | vCard to LDIF/CSV Converter Class                                     |
 | extends the PEAR Contact_Vcard_Parse Class                            |
 |                                                                       |
 | Copyright (C) 2006-2008, Thomas Bruederli - Switzerland               |
 | Licensed under the GNU GPL                                            |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <thomas@brotherli.ch>                        |
 +-----------------------------------------------------------------------+

*/

// version 1.31 required
require_once('Contact_Vcard_Parse.php');


/**
 * Typedef of a vCard object
 */
class vCard
{
	var $version;
	var $displayname;
	var $surname;
	var $firstname;
	var $middlename;
	var $nickname;
	var $title;
	var $birthday;
	var $organization;
	var $department;
	var $jobtitle;
	var $home = array();
	var $work = array();
	var $email;
	var $email2;
	var $pager;
	var $mobile;
	var $im = array();
	var $notes;
	var $categories;
}


/**
 * vCard to LDIF/CSV Converter Class
 */
class vcard_convert extends Contact_Vcard_Parse
{
	var $parsed = array();
	var $vcards = array();
	var $file_charset = 'ISO-8859-1';
	var $charset = 'ISO-8859-1';
	var $export_count = 0;


	/**
	 * Read a file and parse it
	 *
	 * @override
	 */
	function fromFile($filename, $decode_qp = true)
	{
		if (!filesize($filename) || ($text = $this->fileGetContents($filename)) === false)
			return false;

		// dump to, and get return from, the fromText() method.
		return $this->fromText($text, $decode_qp);
	}
	
	/**
	 * Parse a given string for vCards
	 *
	 * @override
	 */
	function fromText($text, $decode_qp = true)
	{
		if ($encoding = vcard_convert::get_charset($text))
			$this->charset = $this->file_charset = $encoding;

		// convert document to UTF-8
		if ($this->charset != 'UTF-8' && $this->charset != 'ISO-8859-1')
		{
			$text = $this->utf8_convert($text);
			$this->charset = 'UTF-8';
		}

		$this->parsed = parent::fromText($text, $decode_qp);
		if (!empty($this->parsed))
		{
			$this->normalize();
			return count($this->cards);
		}
		else
			return false;
	}
	
	
	/**
	 * Convert the abstract vCard structure into address objects
	 *
	 * @access private
	 */
	function normalize()
	{
		$this->cards = array();
		foreach($this->parsed as $i => $card)
		{
			$vcard = new vCard;
			$vcard->version = (float)$card['VERSION'][0]['value'][0][0];

			// extract names
			$names = $card['N'][0]['value'];
			$vcard->surname = trim($names[0][0]);
			$vcard->firstname = trim($names[1][0]);
			$vcard->middlename = trim($names[2][0]);
			$vcard->title = trim($names[3][0]);
			
			if (empty($vcard->title) && isset($card['TITLE']))
				$vcard->title = trim($card['TITLE'][0]['value'][0][0]);

			$vcard->displayname = isset($card['FN']) ? trim($card['FN'][0]['value'][0][0]) : '';
			$vcard->nickname    = isset($card['NICKNAME']) ? trim($card['NICKNAME'][0]['value'][0][0]) : '';

			// extract notes
			$vcard->notes = isset($card['NOTE']) ? ltrim($card['NOTE'][0]['value'][0][0]) : '';

			// extract birthday
			if(is_array($card['BDAY']))
			{
				$temp = preg_replace('/[\-\.\/]/', '', $card['BDAY'][0]['value'][0][0]);
				$vcard->birthday = array(
					'y' => substr($temp,0,4),
					'm' => substr($temp,4,2),
					'd' => substr($temp,6,2));
			}

			// extract job_title
			if (is_array($card['TITLE']))
				$vcard->jobtitle = $card['TITLE'][0]['value'][0][0];

			// extract org and dep
			if (is_array($card['ORG']) && ($temp = $card['ORG'][0]['value']))
			{
				$vcard->organization = trim($temp[0][0]);
				$vcard->department   = trim($temp[1][0]);
			}
			
			// extract urls
			if (is_array($card['URL']))
				$this->parse_url($card['URL'], $vcard);
			else if (is_array($card['ITEM1.URL']))
				$this->parse_url($card['ITEM1.URL'], $vcard);
			if (is_array($card['ITEM2.URL']))
				$this->parse_url($card['ITEM2.URL'], $vcard);

			// extract addresses
			if (is_array($card['ADR']))
				$this->parse_adr($card['ADR'], $vcard);
			else if (is_array($card['ITEM1.ADR']))   // this is from Apple's Address Book
				$this->parse_adr($card['ITEM1.ADR'], $vcard);
			if (is_array($card['ITEM2.ADR']))   // this is from Apple's Address Book
				$this->parse_adr($card['ITEM2.ADR'], $vcard);

			// extract phones
			if (is_array($card['TEL']))
				$this->parse_tel($card['TEL'], $vcard);
			else if (is_array($card['ITEM1.TEL']))
				$this->parse_tel($card['ITEM1.TEL'], $vcard);  // this is from Apple's Address Book
			if (is_array($card['ITEM2.TEL']))
				$this->parse_tel($card['ITEM2.TEL'], $vcard);  // this is from Apple's Address Book

			// extract mail addresses
			$a_email = array();
			if (is_array($card['EMAIL']))
			{
				$a_email[] = $card['EMAIL'][0]['value'][0][0];
				if (isset($card['EMAIL'][1]))
					$a_email[] = $card['EMAIL'][1]['value'][0][0];
			}
			if (is_array($card['ITEM1.EMAIL']))
			{
				$a_email[] = $card['ITEM1.EMAIL'][0]['value'][0][0];
				if (isset($card['ITEM1.EMAIL'][1]))
					$a_email[] = $card['ITEM1.EMAIL'][1]['value'][0][0];
			}

			if (count($a_email))
				$vcard->email = $a_email[0];
			if (!empty($a_email[1]))
				$vcard->email2 = $a_email[1];
			
			// find IM entries
			if (is_array($card['X-AIM']))
				$vcard->im['aim'] = $card['X-AIM'][0]['value'][0][0];
			if (is_array($card['X-IQC']))
				$vcard->im['icq'] = $card['X-ICQ'][0]['value'][0][0];
			if (is_array($card['X-MSN']))
				$vcard->im['msn'] = $card['X-MSN'][0]['value'][0][0];
			if (is_array($card['X-JABBER']))
				$vcard->im['jabber'] = $card['X-JABBER'][0]['value'][0][0];

			if (is_array($card['PHOTO'][0]))
				$vcard->photo = array('data' => $card['PHOTO'][0]['value'][0][0], 'encoding' => $card['PHOTO'][0]['param']['ENCODING'][0]);

			$vcard->categories = join(',', (array)$card['CATEGORIES'][0]['value'][0]);

			$this->cards[] = $vcard;
			}
		}

	/**
	 * Helper method to parse an URL node
	 *
	 * @access private
	 */
	function parse_url(&$node, &$vcard)
	{
		foreach($node as $url)
		{
			if (empty($url['param']['TYPE'][0]) || in_array_nc("WORK", $url['param']['TYPE']) || in_array_nc("PREF", $url['param']['TYPE']))
				$vcard->work['url'] = $url['value'][0][0];
			if (in_array_nc("HOME", $url['param']['TYPE']))
				$vcard->home['url'] = $url['value'][0][0];
		}
	}

	/**
	 * Helper method to parse an address node
	 *
	 * @access private
	 */
	function parse_adr(&$node, &$vcard)
	{
		foreach($node as $adr)
		{
			if (empty($adr['param']['TYPE'][0]) || in_array_nc("HOME", $adr['param']['TYPE']))
				$home = $adr['value'];
			if (in_array_nc("WORK", $adr['param']['TYPE']))
				$work = $adr['value'];
		}
		
		// values not splitted by Contact_Vcard_Parse if key is like item1.ADR
		if (strstr($home[0][0], ';'))
		{
			$temp = split(';', $home[0][0]);
			$vcard->home += array(
				'addr1' => $temp[2],
				'city' => $temp[3],
				'state' => $temp[4],
				'zipcode' => $temp[5],
				'country' => $temp[6]);
		}
		else if (sizeof($home)>6)
		{
			$vcard->home += array(
				'addr1' => $home[2][0],
				'city' => $home[3][0],
				'state' => $home[4][0],
				'zipcode' => $home[5][0],
				'country' => $home[6][0]);
		}
		
		// values not splitted by Contact_Vcard_Parse if key is like item1.ADR
		if (strstr($work[0][0], ';'))
		{
			$temp = split(';', $work[0][0]);
			$vcard->work += array(
				'office' => $temp[1],
				'addr1' => $temp[2],
				'city' => $temp[3],
				'state' => $temp[4],
				'zipcode' => $temp[5],
				'country' => $temp[6]);
		}
		else if (sizeof($work)>6)
		{
			$vcard->work += array(
				'addr1' => $work[2][0],
				'city' => $work[3][0],
				'state' => $work[4][0],
				'zipcode' => $work[5][0],
				'country' => $work[6][0]);
		}
	}

	/**
	 * Helper method to parse an phone number node
	 *
	 * @access private
	 */
	function parse_tel(&$node, &$vcard)
	{
		foreach($node as $tel)
		{
			if (in_array_nc("PAGER", $tel['param']['TYPE']))
				$vcard->pager = $tel['value'][0][0];
			else if (in_array_nc("CELL", $tel['param']['TYPE']))
				$vcard->mobile = $tel['value'][0][0];
			else if (in_array_nc("HOME", $tel['param']['TYPE']) || in_array_nc("PREF", $tel['param']['TYPE']))
			{
				if (in_array_nc("FAX", $tel['param']['TYPE']))
					$vcard->home['fax'] = $tel['value'][0][0];
				else
					$vcard->home['phone'] = $tel['value'][0][0];
			}
			else if (in_array_nc("WORK", $tel['param']['TYPE']))
			{
				if(in_array_nc("FAX", $tel['param']['TYPE']))
					$vcard->work['fax'] = $tel['value'][0][0];
				else
					$vcard->work['phone'] = $tel['value'][0][0];
			}
		}
	}
	

	/**
	 * Convert the parsed vCard data into CSV format
	 */
	function toCSV($delm="\t", $mailonly=false, $add_title=true, $encoding=null)
		{
		$out = '';
		$this->export_count = 0;

		if ($add_title)
		{
			$out .= 'First Name'.$delm.'Last Name'.$delm.'Display Name'.$delm.'Nickname'.$delm.'E-mail Address'.$delm.'E-mail 2 Address'.$delm;
			$out .= 'Home Phone'.$delm.'Business Phone'.$delm.'Home Fax'.$delm.'Pager'.$delm.'Mobile Phone'.$delm;
			$out .= 'Home Street'.$delm.'Home Address 2'.$delm.'Home City'.$delm.'Home State'.$delm.'Home Postal Code'.$delm.'Home Country'.$delm;
			$out .= 'Business Address'.$delm.'Business Address 2'.$delm.'Business City'.$delm.'Business State'.$delm.'Business Postal Code'.$delm;
			$out .= 'Business Country'.$delm.'Title'.$delm.'Department'.$delm.'Organization'.$delm.'Notes'.$delm.'Birthday'.$delm;
			$out .= 'Web Page'.$delm.'Web Page 2'.$delm.'Categories'."\n";
		}

		foreach ($this->cards as $card)
		{
			if ($mailonly && empty($card->email) && empty($card->email2))
				continue;

			$out .= $this->csv_encode($card->firstname, $delm);
			$out .= $this->csv_encode($card->surname, $delm);
			$out .= $this->csv_encode($card->displayname, $delm);
			$out .= $this->csv_encode($card->nickname, $delm);
			$out .= $this->csv_encode($card->email, $delm);
			$out .= $this->csv_encode($card->email2, $delm);
			$out .= $this->csv_encode($card->home['phone'], $delm);
			$out .= $this->csv_encode($card->work['phone'], $delm);
			$out .= $this->csv_encode($card->home['fax'], $delm);
			$out .= $this->csv_encode($card->pager, $delm);
			$out .= $this->csv_encode($card->mobile, $delm);
			$out .= $this->csv_encode($card->home['addr1'], $delm);
			$out .= $this->csv_encode($card->home['addr2'], $delm);
			$out .= $this->csv_encode($card->home['city'], $delm);
			$out .= $this->csv_encode($card->home['state'], $delm);
			$out .= $this->csv_encode($card->home['zipcode'], $delm);
			$out .= $this->csv_encode($card->home['country'], $delm);
			$out .= $this->csv_encode($card->work['addr1'], $delm);
			$out .= $this->csv_encode($card->work['addr2'], $delm);
			$out .= $this->csv_encode($card->work['city'], $delm);
			$out .= $this->csv_encode($card->work['state'], $delm);
			$out .= $this->csv_encode($card->work['zipcode'], $delm);
			$out .= $this->csv_encode($card->work['country'], $delm);
			$out .= /* $this->csv_encode($card->title, $delm) . */ $delm;
			$out .= $this->csv_encode($card->department, $delm);
			$out .= $this->csv_encode($card->organization, $delm);
			$out .= $this->csv_encode($card->notes, $delm);
			$out .= !empty($card->birthday) ? $this->csv_encode(sprintf('%04d-%02d-%02d 00:00:00', $card->birthday['y'], $card->birthday['m'], $card->birthday['d']), $delm) : $delm;
			$out .= $this->csv_encode($card->work['url'], $delm);
			$out .= $this->csv_encode($card->home['url'], $delm);
			$out .= $this->csv_encode($card->categories, $delm, false);

			$out .= "\n";
			$this->export_count++;
		}

		// convert to ISO-8859-1
		if ($encoding == 'ISO-8859-1' && $this->charset == 'UTF-8' && function_exists('utf8_decode'))
			$out = utf8_decode($out);
		// convert to any other charset
		else if (!empty($encoding) && $encoding !== $this->charset && function_exists('iconv'))
		  $out = iconv($this->charset, $encoding.'//IGNORE', $out);

		return $out;
	}
	
	/**
	 * New GMail export function
	 *
	 * @author Thomas Bruederli
	 * @author Max Plischke <plischke@gmail.com>
	 */
	function toGmail($mailonly=false)
	{
		$delm = ',';
		$this->export_count = 0;
		$out = "Name,E-mail,Notes,Section 1 - Description,Section 1 - Email,".
					 "Section 1 - IM,Section 1 - Phone,Section 1 - Mobile,".
					 "Section 1 - Pager,Section 1 - Fax,Section 1 - Company,".
					 "Section 1 - Title,Section 1 - Other,Section 1 - Address,".
					 "Section 2 - Description,Section 2 - Email,Section 2 - IM,".
					 "Section 2 - Phone,Section 2 - Mobile,Section 2 - Pager,".
					 "Section 2 - Fax,Section 2 - Company,Section 2 - Title,".
					 "Section 2 - Other,Section 2 - Address\n";

		foreach ($this->cards as $card)
		{
			if ($mailonly && empty($card->email) && empty($card->email2))
				continue;

			$home = array($card->home['addr1'], $card->home['city']);
			if ($card->home['state']) $home[] = $card->home['state'];
			if ($card->home['zipcode']) $home[] = $card->home['zipcode'];
			if ($card->home['country']) $home[] = $card->home['country'];

			$work = array($card->work['addr1'], $card->work['city']);
			if ($card->work['state']) $work[] = $card->work['state'];
			if ($card->work['zipcode']) $work[] = $card->work['zipcode'];
			if ($card->work['country']) $work[] = $card->work['country'];
			
			$im = array_values($card->im);

			$out .= $this->csv_encode($card->displayname, $delm);
			$out .= $this->csv_encode($card->email, $delm); // main
			$out .= $this->csv_encode($card->notes, $delm); // Notes

			$out .= $this->csv_encode('Home', $delm);
			$out .= $this->csv_encode('', $delm); // home email ?
			$out .= $this->csv_encode($im[0], $delm); // IM
			$out .= $this->csv_encode($card->home['phone'], $delm);
			$out .= $this->csv_encode($card->mobile, $delm);
			$out .= $this->csv_encode($card->pager, $delm);
			$out .= $this->csv_encode($card->home['fax'], $delm);
			$out .= $this->csv_encode('', $delm); //
			$out .= /* $card['title'] . */ $delm;
			$out .= $this->csv_encode('', $delm); // other
			$out .= $this->csv_encode(join(' ', $home), $delm);

			$out .= $this->csv_encode('Work', $delm);
			$out .= $this->csv_encode($card->email2, $delm); // work email
			$out .= $this->csv_encode($im[1], $delm); // IM
			$out .= $this->csv_encode($card->work['phone'], $delm);
			$out .= $this->csv_encode('', $delm); //
			$out .= $this->csv_encode('', $delm); //
			$out .= $this->csv_encode($card->work['fax'], $delm); // work fax
			$out .= $this->csv_encode($card->organization, $delm);
			$out .= $this->csv_encode($card->jobtitle, $delm); // title
			$out .= $this->csv_encode($card->department, $delm);
			$out .= $this->csv_encode(join(' ', $work), $delm);

			//$out .= $this->csv_encode($card->nick, $delm);
			//$out .= $this->csv_encode($card->home['url'], $delm);
			//$out .= $this->csv_encode($card->work['url'], $delm, FALSE);

			$out .= "\n";
			$this->export_count++;
		}

	return $out;
	}

	/**
	 * Convert the parsed vCard data into libdlusb format
	 *
	 * @author Kevin Clement <donkjunk@softhome.net>
	 */
	function toLibdlusb($mailonly=false)
	{
		$delm="; ";
		$out = '';
		$this->export_count = 0;

		foreach ($this->cards as $card)
		{
			if ($mailonly && empty($card->email) && empty($card->email2))
				continue;

			// a little ugly but this filters out files that only have incompatible data to prevent "blank" files
			if (empty($card->home['phone']) && empty($card->work['phone']) && empty($card->email) && empty($card->mobile))
				continue;

			// having determined there is data that needs exporting this
			// makes certain we don't have holes to save watch memory
			$out .= $this->csv_encode($card->displayname, $delm);
			if ($card->home['phone'] != '')
			{
				$out .= 'Home = ';
				$out .= $this->csv_encode($card->home['phone'], $delm);
			}
			if ($card->work['phone'] != '')
			{
				$out .= 'Work = ';
				$out .= $this->csv_encode($card->work['phone'], $delm);
			}
			if ($card->email != '')
			{
				$out .= 'Email = ';
				$out .= $this->csv_encode($card->email, $delm);
			}
			if($card->mobile != '')
			{
				$out .= 'Mobile = ';
				$out .= $this->csv_encode($card->mobile, $delm);
			}

			$out .= "\n";
			$this->export_count++;
		}

		// convert to ISO-8859-1
		//if ($encoding == 'ISO-8859-1' && $this->charset == 'UTF-8' && function_exists('utf8_decode'))
		//	$out = utf8_decode($out);

		return $out;
	}


	/**
	 * Export cards as Ldif
	 */
	function toLdif($mailonly=false)
	{
		$out = '';
		$this->export_count = 0;

		foreach($this->cards as $card)
		{
			if ($mailonly && empty($card->email) && empty($card->email2))
				continue;

			if (empty($card->displayname))
				$card->displayname = $card->firstname.' '.$card->surname;

			$a_out = array();
			$a_out['dn'] = sprintf("cn=%s,mail=%s", $card->displayname, $card->email);
			$a_out['objectclass'] = array('top', 'person', 'organizationalPerson', 'inetOrgPerson', 'mozillaAbPersonObsolete');

			$a_out['givenName'] = $card->firstname;
			$a_out['sn'] = $card->surname;
			$a_out['cn'] = $card->displayname;
			$a_out['mail'] = $card->email;
			$a_out['modifytimestamp'] = '0Z';

			if ($card->nickname)
				$a_out['mozillaNickname'] = $card->nickname;
			if ($card->email2)
				$a_out['mozillaSecondEmail'] = $card->email2;
			if ($card->home['phone'])
				$a_out['homePhone'] = $card->home['phone'];
			if ($card->mobile)
				$a_out['mobile'] = $card->mobile;
			if ($card->pager)
				$a_out['pager'] = $card->pager;
			if ($card->home['addr1'])
				$a_out['homeStreet'] = $card->home['addr1'];
			if ($card->home['city'])
				$a_out['mozillaHomeLocalityName'] = $card->home['city'];
			if ($card->home['state'])
				$a_out['mozillaHomeState'] = $card->home['state'];
			if ($card->home['zipcode'])
				$a_out['mozillaHomePostalCode'] = $card->home['zipcode'];
			if ($card->home['country'])
				$a_out['mozillaHomeCountryName'] = $card->home['country'];
			if ($card->organization)
				$a_out['o'] = $card->organization;
			if ($card->work['addr1'])
				$a_out['street'] = $card->work['addr1'];
			if ($card->work['city'])
				$a_out['l'] = $card->work['city'];
			if ($card->work['state'])
				$a_out['st'] = $card->work['state'];
			if ($card->work['zipcode'])
				$a_out['postalCode'] = $card->work['zipcode'];
			if ($card->work['country'])
				$a_out['c'] = $card->work['country'];
			if ($card->work['phone'])
				$a_out['telephoneNumber'] = $card->work['phone'];
			if ($card->work['url'])
				$a_out['workurl'] = $card->work['url'];
			if ($card->home['url'])
				$a_out['homeurl'] = $card->home['url'];
			if ($card->notes)
				$a_out['description'] = $card->notes;
			if ($card->birthday)
				$a_out['mozillaCustom1'] = sprintf("%04d-%02d-%02d", $card->birthday['y'], $card->birthday['m'], $card->birthday['d']);

			// compose ldif output
			foreach ($a_out as $key => $val)
			{
				if (is_array($val))
					foreach ($val as $i => $val2)
						$out .= sprintf("%s: %s\n", $key, $this->ldif_encode($val2));
				else
					$out .= sprintf("%s:%s\n", $key, $this->ldif_encode($val));
			}

			$out .= "\n";
			$this->export_count++;
		}

		return $out;
	}

	/**
	 * Export all cards images
	 */
	function toImages($tmpdir)
	{
		$this->export_count = 0;
		
		foreach($this->cards as $card)
		{
			if ($card->photo)
			{
				$fn = asciiwords(strtolower($card->displayname)) . '.jpg';
				if (file_put_contents($tmpdir.'/'.$fn, base64_decode(preg_replace('/\s+/', '', $card->photo['data']))))
					$this->export_count++;
			}
		}

		return $this->export_count;
	}

	/**
	 * Encode one col string for CSV export
	 *
	 * @access private
	 */
	function csv_encode($str, $delm, $add_delm=true)
	{
		if (strpos($str, $delm))
			$str = '"'.$str.'"';
		return preg_replace('/\r?\n/', ' ', $str) . ($add_delm ? $delm : '');
	}
	
	
	/**
	 * Encode one col string for Ldif export
	 *
	 * @access private
	 */
	function ldif_encode($str)
	{
		// base64-encode all values that contain non-ascii chars
		if (preg_match('/[^\x09\x20-\x7E]/', $str))
			return ': ' . base64_encode($this->utf8_convert($str));
		else
			return ' ' . $str;
	}


	/**
	 * Convert a string to UTF-8
	 *
	 * @access private
	 */
	function utf8_convert($str, $from=null)
	{
		if (!$from)
			$from = $this->charset;

		// try to convert to UTF-8
		if ($from != 'UTF-8')
		{
			if ($from == 'ISO-8859-1' && function_exists('utf8_encode'))
				$str = utf8_encode($str);
			else if (function_exists('mb_convert_encoding'))
			{
				$str = mb_convert_encoding($str, 'UTF-8', $from);
				if (strlen($str) == 0)
					error_log("Vcfconvert error: mbstring failed to convert the text!");
			}
			else if (function_exists('iconv'))
			{
				$str = iconv($from, 'UTF-8', $str);
				if (strlen($str) == 0)
					error_log("Vcfconvert error: iconv failed to convert the text!");
			}
			else
				error_log("Vcfconvert warning: the vcard is not in UTF-8.");
		}

		// strip BOM if it is still there
		return ltrim($str, "\xFE\xFF\xEF\xBB\xBF\0");
	}
	
	
	/**
	 * Returns UNICODE type based on BOM (Byte Order Mark) or default value on no match
	 *
	 * @author Clemens Wacha <clemens.wacha@gmx.net>
	 * @access private
	 * @static
	 */
	function get_charset($string)
	{
		if (substr($string, 0, 4) == "\0\0\xFE\xFF") return 'UTF-32BE';  // Big Endian
		if (substr($string, 0, 4) == "\xFF\xFE\0\0") return 'UTF-32LE';  // Little Endian
		if (substr($string, 0, 2) == "\xFE\xFF") return 'UTF-16BE';      // Big Endian
		if (substr($string, 0, 2) == "\xFF\xFE") return 'UTF-16LE';      // Little Endian
		if (substr($string, 0, 3) == "\xEF\xBB\xBF") return 'UTF-8';

		// no match, check for utf-8
		if (vcard_convert::is_utf8($string)) return 'UTF-8';

		// heuristics
		if ($string[0] == "\0" && $string[1] == "\0" && $string[2] == "\0" && $string[3] != "\0") return 'UTF-32BE';
		if ($string[0] != "\0" && $string[1] == "\0" && $string[2] == "\0" && $string[3] == "\0") return 'UTF-32LE';
		if ($string[0] == "\0" && $string[1] != "\0" && $string[2] == "\0" && $string[3] != "\0") return 'UTF-16BE';
		if ($string[0] != "\0" && $string[1] == "\0" && $string[2] != "\0" && $string[3] == "\0") return 'UTF-16LE';

		return false;
	}


	/**
	 * Returns true if $string is valid UTF-8 and false otherwise.
	 * From http://w3.org/International/questions/qa-forms-utf-8.html
	 *
	 * @access private
	 * @static
	 */
	function is_utf8($string)
	{
		return preg_match('/\A(
			[\x09\x0A\x0D\x20-\x7E]
			| [\xC2-\xDF][\x80-\xBF]
			| \xE0[\xA0-\xBF][\x80-\xBF]
			| [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}
			| \xED[\x80-\x9F][\x80-\xBF]
			| \xF0[\x90-\xBF][\x80-\xBF]{2}
			| [\xF1-\xF3][\x80-\xBF]{3}
			| \xF4[\x80-\x8F][\x80-\xBF]{2}
			)*\z/xs', substr($string, 0, 2048));
	}
	
}  // end class vcard_convert


/**
 * Checks if a value exists in an array non-case-sensitive
 */
function in_array_nc($needle, $haystack, $strict = false)
{
	foreach ((array)$haystack as $key => $value)
	{
		if (strtolower($needle) == strtolower($value) && ($strict || gettype($needle) == gettype($value)))
			return true;
	}
	return false;
}


?>
