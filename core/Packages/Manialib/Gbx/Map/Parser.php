<?php

namespace Manialib\Gbx\Map;

use Manialib\Gbx\Map;

class Parser
{
    /**
     * @param string $content
     * @return Map
     */
    public static function parseString($content)
    {
        $stream = fopen('php://memory', 'rb+');
        fwrite($stream, $content);
        rewind($stream);
        $data = static::parse($stream);
        fclose($stream);
        return $data;
    }

    /**
     * @param string $filename
     * @return Map
     */
    public static function parseFile($filename)
    {
        $file = fopen($filename, 'rb');
        $data = static::parse($file);
        fclose($file);
        return $data;
    }

    /**
     * @param string $content
     * @return Map
     */
    protected static function parse($fileHandler)
    {
        self::ignore($fileHandler, 9);
        $classId = self::fetchLong($fileHandler);
        if ($classId != 0x03043000) {
            throw new \InvalidArgumentException('File is not a map');
        }
        self::ignore($fileHandler, 4);
        $nbChunks = self::fetchLong($fileHandler);
        $chunkInfos = array();
        for (; $nbChunks > 0; --$nbChunks) {
            $chunkInfos[self::fetchLong($fileHandler)] = self::fetchLong($fileHandler);
        }

        $properties = [];
        foreach ($chunkInfos as $chunkId => $chunkSize) {
            switch ($chunkId) {
                case 0x03043005:
                    $properties = array_merge($properties, self::parseXMLHeader($fileHandler));
                    break;
                case 0x03043007:
                    $properties = array_merge($properties, self::parseThumbnailAndComment($fileHandler));
                    break;
                default:
                    self::ignore($fileHandler, $chunkSize & 0x7fffffff);
            }
        }

        return new Map($properties);
    }

    /**
     * @param \DOMNode $node
     * @return \DOMNamedNodeMap
     */
    protected static function getNodeAttributesValues(\DOMNode $node)
    {
        $attributes = array();
        foreach ($node->attributes as $attribute) {
            $attributes[$attribute->nodeName] = $attribute->nodeValue;
        }

        return $attributes;
    }

    protected static function fetchLong($fp)
    {
        $long = unpack('V', fread($fp, 4));
        return $long[1];
    }

    protected static function fetchString($fp)
    {
        $length = self::fetchLong($fp);
        return $length ? fread($fp, $length) : '';
    }

    protected static function ignore($fp, $length)
    {
        fread($fp, $length);
    }

    protected static function parseXMLHeader($fp)
    {
        $properties = [];
        $domDocument = new \DOMDocument();
        $internalError = libxml_use_internal_errors(true);
        $domDocument->loadHTML('<?xml encoding="UTF-8">' . self::fetchString($fp));
        libxml_use_internal_errors($internalError);

        $header = static::getNodeAttributesValues($domDocument->getElementsByTagName('header')->item(0));
        $properties['exeVersion'] = $header['exever'] ?? null;
        $properties['exeBuild'] = $header['exebuild'] ?? null;
        $properties['title'] = $header['title'] ?? null;
        $properties['lightmap'] = (int) $header['lightmap'] ?? null;

        $ident = static::getNodeAttributesValues($domDocument->getElementsByTagName('ident')->item(0));
        $properties['uid'] = $ident['uid'] ?? null;
        $properties['name'] = $ident['name'] ?? null;
        $properties['author'] = $ident['author'] ?? null;
        $properties['authorZone'] = $ident['authorzone'] ?? null;

        $desc = static::getNodeAttributesValues($domDocument->getElementsByTagName('desc')->item(0));
        $properties['environment'] = $desc['envir'] ?? null;
        $properties['mood'] = $desc['mood'] ?? null;
        $properties['type'] = $desc['type'] ?? null;
        $properties['mapType'] = $desc['maptype'] ?? null;
        $properties['mapStyle'] = $desc['mapstyle'] ?? null;
        $properties['validated'] = (bool) $desc['validated'] ?? null;
        $properties['nbLaps'] = (int) $desc['nblaps'] ?? null;
        $properties['displayCost'] = (int) $desc['displaycost'] ?? null;
        $properties['mod'] = $desc['mod'] ?? null;
        $properties['hasGhostBlocks'] = (bool) $desc['hasghostblocks'] ?? null;

        if ($domDocument->getElementsByTagName('playermodel')->length) {
            $playerModel = static::getNodeAttributesValues($domDocument->getElementsByTagName('playermodel')->item(0));
            $properties['playerModel'] = $playerModel['id'] ?? null;
        }

        $times = static::getNodeAttributesValues($domDocument->getElementsByTagName('times')->item(0));
        $properties['bronzeMedal'] = (int) $times['bronze'] ?? null;
        $properties['silverMedal'] = (int) $times['silver'] ?? null;
        $properties['goldMedal'] = (int) $times['gold'] ?? null;
        $properties['authorTime'] = (int) $times['authortime'] ?? null;
        $properties['authorScore'] = (int) $times['authorscore'] ?? null;

        $dependenciesNode = $domDocument->getElementsByTagName('deps')->item(0);
        foreach ($dependenciesNode->childNodes as $childNode) {
            if ($childNode->nodeName == 'dep') {
                $depAttributes = static::getNodeAttributesValues($childNode);
                $file = $depAttributes['file'];
                $url = $depAttributes['url'] ?? '';
                $properties['dependencies'][] = new Dependency($file, $url);
            }
        }

        return $properties;
    }

    protected static function parseThumbnailAndComment($fp)
    {
        $haveThumbnail = self::fetchLong($fp);
        $properties = [];
        if ($haveThumbnail) {
            $thumbSize = self::fetchLong($fp);
            self::ignore($fp, strlen('<Thumbnail.jpg>'));
            if ($thumbSize) {
                if (!extension_loaded('gd')) {
                    self::ignore($fp, $thumbSize);
                } else {
                    $temporaryStream = fopen('php://memory', 'wb+');
                    $gdImage = imagecreatefromstring(fread($fp, $thumbSize));
                    imageflip($gdImage, IMG_FLIP_VERTICAL);
                    imagejpeg($gdImage, $temporaryStream, 100);
                    $properties['thumbnail'] = new Thumbnail(stream_get_contents($temporaryStream, -1, 0));
                }
            }
            self::ignore($fp, strlen('</Thumbnail.jpg>'));
            self::ignore($fp, strlen('<Comments>'));
            $properties['comments'] = self::fetchString($fp);
            self::ignore($fp, strlen('</Comments>'));
        }
        return $properties;
    }
}
