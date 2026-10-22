<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Xmlrpc;

if (extension_loaded('xmlrpc')) {
    abstract class Request
    {
        private static $options = array(
            'encoding' => 'utf-8',
            'escaping' => 'markup',
            'verbosity' => 'no_white_space'
        );

        /**
         * @param string $method
         * @param mixed[] $args
         * @return string
         */
        static function encode($method, $args, $escape = true)
        {
            $opts = self::$options;
            if (!$escape) {
                $opts['escaping'] = array();
            }
            return xmlrpc_encode_request($method, $args, $opts);
        }

        /**
         * @param string $message
         * @return mixed
         * @throws ParseException
         */
        static function decode($message)
        {
            $value = xmlrpc_decode_request($message, $method, 'utf-8');
            if ($value === null) {
                throw new ParseException();
            }

            if ($method === null) {
                if (is_array($value) && xmlrpc_is_fault($value)) {
                    return array('fault', $value);
                }
                return array('response', $value);
            }
            return array('call', array($method, $value));
        }
    }
} else {
    abstract class Request
    {
        const DATE_FORMAT = 'Ymd\TH:i:s';

        /**
         * @param string $method
         * @param mixed[] $args
         * @return string
         */
        static function encode($method, $args, $escape = true)
        {
            $xml = '<?xml version="1.0" encoding="utf-8"?><methodCall><methodName>' . self::escape($method, $escape) . '</methodName>';
            if (!$args) {
                return $xml . '<params/></methodCall>';
            }

            $xml .= '<params>';
            foreach ($args as $arg) {
                $xml .= '<param><value>' . self::encodeValue($arg, $escape) . '</value></param>';
            }
            return $xml . '</params></methodCall>';
        }

        /**
         * @param mixed $v
         * @return string
         */
        private static function encodeValue($v, $escape = true)
        {
            switch (gettype($v)) {
                case 'boolean':
                    return '<boolean>' . ((int)$v) . '</boolean>';
                case 'integer':
                    return '<int>' . $v . '</int>';
                case 'double':
                    return '<double>' . $v . '</double>';
                case 'string':
                case 'NULL':
                    if (!$v && $v != "0") {
                        return '<string/>';
                    }
                    return '<string>' . self::escape($v, $escape) . '</string>';
                case 'object':
                    if ($v instanceof Base64) {
                        if (!$v->scalar) {
                            return '<base64/>';
                        }
                        return '<base64>' . base64_encode($v->scalar) . '</base64>';
                    }
                    if ($v instanceof \DateTime) {
                        return '<dateTime.iso8601>' . $v->format(self::DATE_FORMAT) . '</dateTime.iso8601>';
                    }
                    $v = get_object_vars($v);
                // fallthrough
                case 'array':
                    // empty array case
                    if (!$v) {
                        return '<array><data/></array>';
                    }
                    $return = '';
                    // pure array case
                    if (array_keys($v) === range(0, count($v) - 1)) {
                        foreach ($v as $item) {
                            $return .= '<value>' . self::encodeValue($item, $escape) . '</value>';
                        }
                        return '<array><data>' . $return . '</data></array>';
                    }
                    // else it's a struct
                    foreach ($v as $name => $value) {
                        $return .= '<member><name>' . self::escape($name, $escape) . '</name><value>' . self::encodeValue($value, $escape) . '</value></member>';
                    }
                    return '<struct>' . $return . '</struct>';
            }
            return '';
        }

        /**
         * @param string $str
         * @param bool $escape
         * @return string
         */
        private static function escape($str, $escape = true)
        {
            if ($escape) {
                return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $str) . ']]>';
            }
            return $str;
        }

        /**
         * @param string $message
         * @return mixed
         * @throws ParseException
         */
        static function decode($message)
        {
            $xml = @simplexml_load_string($message);
            if (!$xml) {
                throw new ParseException();
            }

            if ($xml->getName() == 'methodResponse') {
                if ($xml->fault) {
                    return array('fault', self::decodeValue($xml->fault->value));
                }
                return array('response', self::decodeValue($xml->params->param->value));
            }
            $params = array();
            foreach ($xml->params->param as $param) {
                $params[] = self::decodeValue($param->value);
            }
            return array('call', array((string)$xml->methodName, $params));
        }

        /**
         * @param \SimpleXMLElement $elt
         * @return mixed
         */
        private static function decodeValue($elt)
        {
            $elt = $elt->children();
            $elt = $elt[0];
            switch ($elt->getName()) {
                case 'boolean':
                    return (bool)(int)$elt;
                case 'i4':
                case 'int':
                    return (int)$elt;
                case 'double':
                    return (float)$elt;
                case 'string':
                    return (string)$elt;
                case 'base64':
                    return new Base64(base64_decode($elt));
                case 'dateTime.iso8601':
                    return \DateTime::createFromFormat(self::DATE_FORMAT, (string)$elt);
                case 'array':
                    $arr = array();
                    foreach ($elt->data->value as $v) {
                        $arr[] = self::decodeValue($v);
                    }
                    return $arr;
                case 'struct':
                    $struct = array();
                    foreach ($elt as $member) {
                        $struct[(string)$member->name] = self::decodeValue($member->value);
                    }
                    return $struct;
            }
        }
    }
}

class ParseException extends Exception
{
}
