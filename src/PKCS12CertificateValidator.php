<?php

namespace Ondrejnov\EET;

use Exception;
use Ondrejnov\EET\Exceptions\CertificateValidityException;
use Ondrejnov\EET\Exceptions\ServerException;

/**
 * Class PKCS12CertificateValidator
 * @package Ondrejnov\EET
 *
 * IMPORTANT: to make OpenSSL work properly you must set the OPENSSL_CONF variable using
 * setx -m OPENSSL_CONF "C:\xampp\apache\conf\openssl.cnf" on windows
 */
class PKCS12CertificateValidator{
    /**
     * @var string
     */
    private $x509;

    /**
     * @var string
     */
    private $private_key;

    /**
     * @var Dispatcher
     */
    private $dispatcher;


    function __construct($pkcs12_file, $password){
        //Parse .p12 file
        $pkcs12 = null;
        if( !openssl_pkcs12_read(file_get_contents($pkcs12_file), $pkcs12, $password) ) {
            throw new CertificateValidityException("Could not read the pkcs12 file.", 1);
        }

        // Get a certificate
        if( !openssl_x509_export($pkcs12['cert'], $this->x509) ) {
            throw new CertificateValidityException("Could not get x509 certificate from pkcs12 file.", 2);
        }

        // Get a private key
        if( !openssl_pkey_export($pkcs12['pkey'], $this->private_key) ) {
            throw new CertificateValidityException("Could not get private key from pkcs12 file.", 3);
        }
    }

    /**
     * validate given receipt over given service.
     * @param $service
     * @param Receipt $receipt
     * @param bool $trace
     *
     * @throws ServerException
     * @throws Exception
     *
     * @return bool|string
     */
    public function validate($service, Receipt $receipt, $trace = false){
        $this->dispatcher = new Dispatcher($service, $this->private_key, $this->x509, null, false);
        $this->dispatcher->trace = $trace;

        return $this->dispatcher->send($receipt, true); // Send request as test not real data
    }

    /**
     * @return string X509 certificate in string format
     */
    public function getX509(){
        return $this->x509;
    }

    /**
     * @return string private key certificate in string format
     */
    public function getPrivateKey(){
        return $this->private_key;
    }

    /**
     * @return Dispatcher class for accessing warnings, etc...
     */
    public function getDispatcher(){
        return $this->dispatcher;
    }
}