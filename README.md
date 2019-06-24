# StreamParser

Wrapper for ReactPHP socket stream.
Allows encoding using length prefix or character deliminator (not tested), or none (for testing only).
Allows serializing using JSON or CBOR and if clientn communicates first will use that serializer.

## Installation

Add notioncommotion/stream-parser as a requirement to composer.json:

```javascript
{
    "require": {
       "notioncommotion/stream-parser": "^1.0"
    }
}
```

```
composer update
```

## Usage

### Server usage.

See the [server example](examples/server.php).

Values for the pem keys, and possibly others in [config.client.ini](examples/config.client.ini) will need to be update to reflect your requirements.  Be sure that log file locations are writable.

Current available values for encoder are lengthprefix (tested), lengthprefix (not tested), and no (i.e. none which is only used for testing only).
Current available values for serializer are JSON and [CBOR](https://cbor.io/).

```
ip                      =   0.0.0.0     ;Server listens on
port_tcp                =   1338        ;Leave blank to not use.
port_tls                =   1339        ;Leave blank to not use.
passphrase              =   ;optional key passphrase.  Uncomment if you use.
local_pk                =   /etc/pki/tls/private/sample-private-key.pem
local_cert              =   /etc/pki/tls/certs/sample-public-certificate.pem
local_pk_cert           =   /etc/pki/tls/private/sample-private-key-with-public-certificate.pem ;Both private key and public certificate
logMessages             =   0 ;If -1, do not log LengthPrefixStream, if value, log and crop, if zero, log and don't crop
summaryStreamLog        =   log/summary_stream.log ;If set, will write all read and write traphic to this file
rawReadStreamLog        =   log/raw_read_stream.log ;If set, will write all raw hex read traphic to this file
rawWriteStreamLog       =   log/raw_write_stream.log ;If set, will write all raw hex read traphic to this file
maxMessageLength        =   1000000     ;override for testing only.
encoder                 =   lengthprefix    ;lengthprefix/newline/no
serializers[]           =   cbor
serializers[]           =   json
```

```php
$stream=new NotionCommotion\StreamParser\ServerStreamParser(
    React\Stream\DuplexStreamInterface $stream,
    NotionCommotion\StreamParser\Encoders\EncoderInterface $encoder,
    [NotionCommotion\StreamParser\Serializer\SerializerInterface $serializer],
    array $settings
);
```

To start the example server, using the following from the command line:

```php server.php```


### Client usage.

See the [client example](examples/client.php).

Values for url, peer_name, the pem keys, and possibly others in [config.client.ini](examples/config.client.ini) will need to be update to reflect your requirements.  Be sure that log file locations are writable.

Current available values for encoder are lengthprefix (tested), lengthprefix (not tested), and no (i.e. none which is only used for testing only).
Current available values for serializer are JSON and [CBOR](https://cbor.io/).

```
url                     =   11.22.33.44
port                    =   1339            ;1338 is for TLS and 1339 is for TLS
peer_name               =   example.com
passphrase              =   ;optional key passphrase.  Uncomment if you use.
local_cert              =   /etc/pki/tls/certs/sample-public-certificate.pem    ;or ca
logMessages             =   0 ;If -1, do not log LengthPrefixStream, if value, log and crop, if zero, log and don't crop
summaryStreamLog        =   log/summary_stream.log ;If set, will write all read and write traphic to this file
rawReadStreamLog        =   log/raw_read_stream.log ;If set, will write all raw hex read traphic to this file
rawWriteStreamLog       =   log/raw_write_stream.log ;If set, will write all raw hex read traphic to this file
maxMessageLength        =   1000000 ;override for testing only
encoder                 =   lengthprefix   ;lengthprefix/newline/no
serializer              =   cbor   ;cbor/json
```

```php
$stream=new NotionCommotion\StreamParser\StreamParser(
    React\Stream\DuplexStreamInterface $stream,
    NotionCommotion\StreamParser\Encoders\EncoderInterface $encoder,
    NotionCommotion\StreamParser\Serializer\SerializerInterface $serializer,
    array $settings
);
```

Be sure server is started first, and then start example client using the following from the command line:

```php client.php```