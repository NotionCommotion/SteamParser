<?php
namespace NotionCommotion\StreamParser\Encoders;

interface EncoderInterface {

    /**
    * Add data from read event
    */
    public function addData(string $data);

    /**
    * Encode the data
    */
    public function pack(string $data):string;

    /**
    * Decode the data
    */
    public function unpack():?string;

    /**
    * Used on end event
    */
    public function packLastPackage();

    /**
    * Returns whether buffer is empty
    */
    public function isEmpty():bool;

    /**
    * Used for troubleshooting only
    */
    public function getLastRawRead():?string;

    /**
    * Used for troubleshooting only
    */
    public function getLastRawWrite():?string;
}
