<?php
namespace NotionCommotion\StreamParser\Encoders;

class NewLineEncoder implements EncoderInterface {

    private $buffer='',
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
        return $data.PHP_EOL;
    }

    public function unpack():?string {
        if (($newline = strpos($this->buffer, PHP_EOL)) !== false && $newline <= $this->maxlength) {
            $data = (string)substr($this->buffer, 0, $newline);
            $this->lastRawRead=$data;
            $this->buffer = (string)substr($this->buffer, $newline + 1);
            return $data;
        }
        $this->lastRawRead=null;
        if (isset($this->buffer[$this->maxlength])) {
            throw new \OverflowException('Buffer size exceeded');
        }
        return null;
    }

    public function packLastPackage() {
        $this->buffer+=PHP_EOL;
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
