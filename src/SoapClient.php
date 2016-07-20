<?php

namespace Ondrejnov\EET;

class SoapClient extends \SoapClient {

    /** @var string */
    private $key;

    /** @var string */
    private $cert;

    /** @var boolean */
    private $traceRequired;

    /** @var float */
    private $connectionStartTime;

    /** @var float */
    private $lastResponseStartTime;

    /** @var float */
    private $lastResponseEndTime;

    /** @var string */
    private $lastRequest;

    /**
     * 
     * @param string $service
     * @param string $key
     * @param string $cert
     * @param boolean $trace
     */
    public function __construct($service, $key, $cert, $trace = FALSE) {
        $this->connectionStartTime = microtime(TRUE);
        parent::__construct($service, [
            'exceptions' => TRUE,
            'trace' => $trace
        ]);
        $this->key = $key;
        $this->cert = $cert;
        $this->traceRequired = $trace;
    }

    public function __doRequest($request, $location, $saction, $version, $one_way = NULL) {
        $doc = new \DOMDocument('1.0');
        $doc->loadXML($request);

        $objWSSE = new \WSSESoap($doc);
        $objWSSE->addTimestamp();

        $objKey = new \XMLSecurityKey(\XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $objKey->loadKey($this->key, TRUE);
        $objWSSE->signSoapDoc($objKey, ["algorithm" => \XMLSecurityDSig::SHA256]);

        $token = $objWSSE->addBinaryToken(file_get_contents($this->cert));
        $objWSSE->attachTokentoSig($token);

        $this->traceRequired && $this->lastResponseStartTime = microtime(TRUE);

        $response = parent::__doRequest($this->lastRequest = $objWSSE->saveXML(), $location, $saction, $version);

        $this->traceRequired && $this->lastResponseEndTime = microtime(TRUE);

        return $response;
    }

    /**
     * 
     * @return float
     */
    public function __getLastResponseTime() {
        return $this->lastResponseEndTime - $this->lastResponseStartTime;
    }

    /**
     * 
     * @return float
     */
    public function __getConnectionTime($tillLastRequest = FALSE) {
        return $tillLastRequest ? $this->getConnectionTimeTillLastRequest() : $this->getConnectionTimeTillNow();
    }

    private function getConnectionTimeTillLastRequest() {
        if (!$this->lastResponseEndTime || !$this->connectionStartTime) {
            return NULL;
        }
        return $this->lastResponseEndTime - $this->connectionStartTime;
    }

    private function getConnectionTimeTillNow() {
        if (!$this->connectionStartTime) {
            return NULL;
        }
        return microtime(TRUE) - $this->connectionStartTime;
    }

    /**
     * @return string
     */
    public function __getLastRequest() {
        return $this->lastRequest;
    }

}
