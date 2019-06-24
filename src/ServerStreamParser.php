<?php
namespace NotionCommotion\StreamParser;

use React\Stream\DuplexStreamInterface;
use React\Stream\WritableStreamInterface;

class ServerStreamParser extends StreamParser {

    private $client;                    //Will be set after object is created

    public function __construct(DuplexStreamInterface $stream, Encoders\EncoderInterface $encoder, array $serializers, array $options=[]){
        if (!$serializers) {
            throw new \InvalidArgumentException('At least one serializer must be provided');
        }
        if(array_values($serializers) !== $serializers) {
            throw new \InvalidArgumentException('Serializer array must be sequencial');
        }
        foreach($serializers as $serializer) {
            if (!$serializer instanceof Serializers\SerializerInterface) {
                throw new \InvalidArgumentException('Invalid serializer '.get_class($serializerr));
            }
        }
        $this->serializer=$serializers;
        $this->construct($stream, $encoder, $options);
    }

    protected function encode(array $msg):string {
        if(is_array($this->serializer)) $this->serializer=$this->serializer[0];
        return $this->serializer->encode($msg);
    }

    protected function decode(string $msg):array {
        if(is_array($this->serializer)) {
            //First time communication.  Check if any seriallizers work
            $errors=[];
            foreach($this->serializer as $serializer) {
                try{
                    $msg=$serializer->decode($msg);
                    $this->serializer=$serializer;
                    $this->debug('Encoding type: '.$serializer->getName());
                    return $msg;
                }
                catch(\Exception $e) {
                    $errors[]=$e->getMessage();
                }
            }
            throw new Serializers\SerializerException('Cannot decode: '.implode(', ', $errors));
        }
        return $this->serializer->decode($msg);
    }

    public function setClient(ClientInterface $client):self{
        $this->client=$client;
        return $this;
    }
    public function getClient():ClientInterface{
        return $this->client;
    }

    protected function debug(string $msg, int $format=LOG_INFO) {
        if(!is_null($this->logMessages) || $format!==LOG_INFO) {
            $name = $this->client?$this->client->getClientType():'No Client';
            $msg=$this->logMessages?substr($msg, 0, $this->logMessages):$msg;
            syslog($format, "Debug ($name): $msg");
            if(php_sapi_name() === 'cli') {
                echo("Debug ($name): $msg\n");
            }
        }
    }
}
