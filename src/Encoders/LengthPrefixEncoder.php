<?php
namespace NotionCommotion\StreamParser\Encoders;

class LengthPrefixEncoder implements EncoderInterface {

    private $buffer='',
    $bufferLength=0,
    $messageLength=0,
    $maxMessageLength,
    $maxBufferLength,
    $lastRawRead,
    $lastRawWrite;

    public function __construct(?int $maxMessageLength=65535, ?int $maxBufferLength=16777215) {
        $this->maxBufferLength=$maxBufferLength;
        $this->maxMessageLength=$maxMessageLength;
    }

    public function addData(string $data) {
        $this->bufferLength+=strlen($data);
        if ($this->bufferLength > $this->maxBufferLength) {
            throw new EncoderException('Read buffer size exceeded');
        }
        $this->buffer.=$data;
    }

    public function pack(string $data):string {
        $this->lastRawWrite=$data;
        return pack("V", strlen($data)).$data;
    }

    public function unpack():?string {
        if(!$this->messageLength) {
            if($this->bufferLength > 4) {
                $this->messageLength = unpack('Vlen', substr($this->buffer, 0, 4))['len'];
                if(!is_int($this->messageLength)) {
                    throw new EncoderException('Message length is not an integer');
                }
                if ($this->messageLength > $this->maxMessageLength) {
                    throw new EncoderException("Receive message size exceeded $this->messageLength > $this->maxMessageLength");
                }
            }
            else {
                $this->lastRawRead=null;
                return null;
            }
        }
        if($this->bufferLength >= 4 + $this->messageLength) {
            $msg=substr($this->buffer, 4, $this->messageLength);
            $this->lastRawRead=substr($this->buffer, 0, $this->messageLength+4);
            $this->buffer = substr($this->buffer, 4 + $this->messageLength);
            $this->bufferLength -= (4 + $this->messageLength);
            $this->messageLength=0;
            return $msg;
        }
        //syslog(LOG_INFO, "bufferLength: $this->bufferLength messageLength: $this->messageLength");
        $this->lastRawRead=null;
        return null;
    }

    public function packLastPackage() {
        $this->messageLength=strlen($this->buffer);
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
