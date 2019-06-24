<?php
namespace NotionCommotion\StreamParser\Serializers;

class JSONSerializer implements SerializerInterface {

    private
    $jsonEncodeOptions,     //standard json_encode() options
    $jsonDecodeOptions,     //standard json_decode() options
    $maxJsonDepth;          //Depth of JSON.

    public function __construct(int $jsonEncodeOptions=0, int $jsonDecodeOptions=0, int $maxJsonDepth=512) {
        $this->jsonEncodeOptions=$jsonDecodeOptions;
        $this->jsonDecodeOptions=$jsonDecodeOptions;
        $this->maxJsonDepth=$maxJsonDepth;
    }

    public function encode(array $msg):string {
        $msg = json_encode($msg, $this->jsonEncodeOptions, $this->maxJsonDepth);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new SerializerException('Message could not be converted to JSON.  '.json_last_error_msg()." (".json_last_error().')');
        }
        return $msg;
    }

    public function decode(string $msg):array {
        $msg=json_decode($msg, true, $this->maxJsonDepth, $this->jsonDecodeOptions);
        if (json_last_error() !== JSON_ERROR_NONE){
            throw new SerializerException('Message is not valid JSON.  '.json_last_error_msg()." (".json_last_error().')');
        }
        return $msg;
    }

   public function getName():string {
       return 'JSON';
   }
}
