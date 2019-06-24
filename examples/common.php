<?php
use NotionCommotion\StreamParser\Encoders;
use NotionCommotion\StreamParser\Serializers;

function getSettings(string $configFile):array {
    $settings=parse_ini_file($configFile, true);
    foreach(['summaryStreamLog', 'rawReadStreamLog', 'rawWriteStreamLog', 'local_pk', 'local_pk_cert', 'local_cert'] as $file) {
        if(isset($settings[$file]) && substr($settings[$file], 0, 1)!=='/') {
            $settings[$file]=__DIR__.DIRECTORY_SEPARATOR.$settings[$file];
        }
    }
    return $settings;
}

function getParserSettings(array $settings):array {
    return array_intersect_key($settings, array_flip(['serializerType', 'summaryStreamLog', 'rawReadStreamLog', 'rawWriteStreamLog', 'logMessages', 'maxMessageLength', 'maxReadBufferLength', 'maxJsonDepth']));
}

function logger(string $msg) {
    syslog(LOG_INFO, $msg);
    if(php_sapi_name() === 'cli') {
        echo($msg.PHP_EOL);
    }
}

function getEncoder(string $name, array $settings):Encoders\EncoderInterface {
    switch(strtolower($name)) {
        case 'lengthprefix':
            return new Encoders\LengthPrefixEncoder($settings['maxMessageLength']??null);
        case 'newline':
            //not complete
            return new Encoders\NewLineEncoder();
        case 'no':
            //testing only
            return new Encoders\NoEncoder();
        default: exit("Invalid encoder $name");
    }
}
function getSerializer(string $name):Serializers\SerializerInterface {
    switch(strtolower($name)) {
        case 'cbor':
            return new Serializers\CBORSerializer();
        case 'json':
            return new Serializers\JSONSerializer();
        default: exit("Invalid serializer $name");
    }
}

function onRawData(string $data, string $type) {
    static $totalLng=0;
    $curLng=strlen($data);
    $totalLng+=$curLng;
    logger("onData Raw $type: current: $curLng  total: $totalLng");
}

ini_set('display_startup_errors', '1');
ini_set('display_errors', '1');
ini_set ( 'memory_limit' , '-1' );
error_reporting(E_ALL);
require 'vendor/autoload.php';
