<?php
namespace NotionCommotion\StreamParser\Encoders;

class NoEncoder implements EncoderInterface {

    private $buffer,
    $maxMessageLength,
    $maxBufferLength,
    $lastRawRead,
    $lastRawWrite;

    public function __construct(?int $maxMessageLength=65535, ?int $maxBufferLength=16777215) {
        $this->maxBufferLength=$maxBufferLength;
        $this->maxMessageLength=$maxMessageLength;
    }

    public function addData(string $data) {
        $this->buffer .= $data;
    }

    public function pack(string $data):string {
        $this->lastRawWrite=$data;
        return $data;
    }

    public function unpack():?string {
        $data=$this->buffer;
        $this->lastRawRead=$data;
        $this->buffer='';
        return $data;
    }

    public function packLastPackage() {
    }

    public function isEmpty():bool {
        return !boolval($this->buffer);
    }

    public function getLastRawWrite():?string {
        return $this->lastRawWrite;
    }

    public function getLastRawRead():?string {
        return $this->lastRawRead;
    }
}
