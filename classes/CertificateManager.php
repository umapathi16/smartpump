<?php

class CertificateManager
{
     
    private $caCertPath;

    private $clientCertPath;

    private $clientKeyPath;

    public function __construct()
    {

        $this->caCertPath =
            __DIR__
            . '/../storage/certs/ca.crt';

        $this->clientCertPath =
            __DIR__
            . '/../storage/certs/server.crt';

        $this->clientKeyPath =
            __DIR__
            . '/../storage/certs/server.key';
    }

    

    public function renewCACertificate(
        $certificate
    ): bool {

        return file_put_contents(

            $this->caCertPath,

            $certificate
        ) !== false;
    }

   

    public function renewClientCertificate(
        $certificate,
        $privateKey
    ): bool {

        $certSaved =
            file_put_contents(

                $this->clientCertPath,

                $certificate
            );

        $keySaved =
            file_put_contents(

                $this->clientKeyPath,

                $privateKey
            );

        return
            $certSaved !== false
            &&
            $keySaved !== false;
    }
}