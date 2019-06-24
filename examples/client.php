<?php

require_once('common.php');
function makeArray(int $d):array {
    //Array ( [0] => 49 [1] => 481 [2] => 4901 [3] => 50001 [4] => 510001 [5] => 5200001 )
    $arr=[];
    $cnt=pow(10,$d);
    for ($i = 0; $i < $cnt; $i++) {
        $arr['property_'.sprintf("%0{$d}d", $i)]=md5(rand());
    }
    return $arr;
}

$settings=getSettings('config.client.ini');

$loop = React\EventLoop\Factory::create();
$connector = new React\Socket\Connector($loop);
if($settings['port']==1339) {
    $arr=[
        'cafile' => $settings['local_cert'],  //Use public cert since selfsigned?  Why does even test_ss_csr.pem work?
        'peer_name' => $settings['peer_name'],
        'allow_self_signed'=>true,
    ];
    if($settings['passphrase']) $arr['passphrase']=$settings['passphrase'];
    $connector = new React\Socket\SecureConnector($connector, $loop, $arr);
}
$ipPort=$settings['url'].':'.$settings['port'];
logger("Attempting to connection to $ipPort using ".($settings['port']==1339?'TLS':' TCP'));
$connector->connect($ipPort)->then(function (React\Socket\ConnectionInterface $stream) use($loop, $settings){
    logger('SocketClient onConnect');
    //$stream->on('data', function ($data) {onRawData($data, 'SocketClient');});

    $encoder = getEncoder($settings['encoder'], $settings);    //current options are LengthPrefix, NewLine, and No
    $serializer = getSerializer($settings['serializer']);    //current options are cbor or json
    $stream=new NotionCommotion\StreamParser\StreamParser($stream, $encoder, $serializer, getParserSettings($settings));

    $stream->on('data', function ($data) {
        $json=json_encode($data);
        logger('SocketClient onData: '.(strlen($json)<100?$json:'strlen: '.strlen($json)));
    });

    $stream->writeArray(makeArray(4));
    $timer = $loop->addPeriodicTimer(5, function () use($stream) {
        $stream->writeArray(makeArray(4));
    });

    $stream->on('error', function(\Exception $e) {
        logger('SocketClient onError: '.$e->getMessage());
        exit;
    });

    $stream->on('close', function() use ($timer, $loop){
        logger("SocketClient onClose");
        $loop->cancelTimer($timer);
    });

    $stream->on('end', function() {
        logger("SocketClient onEnd");
    });
    $stream->on('drain', function() {
        logger("SocketClient onDrain");
    });
    $stream->on('pipe', function() {
        logger("SocketClient onPipe");
    });

    //$loop->addTimer(5, function(){exit('No connection');});
})->otherwise(function(\RuntimeException $error){
    logger('no connection');
    exit;
});

$loop->run();
