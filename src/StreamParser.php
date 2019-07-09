<?php
namespace NotionCommotion\StreamParser;

use Evenement\EventEmitter;
use React\Stream\DuplexStreamInterface;
use React\Stream\WritableStreamInterface;

/*
Parses stream based on length prefix, and emits data or error if bad JSON is received.
*/
class StreamParser extends EventEmitter implements DuplexStreamInterface {

    private
    $stream=false,
    $encoder,                   //New line, length prefix, etc

    //The following are defined by injected $options array
    $summaryStreamLog,          //Log file location to log summary messages along with binary
    $rawReadStreamLog,          //Log file location to log raw hex read messages (will be deleted upon start)
    $rawWriteStreamLog,
    $maxWriteChunkLength=2000,      //Will break in parts
    $maxMessageLength=65535,
    $maxWriteBufferLength=16777215, //Maximum length of the write buffer

    $writeBuffer,               //internally used
    $timestamp;                 //When initiated

    protected
    $serializer,                //Encode and decode using JSON, CBOR, etc.
    $logMessages=null;          //If null, do not log.  If zero, log but don't crop message.  Else log and crop.

    public function __construct(DuplexStreamInterface $stream, Encoders\EncoderInterface $encoder, Serializers\SerializerInterface $serializer, array $options=[]){
        $this->serializer=$serializer;
        $this->construct($stream, $encoder, $options);
    }

    protected function construct($stream, $encoder, $options){

        $this->stream=$stream;
        $this->encoder=$encoder;

        if ($err=array_diff(array_keys($options), ['logMessages', 'summaryStreamLog', 'rawReadStreamLog', 'rawWriteStreamLog', 'maxMessageLength','maxReadBufferLength'])) {
            throw new \InvalidArgumentException('Invalid options: '.implode(', ', $err));
        }
        if(isset($options['logMessages'])) {
            $options['logMessages']=$options['logMessages']==-1?null:(int) $options['logMessages'];
        }
        foreach($options as $option=>$value) {
            $this->$option=$value;
        }

        //Raw log files are binary and thus will be difficult to view if it gets too big.
        if($this->rawReadStreamLog) $this->emptyFile($this->rawReadStreamLog);
        if($this->rawReadStreamLog) $this->emptyFile($this->rawWriteStreamLog);

        $this->timestamp=time();

        if (!$stream->isWritable() || !$stream->isReadable()) {
            throw new \RuntimeException('Non readable or writable stream');
            return $this->close();
        }

        //\React\Stream\Util::forwardEvents($stream, $this, ['error', 'pipe']);
        $stream->on('data',  [$this, 'handleData']);  //Read only
        $stream->on('drain', [$this, 'handleDrain']); //Write only
        $stream->on('error', [$this, 'handleError']); //Both
        $stream->on('close', [$this, 'handleClose']); //Both
        $stream->on('end',   [$this, 'handleEnd']);   //Read only
        $stream->on('pipe',  [$this, 'handlePipe']);  //Write only
    }

    /**
    * Write to duplex stream and return true.  If stream buffer is full, send to array buffer and return false.
    *
    * @param mixed $data
    */
    public function write($data) {
         if($this->stream && $this->stream->isWritable()) {
            if (strlen($data) > $this->maxMessageLength) {
                $this->handleError(new \OverflowException('Write message size exceeded'));
            }
            $package=$this->encoder->pack($data);
            if($this->summaryStreamLog) {
                try{
                    $this->logSummaryStream('write', json_encode($this->decode($data)), $package);
                }
                catch(\Exception $e) {
                    $this->handleError($e);
                }
            }
            $this->writeBuffer.=$package;
            if (strlen($this->writeBuffer) > $this->maxWriteBufferLength) {
                $this->handleError(new \OverflowException('Write buffer size exceeded'));
            }
            //Tecnically, this is not necessarly being sent to the stream.  Maybe move to _write()?
            $this->debug('write strlen: '.strlen($package));
            if($this->rawWriteStreamLog) {
                //Or base64_encode() which uses less space and can compare whole values easily, but requires decoding to do detailed comparisons.
                file_put_contents($this->rawWriteStreamLog, bin2hex($package),  FILE_APPEND);
            }
            return $this->_write();

            if($this->writeBuffer) {
                $this->debug('StreamParser::write() Buffer still accepting data', LOG_ERR);
                return false;
            }
            else {
                return true;
            }

        }
        throw new \RuntimeException('Attempting to write to a non-writable stream');
    }

    private function _write():bool {
        while ($chunk=substr($this->writeBuffer, 0, $this->maxWriteChunkLength)) {
            $this->debug('attempting to write string of length: '.strlen($chunk));
            if($this->stream->write($chunk)) {
                $this->writeBuffer=substr($this->writeBuffer, strlen($chunk));
                if(!$this->writeBuffer) {
                    return true;
                }
            }
            else {
                $this->debug('Write stream not accepting data', LOG_ERR);
                return false;
            }
        }
        return true;    //Exact length?
    }

    /** @internal */
    public function handleDrain() {
        $this->debug('handleDrain');
        $this->emit('drain');
        $this->_write();
    }

    /** @internal */
    public function handleData(string $data) {
        if($this->rawReadStreamLog) {
            //Or base64_encode() which uses less space and can compare whole values easily, but requires decoding to do detailed comparisons.
            file_put_contents($this->rawReadStreamLog, bin2hex($data),  FILE_APPEND | LOCK_EX);
        }
        try{
            $this->encoder->addData($data);
            while($package=$this->encoder->unpack()){
                try{
                    $msg=$this->decode($package);
                    //$this->debug('handleData message: '.json_encode($msg));
                    if($this->summaryStreamLog) {
                        $this->logSummaryStream('read', json_encode($msg), $this->encoder->getLastRawRead());
                    }
                    $this->emit('data', [$msg]);
                }
                catch(\Exception $e) {
                    $this->handleError($e);
                }
            }
        }
        catch(\Exception $e) {
            $this->debug('handleData error: '.$e->getMessage(), LOG_ERR);
            $this->handleError($e);
        }
    }

    /** @internal */
    public function handleError(\Exception $error) {
        $this->emit('error', [$error]);
    }

    public function close() {
        $this->stream->close();
    }
    /** @internal */
    public function handleClose() {
        //$this->readBuffer = '';
        //$this->writeBuffer = '';
        //$this->stream = false;
        $this->emit('close');
        $this->removeAllListeners();
    }

    public function end($data = null) {
        //Any direct calls will bypass and attempt to send without length prefix.  Probably not an issue.
        if ($data !== null) {
            $this->write($data);
        }
        $this->stream->end();
    }
    /** @internal */
    public function handleEnd() {
        if (!$this->encoder->isEmpty()) {
            $this->encoder->packLastPackage();
            $this->handleData('');
        }
        $this->emit('end');
    }

    public function pause() {
        $this->stream->pause();
    }
    public function resume() {
        $this->stream->resume();
    }

    public function pipe(WritableStreamInterface $dest, array $options = []) {
        return $this->stream->pipe($dest, $options);
    }
    public function handlePipe() {
        $this->emit('pipe');
    }

    public function isReadable() {
        return $this->stream->isReadable();
    }
    public function isWritable() {
        return $this->stream->isWritable();
    }

    // Client methods

    public function writeArray(array $data):self {
        try{
            if($data = $this->encode($data)) {
                $this->write($data);
            }
        }
        catch(\Exception $e) {
            $this->handleError($e);
        }
        return $this;
    }

    public function getRemoteAddress():string{
        return $this->stream->getRemoteAddress();
    }
    public function getLocalAddress():string{
        return $this->stream->getLocalAddress();
    }

    public function getTimestamp():int {
        return $this->timestamp;
    }

    public function isConnected():bool {
        return boolval($this->stream);
    }

    //Testers
    public function testWrite(string $string) {
        $this->stream->write($string);
    }

    protected function debug(string $msg, int $format=LOG_INFO) {
        if(!is_null($this->logMessages) || $format!==LOG_INFO) {
            $msg=$this->logMessages?substr($msg, 0, $this->logMessages):$msg;
            syslog($format, "Debug: $msg");
            if(php_sapi_name() === 'cli') {
                echo("Debug: $msg\n");
            }
        }
    }

    /**
    * encode() and decode() be overwritten by ServerStreamParser
    */
    protected function encode(array $msg):string {
        return $this->serializer->encode($msg);
    }
    protected function decode(string $msg):array {
        return $this->serializer->decode($msg);
    }

    /**
    * $type will be read or write.  If write, also log decompressed version
    *
    * @param mixed $type
    * @param mixed $data
    * @return void
    */
    private function logSummaryStream(string $type, string $msg, ?string $rawData) {
        //Will only be called after a full message is received.
        $date = date('m/d/Y h:i:s a', time());
        $serializer=is_array($this->serializer)?'Unknown':$this->serializer->getName();
        $strlen=strlen($msg);
        $rawDataEncoded = bin2hex($rawData); //Or base64_encode() which uses less space and can compare whole values easily, but requires decoding to do detailed comparisons.
        $s="$date $type $serializer $strlen bytes\n";   //$rawDataEncoded\n\n";
        file_put_contents($this->summaryStreamLog, $s,  FILE_APPEND | LOCK_EX);
    }

    private function emptyFile($file){
        $f = @fopen($file, "r+");
        if ($f !== false) {
            ftruncate($f, 0);
            fclose($f);
        }
    }

}
