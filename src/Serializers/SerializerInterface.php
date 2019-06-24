<?php
namespace NotionCommotion\StreamParser\Serializers;

interface SerializerInterface {

    public function encode(array $msg):string;

    public function decode(string $msg):array;

    /**
    * Returns name (i.e. JSON, CBOR, etc which will be included in summary logs)
    */
    public function getName():string;
}
