<?php

function configureServer(React\Socket\ServerInterface $server, array $serializers, $loop, array $settings, string $protocol) {
    $server->on('connection', function (React\Socket\ConnectionInterface $stream) use($loop, $serializers, $settings, $protocol) {
        //$stream->on('data', function ($data) {onRawData($data, 'SocketServer');});
        $encoder = getEncoder($settings['encoder'], $settings);    //current options are LengthPrefix, NewLine, and No
        $stream=new NotionCommotion\StreamParser\ServerStreamParser($stream, $encoder, $serializers, getParserSettings($settings));

        logger('SocketServer onConnect with '.$stream->getRemoteAddress().' using '.$protocol);

        $stream->on('data', function ($data) use ($stream) {
            $json=json_encode($data);
            logger('SocketServer onData: '.(strlen($json)<100?$json:'strlen: '.strlen($json)));
            $stream->writeArray(array_flip($data));
            //$stream->close();
        });
        $stream->on('error', function(\Exception $e) use($stream) {
            logger('SocketServer onError: '.$e->getMessage());
            $stream->close();
        });
        $stream->on('close', function() {
            logger("SocketServer onClose");
        });
        $stream->on('end', function() {
            logger("SocketServer onEnd");
        });
        $stream->on('drain', function() {
            logger("SocketServer onDrain");
        });
        $stream->on('pipe', function() {
            logger("SocketServer onPipe");
        });
    });
    $server->on('error', function (\Exception $e) {
        logger('SocketServer onServererror: '.$e->getMessage());
        exit;
    });
}

require_once('common.php');
$settings=getSettings('config.server.ini');

$loop = React\EventLoop\Factory::create();

$serializers=[];
foreach($settings['serializers'] as $serializer) {
    $serializers[]=getSerializer($serializer);
}

if(!isset($settings['port_tcp']) && !isset($settings['port_tls'])) exit('At least one port must be set');

if($settings['port_tcp']) {
    $ipPort=$settings['ip'].':'.$settings['port_tcp'];
    $server = new React\Socket\TcpServer($ipPort, $loop);
    configureServer($server, $serializers, $loop, $settings, 'TCP');
    logger("Status: Server Running on $ipPort using TCP");
}
if($settings['port_tls']) {
    $ipPort=$settings['ip'].':'.$settings['port_tls'];
    $arr=['local_cert' => $settings['local_pk_cert']]; //Either use certificate file with private key, or also include local_pk
    if($settings['passphrase']) $arr['passphrase']=$settings['passphrase'];
    $secureServer = new React\Socket\SecureServer(new React\Socket\TcpServer($ipPort, $loop), $loop, $arr );
    configureServer($secureServer, $serializers, $loop, $settings, 'TLS');
    logger("Status: Server Running on $ipPort using TLS");
}

$loop->run();
