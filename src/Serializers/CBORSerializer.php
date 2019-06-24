<?php
namespace NotionCommotion\StreamParser\Serializers;

class CBORSerializer implements SerializerInterface {

    public function encode(array $msg):string {
        $msg = \CBOR\CBOREncoder::encode($msg);
        if ( $msg === false){
            throw new SerializerException('Message could not be converted to CBOR');
        }
        return $msg;
    }

    public function decode(string $msg):array {
        $msg = \CBOR\CBOREncoder::decode($msg);
        if(!is_array($msg)){
            throw new SerializerException('Message is not valid CBOR');
        }
        return $msg;
    }

   public function getName():string {
       return 'CBOR';
   }
}
