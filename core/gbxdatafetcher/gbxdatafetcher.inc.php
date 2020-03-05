<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * GBXDataFetcher - Fetch GBX challenge/map/replay/pack data for TrackMania (TM)
 *                  & ManiaPlanet (MP) files
 * Created by Xymph <tm@gamers.org>
 * Thanks to Electron for additional input, prototyping & testing
 * Based on information at https://wiki.xaseco.org/wiki/GBX,
 * http://www.tm-forum.com/viewtopic.php?p=192817#p192817
 * and https://wiki.xaseco.org/wiki/PAK
 *
 * v2.11: Update GBXPackHeaderFetcher $nestLevel to $inclDepth
 * v2.10: Add lookback string Lagoon
 * v2.9: Fix resource leak on PHP7
 * v2.8: Fix minor lookback strings bug
 * v2.7: Add class GBXPackHeaderFetcher for Included Packs info in GBXPackFetcher;
 *       move readFiletime method to base class; add $vehicle to GBXChallMapFetcher
 *       and GBXReplayFetcher; improved chunk handling; minor fixes
 * v2.6: Update GBXPackFetcher version-dependent processing; add $ghostBlocks to
 *       GBXChallMapFetcher; add $headerMaxSz & $downloadUrl to GBXPackFetcher
 * v2.5: Add lookback string Valley; strip optional digits from mood; fix empty
 *       thumbnail warning
 * v2.4: Update GBXChallMapFetcher & GBXPackFetcher version-dependent processing;
 *       update lookback strings
 * v2.3: Add UTF-8 decoding of parsed XML chunk's elements; strip UTF-8 BOM
 *       from various string fields
 * v2.2: Add class GBXPackFetcher; limit loadGBXdata() to first 256KB
 * v2.1: Add exception codes; add $thumbLen to GBXChallMapFetcher
 * v2.0: Complete rewrite
 */

/**
 * @class GBXBaseFetcher
 * @brief The base GBX class with all common functionality
 */
class GBXBaseFetcher
{
	public $parseXml, $xml, $xmlParsed;

	public $authorVer, $authorLogin, $authorNick, $authorZone, $authorEInfo;

	private $_gbxdata, $_gbxlen, $_gbxptr, $_debug, $_error, $_endianess,
	        $_lookbacks, $_parsestack;

	// supported class ID's
	const GBX_CHALLENGE_TMF = 0x03043000;
	const GBX_AUTOSAVE_TMF  = 0x03093000;
	const GBX_CHALLENGE_TM  = 0x24003000;
	const GBX_AUTOSAVE_TM   = 0x2403F000;
	const GBX_REPLAY_TM     = 0x2407E000;

	const MACHINE_ENDIAN_ORDER = 0;
	const LITTLE_ENDIAN_ORDER  = 1;
	const BIG_ENDIAN_ORDER     = 2;

	const LOAD_LIMIT = 256;  // KBs to read in loadGBXdata()

	// initialise new instance
	public function __construct()
	{
		$this->parseXml = false;
		$this->xml = '';
		$this->xmlParsed = array();
		$this->_debug = false;
		$this->_error = '';

		list($endiantest) = array_values(unpack('L1L', pack('V', 1)));
		if ($endiantest != 1)
			$this->_endianess = self::BIG_ENDIAN_ORDER;
		else
			$this->_endianess = self::LITTLE_ENDIAN_ORDER;

		$this->clearGBXdata();
		$this->clearLookbacks();
		$this->_parsestack = array();

		$this->authorVer   = 0;
		$this->authorLogin = '';
		$this->authorNick  = '';
		$this->authorZone  = '';
		$this->authorEInfo = '';
	}

	// enable debug logging
	protected function enableDebug()
	{
		$this->_debug = true;
	}

	// disable debug logging
	protected function disableDebug()
	{
		$this->_debug = false;
	}

	// print message to stderr if debugging
	protected function debugLog($msg)
	{
		if ($this->_debug)
			fwrite(STDERR, $msg."\n");
	}

	// set error message prefix
	protected function setError($prefix)
	{
		$this->_error = (string)$prefix;
	}

	// exit with error exception
	protected function errorOut($msg, $code = 0)
	{
		$this->clearGBXdata();
		throw new Exception($this->_error . $msg, $code);
	}

	// read in raw GBX data
	protected function loadGBXdata($filename)
	{
		$gbxdata = @file_get_contents($filename, false, null, 0, self::LOAD_LIMIT * 1024);
		if ($gbxdata !== false)
			$this->storeGBXdata($gbxdata);
		else
			$this->errorOut('Unable to read GBX data from ' . $filename, 1);
	}

	// store raw GBX data
	protected function storeGBXdata($gbxdata)
	{
		$this->_gbxdata = & $gbxdata;
		$this->_gbxlen = strlen($gbxdata);
		$this->_gbxptr = 0;
		if ($this->_gbxlen > 0)
			$this->debugLog('GBX data length: ' . $this->_gbxlen);
	}

	// retrieve raw GBX data
	protected function retrieveGBXdata()
	{
		return $this->_gbxdata;
	}

	// clear GBX data (to avoid print_r problems & reduce memory usage)
	protected function clearGBXdata()
	{
		$this->storeGBXdata('');
	}

	// get GBX pointer
	protected function getGBXptr()
	{
		return $this->_gbxptr;
	}

	// set GBX pointer
	protected function setGBXptr($ptr)
	{
		$this->_gbxptr = (int)$ptr;
	}

	// move GBX pointer
	protected function moveGBXptr($len)
	{
		$this->_gbxptr += (int)$len;
	}

	// read $len bytes from GBX data
	protected function readData($len)
	{
		if ($this->_gbxptr + $len > $this->_gbxlen)
			$this->errorOut(sprintf('Insufficient data for %d bytes at pos 0x%04X',
			                        $len, $this->_gbxptr), 2);
		$data = '';
		while ($len-- > 0)
			$data .= $this->_gbxdata[$this->_gbxptr++];
		return $data;
	}

	// read signed byte from GBX data
	protected function readInt8()
	{
		$data = $this->readData(1);
		list(, $int8) = unpack('c*', $data);
		return $int8;
	}

	// read signed short from GBX data
	protected function readInt16()
	{
		$data = $this->readData(2);
		if ($this->_endianess == self::BIG_ENDIAN_ORDER)
			$data = strrev($data);
		list(, $int16) = unpack('s*', $data);
		return $int16;
	}

	// read signed long from GBX data
	protected function readInt32()
	{
		$data = $this->readData(4);
		if ($this->_endianess == self::BIG_ENDIAN_ORDER)
			$data = strrev($data);
		list(, $int32) = unpack('l*', $data);
		return $int32;
	}

	// read string from GBX data
	protected function readString()
	{
		$gbxptr = $this->getGBXptr();
		$len = $this->readInt32();
		$len &= 0x7FFFFFFF;
		if ($len <= 0 || $len >= 0x12000) {  // for large XML & Data blocks
			if ($len != 0)
				$this->errorOut(sprintf('Invalid string length %d (0x%04X) at pos 0x%04X',
				                        $len, $len, $gbxptr), 3);
		}
        return $this->readData($len);
	}

	// strip UTF-8 BOM from string
	protected function stripBOM($str)
	{
		return str_replace("\xEF\xBB\xBF", '', $str);
	}

	// clear lookback strings
	protected function clearLookbacks()
	{
		unset($this->_lookbacks);
	}

	// read lookback string from GBX data
	protected function readLookbackString()
	{
		if (!isset($this->_lookbacks)) {
			$this->_lookbacks = array();
			$version = $this->readInt32();
			if ($version != 3)
				$this->errorOut('Unknown lookback strings version: ' . $version, 4);
		}

		// check index
		$index = $this->readInt32();
		if ($index == -1) {
			// unassigned (empty) string
			$str = '';
		} elseif (($index & 0xC0000000) == 0) {
			// use external reference string
			switch ($index) {
				case 11:    $str = 'Valley';
				            break;
				case 12:    $str = 'Canyon';
				            break;
				case 13:    $str = 'Lagoon';
				            break;
				case 17:    $str = 'TMCommon';
				            break;
				case 202:   $str = 'Storm';
				            break;
				case 299:   $str = 'SMCommon';
				            break;
				case 10003: $str = 'Common';
				            break;
				default:    $str = 'UNKNOWN';
			}
		} elseif (($index & 0x3FFFFFFF) == 0) {
			// read string & add to lookbacks
			$str = $this->readString();
			$this->_lookbacks[] = $str;
		} else {
			// use string from lookbacks
			$str = $this->_lookbacks[($index & 0x3FFFFFFF) - 1];
		}

		return $str;
	}

	// XML parser functions
	private function startTag($parser, $name, $attribs)
	{
		foreach ($attribs as $key => &$val)
			$val = utf8_decode($val);
		//echo 'startTag: ' . $name . "\n"; print_r($attribs);
		array_push($this->_parsestack, $name);
		if ($name == 'DEP') {
			$this->xmlParsed['DEPS'][] = $attribs;
		} elseif (count($this->_parsestack) <= 2) {
			// HEADER, IDENT, DESC, TIMES, CHALLENGE/MAP, DEPS, CHECKPOINTS
			$this->xmlParsed[$name] = $attribs;
		}
	}

	private function charData($parser, $data)
	{
		//echo 'charData: ' . $data . "\n";
		if (count($this->_parsestack) == 3)
			$this->xmlParsed[$this->_parsestack[1]][$this->_parsestack[2]] = $data;
		elseif (count($this->_parsestack) > 3)
			$this->debugLog('XML chunk nested too deeply: ' . print_r($this->_parsestack, true));
	}

	private function endTag($parser, $name)
	{
		//echo 'endTag: ' . $name . "\n";
		array_pop($this->_parsestack);
	}

	protected function parseXMLstring()
	{
		// define a dedicated parser to handle the attributes
		$xml_parser = xml_parser_create();
		xml_set_object($xml_parser, $this);
		xml_set_element_handler($xml_parser, 'startTag', 'endTag');
		xml_set_character_data_handler($xml_parser, 'charData');

		// escape '&' characters unless already a known entity
		$xml = preg_replace('/&(?!(?:amp|quot|apos|lt|gt);)/', '&amp;', $this->xml);

		if (!xml_parse($xml_parser, utf8_encode($xml), true))
			$this->errorOut(sprintf('XML chunk parse error: %s at line %d',
			                        xml_error_string(xml_get_error_code($xml_parser)),
			                        xml_get_current_line_number($xml_parser)), 12);

		xml_parser_free($xml_parser);
		unset($xml_parser); // for PHP7
	}

	/**
	 * Check GBX header, main class ID & header block
	 * @param Array $classes
	 *              The main class IDs accepted for this GBX
	 * @return Size of GBX header block
	*/
	protected function checkHeader(array $classes)
	{
		// check magic header
		$data = $this->readData(3);
		$version = $this->readInt16();
		if ($data != 'GBX')
			$this->errorOut('No magic GBX header', 5);
		if ($version != 6)
			$this->errorOut('Unsupported GBX version: ' . $version, 6);

		$this->moveGBXptr(4);  // skip format/compression/unknown bytes

		// check main class ID
		$mainClass = $this->readInt32();
		if (!in_array($mainClass, $classes))
			$this->errorOut(sprintf('Main class ID %08X not supported', $mainClass), 7);
		$this->debugLog(sprintf('GBX main class ID: %08X', $mainClass));

		// get header size
		$headerSize = $this->readInt32();
		$this->debugLog(sprintf('GBX header block size: %d (%.1f KB)',
		                        $headerSize, $headerSize / 1024));
		return $headerSize;
	}  // checkHeader

	/**
	 * Get list of chunks from GBX header block
	 * @param Int $headerSize
	 *        Size of header block (chunks list & chunks data)
	 * @param array $chunks
	 *        List of chunk IDs & names
	 * @return List of chunk offsets & sizes
	 */
	protected function getChunksList($headerSize, array $chunks)
	{
		// get number of chunks
		$numChunks = $this->readInt32();
		if ($numChunks == 0)
			$this->errorOut('No GBX header chunks', 9);

		$this->debugLog('GBX number of header chunks: ' . $numChunks);
		$chunkStart = $this->getGBXptr();
		$this->debugLog(sprintf('GBX start of chunk list: 0x%04X', $chunkStart));
		$chunkOffset = $chunkStart + $numChunks * 8;

		// get list of all chunks
		$chunksList = array();
		for ($i = 0; $i < $numChunks; $i++)
		{
			$chunkId = $this->readInt32();
			$chunkSize = $this->readInt32();
			$chunkSize &= 0x7FFFFFFF;

			if (array_key_exists($chunkId, $chunks)) {
				$name = $chunks[$chunkId];
				$chunksList[$name] = array(
					'off' => $chunkOffset,
					'size' => $chunkSize
				);
			} else {
				$name = 'UNKNOWN';
			}
			$this->debugLog(sprintf('GBX chunk %2d  %-8s  Id  0x%08X  Offset  0x%06X  Size %6d',
			                        $i, $name, $chunkId, $chunkOffset, $chunkSize));
			$chunkOffset += $chunkSize;
		}

		//$this->debugLog(print_r($chunksList, true));
		$totalSize = $chunkOffset - $chunkStart + 4;  // numChunks
		if ($headerSize != $totalSize)
			$this->errorOut(sprintf('Chunk list size mismatch: %d <> %d',
			                        $headerSize, $totalSize), 10);

		return $chunksList;
	}  // getChunksList

	/**
	 * Initialize for a new chunk
	 * @param int $offset
	 */
	protected function initChunk($offset)
	{
		$this->setGBXptr($offset);
		$this->clearLookbacks();
	}

	/**
	 * Get XML chunk from GBX header block & optionally parse it
	 * @param array $chunksList
	 *        List of chunk offsets & sizes
	 */
	protected function getXMLChunk(array $chunksList)
	{
		if (!isset($chunksList['XML'])) return;

		$this->initChunk($chunksList['XML']['off']);
		$this->xml = $this->readString();
		$xmlLen = strlen($this->xml);

		// check for XML chunk that's not zero-filled
		if ($xmlLen > 0 && $chunksList['XML']['size'] != $xmlLen + 4)
			$this->errorOut(sprintf('XML chunk size mismatch: %d <> %d',
			                        $chunksList['XML']['size'], $xmlLen + 4), 11);

		if ($this->parseXml && $this->xml != '')
			$this->parseXMLstring();
	}  // getXMLChunk

	/**
	 * Get Author fields from GBX header block
	 */
	protected function getAuthorFields()
	{
		$this->authorVer   = $this->readInt32();
		$this->authorLogin = $this->readString();
		$this->authorNick  = $this->stripBOM($this->readString());
		$this->authorZone  = $this->stripBOM($this->readString());
		$this->authorEInfo = $this->readString();
	}  // getAuthorFields

	/**
	 * Get Author chunk from GBX header block
	 * @param array $chunksList
	 *        List of chunk offsets & sizes
	 */
	protected function getAuthorChunk(array $chunksList)
	{
		if (!isset($chunksList['Author'])) return;

		$this->initChunk($chunksList['Author']['off']);
		$version = $this->readInt32();
		$this->debugLog('GBX Author chunk version: ' . $version);

		$this->getAuthorFields();
	}  // getAuthorChunk

	/**
	 * Read Windows FileTime and convert to Unix timestamp
	 * Filetime = 64-bit value with the number of 100-nsec intervals since Jan 1, 1601 (UTC)
	 * Based on http://www.mysqlperformanceblog.com/2007/03/27/integers-in-php-running-with-scissors-and-portability/
	 * @return Unix timestamp, or -1 on error
	 */
	protected function readFiletime()
	{
		// Unix epoch (1970-01-01) - Windows epoch (1601-01-01) in 100ns units
		$EPOCHDIFF = '116444735995904000';
		$UINT32MAX = '4294967296';
		$USEC2SEC  = 1000000;

		$lo = $this->readInt32();
		$hi = $this->readInt32();

		// check for 64-bit platform
		if (PHP_INT_SIZE >= 8) {
			// use native math
			if ($lo < 0) $lo += (1 << 32);
			$date = ($hi << 32) + $lo;
			$this->debugLog(sprintf('PAK CreationDate source: %016x', $date));
			if ($date == 0) return -1;

			// convert to Unix timestamp in usec
			$stamp = ($date - (int)$EPOCHDIFF) / 10;
			$this->debugLog(sprintf('PAK CreationDate 64-bit: %u.%06u',
			                        $stamp / $USEC2SEC, $stamp % $USEC2SEC));
			return (int)($stamp / $USEC2SEC);

		// check for 32-bit platform
		} elseif (PHP_INT_SIZE >= 4) {
			$this->debugLog(sprintf('PAK CreationDate source: %08x%08x', $hi, $lo));
			if ($lo == 0 && $hi == 0) return -1;

			// workaround signed/unsigned braindamage on x32
			$lo = sprintf('%u', $lo);
			$hi = sprintf('%u', $hi);

			// try and use GMP
			if (function_exists('gmp_mul')) {
				$date = gmp_add(gmp_mul($hi, $UINT32MAX), $lo);
				// convert to Unix timestamp in usec
				$stamp = gmp_div(gmp_sub($date, $EPOCHDIFF), 10);
				$stamp = gmp_div_qr($stamp, $USEC2SEC);
				$this->debugLog(sprintf('PAK CreationDate GNU MP: %u.%06u',
				                        gmp_strval($stamp[0]), gmp_strval($stamp[1])));
				return (int)gmp_strval($stamp[0]);
			}

			// try and use BC Math
			if (function_exists('bcmul')) {
				$date = bcadd(bcmul($hi, $UINT32MAX), $lo);
				// convert to Unix timestamp in usec
				$stamp = bcdiv(bcsub($date, $EPOCHDIFF), 10, 0);
				$this->debugLog(sprintf('PAK CreationDate BCMath: %u.%06u',
				                        bcdiv($stamp, $USEC2SEC), bcmod($stamp, $USEC2SEC)));
				return (int)bcdiv($stamp, $USEC2SEC);
			}

			// compute everything manually
			$a = substr($hi, 0, -5);
			$b = substr($hi, -5);
			// hope that float precision is enough
			$ac = $a * 42949;
			$bd = $b * 67296;
			$adbc = $a * 67296 + $b * 42949;
			$r4 = substr($bd, -5) + substr($lo, -5);
			$r3 = substr($bd, 0, -5) + substr($adbc, -5) + substr($lo, 0, -5);
			$r2 = substr($adbc, 0, -5) + substr($ac, -5);
			$r1 = substr($ac, 0, -5);
			while ($r4 >= 100000) { $r4 -= 100000; $r3++; }
			while ($r3 >= 100000) { $r3 -= 100000; $r2++; }
			while ($r2 >= 100000) { $r2 -= 100000; $r1++; }
			$date = ltrim(sprintf('%d%05d%05d%05d', $r1, $r2, $r3, $r4), '0');

			// convert to Unix timestamp in usec
			$r3 = substr($date, -6)     - substr($EPOCHDIFF, -6);
			$r2 = substr($date, -12, 6) - substr($EPOCHDIFF, -12, 6);
			$r1 = substr($date, -18, 6) - substr($EPOCHDIFF, -18, 6);
			if ($r3 < 0) { $r3 += 1000000; $r2--; }
			if ($r2 < 0) { $r2 += 1000000; $r1--; }
			$stamp = substr(sprintf('%d%06d%06d', $r1, $r2, $r3), 0, -1);
			$this->debugLog(sprintf('PAK CreationDate manual: %s.%s',
			                        substr($stamp, 0, -6), substr($stamp, -6)));
			return (int)substr($stamp, 0, -6);
		} else {
			return -1;
		}
	}  // readFiletime

}  // class GBXBaseFetcher


/**
 * @class GBXChallMapFetcher
 * @brief The class that fetches all GBX challenge/map info
 */
class GBXChallMapFetcher extends GBXBaseFetcher
{
	public $tnImage;

	public $headerVersn, $bronzeTime, $silverTime, $goldTime, $authorTime,
	       $cost, $multiLap, $type, $typeName, $authorScore, $simpleEdit, $ghostBlocks,
	       $nbChecks, $nbLaps;
	public $uid, $envir, $author, $name, $kind, $kindName, $password,
	       $mood, $envirBg, $authorBg, $mapType, $mapStyle, $lightmap, $titleUid;
	public $xmlVer, $exeVer, $exeBld, $validated, $songFile, $songUrl,
	       $modName, $modFile, $modUrl, $vehicle;
	public $thumbLen, $thumbnail, $comment;

	const IMAGE_FLIP_HORIZONTAL = 1;
	const IMAGE_FLIP_VERTICAL   = 2;
	const IMAGE_FLIP_BOTH       = 3;

	/**
	 * Mirror (flip) an image across horizontal, vertical or both axis
	 * Source: http://www.php.net/manual/en/function.imagecopy.php#85992
	 * @param String $imgsrc
	 *        Source image data
	 * @param Int $dir
	 *        Flip direction from the constants above
	 * @return Flipped image data if successful, otherwise source image data
	 */
	private function imageFlip($imgsrc, $dir)
	{
		$width      = imagesx($imgsrc);
		$height     = imagesy($imgsrc);
		$src_x      = 0;
		$src_y      = 0;
		$src_width  = $width;
		$src_height = $height;

		switch ((int)$dir) {
			case self::IMAGE_FLIP_HORIZONTAL:
				$src_y      =  $height;
				$src_height = -$height;
				break;
			case self::IMAGE_FLIP_VERTICAL:
				$src_x      =  $width;
				$src_width  = -$width;
				break;
			case self::IMAGE_FLIP_BOTH:
				$src_x      =  $width;
				$src_y      =  $height;
				$src_width  = -$width;
				$src_height = -$height;
				break;
			default:
				return $imgsrc;
		}

		$imgdest = imagecreatetruecolor($width, $height);
		if (imagecopyresampled($imgdest, $imgsrc, 0, 0, $src_x, $src_y,
		                       $width, $height, $src_width, $src_height)) {
			return $imgdest;
		}
		return $imgsrc;
	}  // imageFlip

	/**
	 * Instantiate GBX challenge/map fetcher
	 *
	 * @param Boolean $parsexml
	 *        If true, the fetcher also parses the XML block
	 * @param Boolean $tnimage
	 *        If true, the fetcher also extracts the thumbnail image;
	 *        if GD/JPEG libraries are present, image will be flipped upright,
	 *        otherwise it will be in the original upside-down format
	 *        Warning: this is binary data in JPEG format, 256x256 pixels for
	 *        TMU/TMF or 512x512 pixels for MP
	 * @param Boolean $debug
	 *        If true, the fetcher prints debug logging to stderr
	 */
	public function __construct($parsexml = false, $tnimage = false, $debug = false)
	{
		parent::__construct();

		$this->headerVersn = 0;
		$this->bronzeTime  = 0;
		$this->silverTime  = 0;
		$this->goldTime    = 0;
		$this->authorTime  = 0;

		$this->cost        = 0;
		$this->multiLap    = false;
		$this->type        = 0;
		$this->typeName    = '';

		$this->authorScore = 0;
		$this->simpleEdit  = false;
		$this->ghostBlocks = false;
		$this->nbChecks    = 0;
		$this->nbLaps      = 0;

		$this->uid       = '';
		$this->envir     = '';
		$this->author    = '';
		$this->name      = '';
		$this->kind      = 0;
		$this->kindName  = '';

		$this->password  = '';
		$this->mood      = '';
		$this->envirBg   = '';
		$this->authorBg  = '';

		$this->mapType   = '';
		$this->mapStyle  = '';
		$this->lightmap  = 0;
		$this->titleUid  = '';

		$this->xmlVer    = '';
		$this->exeVer    = '';
		$this->exeBld    = '';
		$this->validated = false;
		$this->songFile  = '';
		$this->songUrl   = '';
		$this->modName   = '';
		$this->modFile   = '';
		$this->modUrl    = '';
		$this->vehicle   = '';

		$this->thumbLen  = 0;
		$this->thumbnail = '';
		$this->comment   = '';

		$this->parseXml = (bool)$parsexml;
		$this->tnImage  = (bool)$tnimage;
		if ((bool)$debug)
			$this->enableDebug();

		$this->setError('GBX map error: ');
	}  // __construct

	/**
	 * Process GBX challenge/map file
	 *
	 * @param String $filename
	 *        The challenge filename
	 */
	public function processFile($filename)
	{
		$this->loadGBXdata((string)$filename);

		$this->processGBX();
	}  // processFile

	/**
	 * Process GBX challenge/map data
	 *
	 * @param String $gbxdata
	 *        The challenge/map data
	 */
	public function processData($gbxdata)
	{
		$this->storeGBXdata((string)$gbxdata);

		$this->processGBX();
	}  // processData

	// process GBX data
	private function processGBX()
	{
		// supported challenge/map class IDs
		$challclasses = array(
			self::GBX_CHALLENGE_TMF,
			self::GBX_CHALLENGE_TM,
		);

		$headerSize = $this->checkHeader($challclasses);
		if ($headerSize == 0)
			$this->errorOut('No GBX header block', 8);
		$headerStart = $headerEnd = $this->getGBXptr();

		// desired challenge/map chunk IDs
		$chunks = array(
			0x03043002 => 'Info',     // TM, MP
			0x24003002 => 'Info',     // TM
			0x03043003 => 'String',   // TM, MP
			0x24003003 => 'String',   // TM
			0x03043004 => 'Version',  // TM, MP
			0x24003004 => 'Version',  // TM
			0x03043005 => 'XML',      // TM, MP
			0x24003005 => 'XML',      // TM
			0x03043007 => 'Thumbnl',  // TM, MP
			0x24003007 => 'Thumbnl',  // TM
			0x03043008 => 'Author',   // MP
		);

		$chunksList = $this->getChunksList($headerSize, $chunks);

		$this->getInfoChunk($chunksList);
		$headerEnd = max($headerEnd, $this->getGBXptr());

		$this->getStringChunk($chunksList);
		$headerEnd = max($headerEnd, $this->getGBXptr());

		$this->getVersionChunk($chunksList);
		$headerEnd = max($headerEnd, $this->getGBXptr());

		$this->getXMLChunk($chunksList);
		$headerEnd = max($headerEnd, $this->getGBXptr());

		$this->getThumbnlChunk($chunksList);
		$headerEnd = max($headerEnd, $this->getGBXptr());

		$this->getAuthorChunk($chunksList);
		$headerEnd = max($headerEnd, $this->getGBXptr());

		if ($headerSize != $headerEnd - $headerStart)
			$this->errorOut(sprintf('Header size mismatch: %d <> %d',
			                        $headerSize, $headerEnd - $headerStart), 16);

		if ($this->parseXml) {
			if (isset($this->xmlParsed['HEADER']['VERSION']))
				$this->xmlVer = $this->xmlParsed['HEADER']['VERSION'];
			if (isset($this->xmlParsed['HEADER']['EXEVER']))
				$this->exeVer = $this->xmlParsed['HEADER']['EXEVER'];
			if (isset($this->xmlParsed['HEADER']['EXEBUILD']))
				$this->exeBld = $this->xmlParsed['HEADER']['EXEBUILD'];
			if ($this->lightmap == 0 && isset($this->xmlParsed['HEADER']['LIGHTMAP']))
				$this->lightmap = (int)$this->xmlParsed['HEADER']['LIGHTMAP'];
			if ($this->authorZone == '' && isset($this->xmlParsed['IDENT']['AUTHORZONE']))
				$this->authorZone = $this->xmlParsed['IDENT']['AUTHORZONE'];
			if ($this->envir == 'UNKNOWN' && isset($this->xmlParsed['DESC']['ENVIR']))
				$this->envir = $this->xmlParsed['DESC']['ENVIR'];
			if ($this->nbLaps == 0 && isset($this->xmlParsed['DESC']['NBLAPS']))
				$this->nbLaps = (int)$this->xmlParsed['DESC']['NBLAPS'];
			if (isset($this->xmlParsed['DESC']['VALIDATED']))
				$this->validated = (bool)$this->xmlParsed['DESC']['VALIDATED'];
			if (isset($this->xmlParsed['DESC']['MOD']))
				$this->modName = $this->xmlParsed['DESC']['MOD'];
			if (isset($this->xmlParsed['PLAYERMODEL']['ID']))
				$this->vehicle = $this->xmlParsed['PLAYERMODEL']['ID'];

			// extract optional song & mod filenames
			if (!empty($this->xmlParsed['DEPS'])) {
				for ($i = 0; $i < count($this->xmlParsed['DEPS']); $i++) {
					if (preg_match('/ChallengeMusics\\\\(.+)/', $this->xmlParsed['DEPS'][$i]['FILE'], $path)) {
						$this->songFile = $path[1];
						if (isset($this->xmlParsed['DEPS'][$i]['URL']))
							$this->songUrl = $this->xmlParsed['DEPS'][$i]['URL'];
					} elseif (preg_match('/.+\\\\Mod\\\\.+/', $this->xmlParsed['DEPS'][$i]['FILE'], $path)) {
						$this->modFile = $path[0];
						if (isset($this->xmlParsed['DEPS'][$i]['URL']))
							$this->modUrl = $this->xmlParsed['DEPS'][$i]['URL'];
					}
				}
			}
		}

		$this->clearGBXdata();
	}  // processGBX

	/**
	 * Get Info chunk from GBX header block
	 * @param array $chunksList
	 *        List of chunk offsets & sizes
	 */
	protected function getInfoChunk(array $chunksList)
	{
		if (!isset($chunksList['Info'])) return;

		$this->initChunk($chunksList['Info']['off']);
		$version = $this->readInt8();
		$this->debugLog('GBX Info chunk version: ' . $version);

		if ($version < 3) {
			$this->uid = $this->readLookbackString();

			$this->envir  = $this->readLookbackString();
			$this->author = $this->readLookbackString();

			$this->name = $this->stripBOM($this->readString());
		}
		$this->moveGBXptr(4);  // skip bool 0

		if ($version >= 1) {
			$this->bronzeTime = $this->readInt32();

			$this->silverTime = $this->readInt32();

			$this->goldTime = $this->readInt32();

			$this->authorTime = $this->readInt32();

			if ($version == 2)
				$this->moveGBXptr(1);  // skip unknown byte

			if ($version >= 4) {
				$this->cost = $this->readInt32();

				if ($version >= 5) {
					$this->multiLap = (bool)$this->readInt32();

					if ($version == 6)
						$this->moveGBXptr(4);  // skip unknown bool

					if ($version >= 7) {
						$this->type = $this->readInt32();
						switch ($this->type) {
							case 0: $this->typeName = 'Race';
							        break;
							case 1: $this->typeName = 'Platform';
							        break;
							case 2: $this->typeName = 'Puzzle';
							        break;
							case 3: $this->typeName = 'Crazy';
							        break;
							case 4: $this->typeName = 'Shortcut';
							        break;
							case 5: $this->typeName = 'Stunts';
							        break;
							case 6: $this->typeName = 'Script';
							        break;
							default: $this->typeName = 'UNKNOWN';
						}

						if ($version >= 9) {
							$this->moveGBXptr(4);  // skip int32 0

							if ($version >= 10) {
								$this->authorScore = $this->readInt32();

								if ($version >= 11) {
									$editorMode = $this->readInt32();
									$this->simpleEdit = (bool)($editorMode & 1);
									$this->ghostBlocks = (bool)($editorMode & 2);

									if ($version >= 12) {
										$this->moveGBXptr(4);  // skip bool 0

										if ($version >= 13) {
											$this->nbChecks = $this->readInt32();

											$this->nbLaps = $this->readInt32();
										}
									}
								}
							}
						}
					}
				}
			}
		}
	}  // getInfoChunk

	/**
	 * Get String chunk from GBX header block
	 * @param array $chunksList
	 *        List of chunk offsets & sizes
	 */
	protected function getStringChunk(array $chunksList)
	{
		if (!isset($chunksList['String'])) return;

		$this->initChunk($chunksList['String']['off']);
		$version = $this->readInt8();
		$this->debugLog('GBX String chunk version: ' . $version);

		$this->uid = $this->readLookbackString();

		$this->envir  = $this->readLookbackString();
		$this->author = $this->readLookbackString();

		$this->name = $this->stripBOM($this->readString());

		$this->kind = $this->readInt8();
		switch ($this->kind) {
			case 0:  $this->kindName = '(internal)EndMarker';
			         break;
			case 1:  $this->kindName = '(old)Campaign';
			         break;
			case 2:  $this->kindName = '(old)Puzzle';
			         break;
			case 3:  $this->kindName = '(old)Retro';
			         break;
			case 4:  $this->kindName = '(old)TimeAttack';
			         break;
			case 5:  $this->kindName = '(old)Rounds';
			         break;
			case 6:  $this->kindName = 'InProgress';
			         break;
			case 7:  $this->kindName = 'Campaign';
			         break;
			case 8:  $this->kindName = 'Multi';
			         break;
			case 9:  $this->kindName = 'Solo';
			         break;
			case 10: $this->kindName = 'Site';
			         break;
			case 11: $this->kindName = 'SoloNadeo';
			         break;
			case 12: $this->kindName = 'MultiNadeo';
			         break;
			default: $this->kindName = 'UNKNOWN';
		}

		if ($version >= 1) {
			$this->moveGBXptr(4);  // skip locked

			$this->password = $this->readString();

			if ($version >= 2) {
				$this->mood = $this->readLookbackString();
				// strip optional trailing digits (48)
				$this->mood = preg_replace('/^([A-Za-z]+)\d*/', '\1', $this->mood);

				$this->envirBg  = $this->readLookbackString();
				$this->authorBg = $this->readLookbackString();

				if ($version >= 3) {
					$this->moveGBXptr(8);  // skip mapOrigin

					if ($version >= 4) {
						$this->moveGBXptr(8);  // skip mapTarget

						if ($version >= 5) {
							$this->moveGBXptr(16);  // skip unknown int128

							if ($version >= 6) {
								$this->mapType  = $this->readString();
								$this->mapStyle = $this->readString();

								if ($version <= 8)
									$this->moveGBXptr(4);  // skip unknown bool

								if ($version >= 8) {
									$this->moveGBXptr(8);  // skip lightmapCacheUID

									if ($version >= 9) {
										$this->lightmap = $this->readInt8();

										if ($version >= 11) {
											$this->titleUid = $this->readLookbackString();
										}
									}
								}
							}
						}
					}
				}
			}
		}
	}  // getStringChunk

	/**
	 * Get Version chunk from GBX header block
	 * @param array $chunksList
	 *        List of chunk offsets & sizes
	 */
	protected function getVersionChunk(array $chunksList)
	{
		if (!isset($chunksList['Version'])) return;

		$this->initChunk($chunksList['Version']['off']);
		$this->headerVersn = $this->readInt32();
	}  // getVersionChunk

	/**
	 * Get Thumbnail/Comments chunk from GBX header block
	 * @param array $chunksList
	 *        List of chunk offsets & sizes
	 */
	protected function getThumbnlChunk(array $chunksList)
	{
		if (!isset($chunksList['Thumbnl'])) return;

		$this->initChunk($chunksList['Thumbnl']['off']);
		$version = $this->readInt32();
		$this->debugLog('GBX Thumbnail chunk version: ' . $version);

		if ($version == 1) {
			$thumbSize = $this->readInt32();
			$this->debugLog(sprintf('GBX Thumbnail size: %d (%.1f KB)',
			                        $thumbSize, $thumbSize / 1024));

			$this->moveGBXptr(strlen('<Thumbnail.jpg>'));
			$this->thumbnail = $this->readData($thumbSize);
			$this->thumbLen = strlen($this->thumbnail);
			$this->moveGBXptr(strlen('</Thumbnail.jpg>'));

			$this->moveGBXptr(strlen('<Comments>'));
			$this->comment = $this->stripBOM($this->readString());
			$this->moveGBXptr(strlen('</Comments>'));

			// return extracted thumbnail image?
			if ($this->tnImage && $this->thumbLen > 0) {
				// check for GD/JPEG libraries
				if (function_exists('imagecreatefromjpeg') &&
				    function_exists('imagecopyresampled')) {
					// flip thumbnail via temporary file
					$tmp = tempnam(sys_get_temp_dir(), 'gbxflip');
					if (@file_put_contents($tmp, $this->thumbnail) !== false) {
						if ($tn = @imagecreatefromjpeg($tmp)) {
							$tn = $this->imageFlip($tn, self::IMAGE_FLIP_HORIZONTAL);
							if (@imagejpeg($tn, $tmp)) {
								if (($tn = @file_get_contents($tmp)) !== false) {
									$this->thumbnail = $tn;
								}
							}
						}
						unlink($tmp);
					}
				}
			} else {
				$this->thumbnail = '';
			}
		}
	}  // getThumbnlChunk

}  // class GBXChallMapFetcher


/**
 * @class GBXChallengeFetcher
 * @brief Wrapper class for backwards compatibility with the old GBXChallengeFetcher
 * @deprecated Do not use for new development, use GBXChallMapFetcher instead
 */
class GBXChallengeFetcher extends GBXChallMapFetcher
{
	public $authortm, $goldtm, $silvertm, $bronzetm, $ascore, $azone, $multi, $editor,
	       $pub, $nblaps, $parsedxml, $xmlver, $exever, $exebld, $songfile, $songurl,
	       $modname, $modfile, $modurl;

	/**
	 * Fetches a hell of a lot of data about a GBX challenge
	 *
	 * @param String $filename
	 *        The challenge filename (must include full path)
	 * @param Boolean $parsexml
	 *        If true, the script also parses the XML block
	 * @param Boolean $tnimage
	 *        If true, the script also extracts the thumbnail image; if GD/JPEG
	 *        libraries are present, image will be flipped upright, otherwise
	 *        it will be in the original upside-down format
	 *        Warning: this is binary data in JPEG format, 256x256 pixels for
	 *        TMU/TMF or 512x512 pixels for MP
	 * @return GBXChallengeFetcher
	 *        If $uid is empty, GBX data couldn't be extracted
	 */
	public function __construct($filename, $parsexml = false, $tnimage = false)
	{
		parent::__construct($parsexml, $tnimage, false);

		try
		{
			$this->processFile($filename);

			$this->authortm  = $this->authorTime;
			$this->goldtm    = $this->goldTime;
			$this->silvertm  = $this->silverTime;
			$this->bronzetm  = $this->bronzeTime;
			$this->ascore    = $this->authorScore;
			$this->azone     = $this->authorZone;
			$this->multi     = $this->multiLap;
			$this->editor    = $this->simpleEdit;
			$this->pub       = $this->authorBg;
			$this->nblaps    = $this->nbLaps;
			$this->parsedxml = $this->xmlParsed;
			$this->xmlver    = $this->xmlVer;
			$this->exever    = $this->exeVer;
			$this->exebld    = $this->exeBld;
			$this->songfile  = $this->songFile;
			$this->songurl   = $this->songUrl;
			$this->modname   = $this->modName;
			$this->modfile   = $this->modFile;
			$this->modurl    = $this->modUrl;
		}
		catch (Exception $e)
		{
			$this->uid = '';
		}
	}  // __construct

}  // class GBXChallengeFetcher


/**
 * @class GBXReplayFetcher
 * @brief The class that fetches all GBX replay info
 * @note The interface for GBXReplayFetcher has changed compared to the old class,
 *       but there is no wrapper because no third-party XASECO[2] plugins used that
 */
class GBXReplayFetcher extends GBXBaseFetcher
{
	public $uid, $envir, $author, $replay, $nickname, $login, $titleUid;
	public $xmlVer, $exeVer, $exeBld, $respawns, $stuntScore, $validable,
	       $cpsCur, $cpsLap, $vehicle;

	/**
	 * Instantiate GBX replay fetcher
	 *
	 * @param Boolean $parsexml
	 *        If true, the fetcher also parses the XML block
	 * @param Boolean $debug
	 *        If true, the fetcher prints debug logging to stderr
	 * @return GBXReplayFetcher
	 *        If GBX data couldn't be extracted, an Exception is thrown with
	 *        the error message & code
	 */
	public function __construct($parsexml = false, $debug = false)
	{
		parent::__construct();

		$this->uid      = '';
		$this->envir    = '';
		$this->author   = '';
		$this->replay   = 0;
		$this->nickname = '';
		$this->login    = '';
		$this->titleUid = '';

		$this->xmlVer     = '';
		$this->exeVer     = '';
		$this->exeBld     = '';
		$this->respawns   = 0;
		$this->stuntScore = 0;
		$this->validable  = false;
		$this->cpsCur     = 0;
		$this->cpsLap     = 0;
		$this->vehicle    = '';

		$this->parseXml = (bool)$parsexml;
		if ((bool)$debug)
			$this->enableDebug();

		$this->setError('GBX replay error: ');
	}  // __construct

	/**
	 * Process GBX replay file
	 *
	 * @param String $filename
	 *        The replay filename
	 */
	public function processFile($filename)
	{
		$this->loadGBXdata((string)$filename);

		$this->processGBX();
	}  // processFile

	/**
	 * Process GBX replay data
	 *
	 * @param String $gbxdata
	 *        The replay data
	 */
	public function processData($gbxdata)
	{
		$this->storeGBXdata((string)$gbxdata);

		$this->processGBX();
	}  // processData

	// process GBX data
	private function processGBX()
	{
		// supported replay class IDs
		$replayclasses = array(
			self::GBX_AUTOSAVE_TMF,
			self::GBX_AUTOSAVE_TM,
			self::GBX_REPLAY_TM,
		);

		$headerSize = $this->checkHeader($replayclasses);
		if ($headerSize == 0)
			$this->errorOut('No GBX header block', 8);
		$headerStart = $headerEnd = $this->getGBXptr();

		// desired replay chunk IDs
		$chunks = array(
			0x03093000 => 'String',  // TM, MP
			0x2403F000 => 'String',  // TM
			0x03093001 => 'XML',     // TM, MP
			0x2403F001 => 'XML',     // TM
			0x03093002 => 'Author',  // MP
		);

		$chunksList = $this->getChunksList($headerSize, $chunks);

		$this->getStringChunk($chunksList);
		$headerEnd = max($headerEnd, $this->getGBXptr());

		$this->getXMLChunk($chunksList);
		$headerEnd = max($headerEnd, $this->getGBXptr());

		$this->getAuthorChunk($chunksList);
		$headerEnd = max($headerEnd, $this->getGBXptr());

		if ($headerSize != $headerEnd - $headerStart)
			$this->errorOut(sprintf('Header size mismatch: %d <> %d',
			                        $headerSize, $headerEnd - $headerStart), 20);

		if ($this->parseXml) {
			if (isset($this->xmlParsed['HEADER']['VERSION']))
				$this->xmlVer = $this->xmlParsed['HEADER']['VERSION'];
			if (isset($this->xmlParsed['HEADER']['EXEVER']))
				$this->exeVer = $this->xmlParsed['HEADER']['EXEVER'];
			if (isset($this->xmlParsed['HEADER']['EXEBUILD']))
				$this->exeBld = $this->xmlParsed['HEADER']['EXEBUILD'];
			if (isset($this->xmlParsed['TIMES']['RESPAWNS']))
				$this->respawns = (int)$this->xmlParsed['TIMES']['RESPAWNS'];
			if (isset($this->xmlParsed['TIMES']['STUNTSCORE']))
				$this->stuntScore = (int)$this->xmlParsed['TIMES']['STUNTSCORE'];
			if (isset($this->xmlParsed['TIMES']['VALIDABLE']))
				$this->validable = (bool)$this->xmlParsed['TIMES']['VALIDABLE'];
			if (isset($this->xmlParsed['CHECKPOINTS']['CUR']))
				$this->cpsCur = (int)$this->xmlParsed['CHECKPOINTS']['CUR'];
			if (isset($this->xmlParsed['CHECKPOINTS']['ONELAP']))
				$this->cpsLap = (int)$this->xmlParsed['CHECKPOINTS']['ONELAP'];
			if (isset($this->xmlParsed['PLAYERMODEL']['ID']))
				$this->vehicle = $this->xmlParsed['PLAYERMODEL']['ID'];
		}

		$this->clearGBXdata();
	}  // processGBX

	/**
	 * Get String chunk from GBX header block
	 * @param array $chunksList
	 *        List of chunk offsets & sizes
	 */
	protected function getStringChunk(array $chunksList)
	{
		if (!isset($chunksList['String'])) return;

		$this->initChunk($chunksList['String']['off']);
		$version = $this->readInt32();
		$this->debugLog('GBX String chunk version: ' . $version);

		if ($version >= 2) {
			$this->uid = $this->readLookbackString();

			$this->envir  = $this->readLookbackString();
			$this->author = $this->readLookbackString();

			$this->replay = $this->readInt32();

			$this->nickname = $this->stripBOM($this->readString());

			if ($version >= 6) {
				$this->login = $this->readString();

				if ($version >= 8) {
					$this->moveGBXptr(1);  // skip byte 1

					$this->titleUid = $this->readLookbackString();
				}
			}
		}
	}  // getStringChunk

}  // class GBXReplayFetcher


/**
 * @class GBXPackFetcher
 * @brief The class that fetches all GBX pack info
 */
class GBXPackFetcher extends GBXBaseFetcher
{
	public $headerVersn, $flags, $headerMaxSz, $infoMlUrl, $downloadUrl, $creatDate, $comment,
	       $titleId, $usageSubdir, $buildInfo, $authorUrl, $exeVer, $exeBld, $xmlDate,
	       $nbPacks, $packHeaders;

	/**
	 * Instantiate GBX pack fetcher
	 *
	 * @param Boolean $parsexml
	 *        If true, the fetcher also parses the XML block
	 * @param Boolean $debug
	 *        If true, the fetcher prints debug logging to stderr
	 * @return GBXPackFetcher
	 *        If GBX data couldn't be extracted, an Exception is thrown with
	 *        the error message & code
	 */
	public function __construct($parsexml = false, $debug = false)
	{
		parent::__construct();

		$this->headerVersn = 0;
		$this->flags       = 0;
		$this->headerMaxSz = 0;
		$this->infoMlUrl   = '';
		$this->downloadUrl = '';
		$this->creatDate   = -1;
		$this->comment     = '';
		$this->titleId     = '';
		$this->usageSubdir = '';
		$this->buildInfo   = '';
		$this->authorUrl   = '';
		$this->exeVer      = '';
		$this->exeBld      = '';
		$this->xmlDate     = '';
		$this->nbPacks     = 0;
		$this->packHeaders = array();

		$this->parseXml = (bool)$parsexml;
		if ((bool)$debug)
			$this->enableDebug();

		$this->setError('GBX pack error: ');
	}  // __construct

	/**
	 * Process GBX pack file
	 *
	 * @param String $filename
	 *        The pack filename
	 */
	public function processFile($filename)
	{
		$this->loadGBXdata((string)$filename);

		$this->processGBX();
	}  // processFile

	/**
	 * Process GBX pack data
	 *
	 * @param String $gbxdata
	 *        The pack data
	 */
	public function processData($gbxdata)
	{
		$this->storeGBXdata((string)$gbxdata);

		$this->processGBX();
	}  // processData

	// process GBX data
	private function processGBX()
	{
		// check magic header
		$data = $this->readData(8);
		if ($data != 'NadeoPak')
			$this->errorOut('No magic NadeoPak header', 5);

		$this->headerVersn = $this->readInt32();
		if ($this->headerVersn < 6)
			$this->errorOut(sprintf('Pack version %d not supported', $this->headerVersn), 24);

		$this->moveGBXptr(32);  // skip ContentsChecksum

		$this->flags = $this->readInt32(); // DecryptFlags
		if ($this->headerVersn >= 15)
			$this->headerMaxSz = $this->readInt32(); // 0x4000 = small, 0x100000 = big, 0x1000000 = huge

		if ($this->headerVersn >= 7) {
			$this->getAuthorFields();

			if ($this->headerVersn < 9) {
				$this->comment = $this->stripBOM($this->readString());

				$this->moveGBXptr(16);  // skip unused uint128

				if ($this->headerVersn >= 8) {
					$this->buildInfo = $this->readString();

					$this->authorUrl = $this->readString();
				}

			} else {  // >= 9
				$this->infoMlUrl = $this->readString();

				if ($this->headerVersn >= 13)
					$this->downloadUrl = $this->readString();

				$this->creatDate = $this->readFiletime();

				$this->comment = $this->stripBOM($this->readString());

				if ($this->headerVersn >= 12) {
					$this->xml = $this->readString();
					$this->titleId = $this->readString();

					if ($this->parseXml && $this->xml != '') {
						$this->parseXMLstring();

						if (isset($this->xmlParsed['HEADER']['EXEVER']))
							$this->exeVer = $this->xmlParsed['HEADER']['EXEVER'];
						if (isset($this->xmlParsed['HEADER']['EXEBUILD']))
							$this->exeBld = $this->xmlParsed['HEADER']['EXEBUILD'];
						if (isset($this->xmlParsed['IDENT']['CREATIONDATE']))
							$this->xmlDate = $this->xmlParsed['IDENT']['CREATIONDATE'];
					}
				}

				$this->usageSubdir = $this->readString();

				$this->buildInfo = $this->readString();

				$this->moveGBXptr(16);  // skip unused uint128
				if ($this->headerVersn >= 10) {
					$this->nbPacks = $this->readInt32();

					// process all included pack headers
					for ($i = 0; $i < $this->nbPacks; $i++) {
						// pass pack base class to header class
						$packHeader = new GBXPackHeaderFetcher($this);

						$packHeader->processGBX();

						$packHeader->cleanup($this);

						// collect included pack header
						$this->packHeaders[] = $packHeader;
					}
				}
			}
		}

		$this->clearGBXdata();
	}  // processGBX

}  // class GBXPackFetcher


/**
 * @class GBXPackHeaderFetcher
 * @brief The class that fetches GBX included pack header info
 */
class GBXPackHeaderFetcher extends GBXBaseFetcher
{
	public $name, $infoMlUrl, $creatDate, $inclDepth;

	private $_headerVersn;

	/**
	 * Instantiate GBX included pack header fetcher
	 *
	 * @param GBXPackFetcher $packGBX
	 *        The pack class, needed for headerVersn & the overall base class
	 * @return GBXPackHeaderFetcher
	 *        If GBX data couldn't be extracted, an Exception is thrown with
	 *        the error message & code
	 */
	public function __construct(GBXPackFetcher $packGBX)
	{
		$this->name      = '';
		$this->infoMlUrl = '';
		$this->creatDate = -1;
		$this->inclDepth = 0;

		$this->setError('GBX pack header error: ');

		$this->_headerVersn = $packGBX->headerVersn;

		// copy raw GBX data
		$this->storeGBXdata($packGBX->retrieveGBXdata());

		// keep GBX pointer synced between classes
		$this->setGBXptr($packGBX->getGBXptr());
	}  // __construct

	/**
	 * Clean up GBX included pack header fetcher
	 *
	 * @param GBXPackFetcher $packGBX
	 *        The pack class, needed for the overall base class
	 */
	public function cleanup(GBXPackFetcher $packGBX)
	{
		// keep GBX pointer synced between classes
		$packGBX->setGBXptr($this->getGBXptr());

		// discard copied GBX data
		$this->clearGBXdata();
	}  // cleanup

	/**
	 * Process GBX included pack header
	 */
	public function processGBX()
	{
		$this->moveGBXptr(32);  // skip ContentsChecksum

		$this->name = $this->readString();

		$this->getAuthorFields();

		$this->infoMlUrl = $this->readString();

		$this->creatDate = $this->readFiletime();

		$this->name = $this->readString();  // duplicate

		if ($this->_headerVersn >= 11)
			$this->inclDepth = $this->readInt32();
	}  // processGBX

}  // class GBXPackHeaderFetcher

