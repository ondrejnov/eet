<?php

namespace Ondrejnov\EET;

use Ondrejnov\EET\Exceptions\ClientException;
use Ondrejnov\EET\Exceptions\RequirementsException;
use Ondrejnov\EET\Exceptions\ServerException;
use Ondrejnov\EET\SoapClient;
use Ondrejnov\EET\Utils\Format;
use RobRichards\XMLSecLibs\XMLSecurityKey;

/**
 * Receipt for Ministry of Finance
 */
class Dispatcher {

    /**
     * Certificate key
     * @var string
     */
    private $key;

    /**
     * Certificate
     * @var string
     */
    private $cert;

    /**
     * WSDL path or URL
     * @var string
     */
    private $service;

    /**
     * @var boolean
     */
    public $trace;

    /**
     *
     * @var SoapClient
     */
    private $soapClient;

    /**
     * 
     * @param string $key
     * @param string $cert
     */
    public function __construct($service, $key, $cert) {
        $this->service = $service;
        $this->key = $key;
        $this->cert = $cert;
        $this->checkRequirements();
    }

    /**
     * 
     * @param string $service
     * @param Receipt $receipt
     * @return boolean|string
     */
    public function check(Receipt $receipt) {
        try {
            return $this->send($receipt, TRUE);
        } catch (ServerException $e) {
            return FALSE;
        }
    }

    /**
     * 
     * @param boolean $tillLastRequest optional If not set/FALSE connection time till now is returned.
     * @return float
     */
    public function getConnectionTime($tillLastRequest = FALSE) {
        !$this->trace && $this->throwTraceNotEnabled();
        return $this->getSoapClient()->__getConnectionTime($tillLastRequest);
    }

    /**
     * 
     * @return int
     */
    public function getLastResponseSize() {
        !$this->trace && $this->throwTraceNotEnabled();
        return mb_strlen($this->getSoapClient()->__getLastResponse(), '8bit');
    }

    /**
     * 
     * @return int
     */
    public function getLastRequestSize() {
        !$this->trace && $this->throwTraceNotEnabled();
        return mb_strlen($this->getSoapClient()->__getLastRequest(), '8bit');
    }

    /**
     * 
     * @return float time in ms
     */
    public function getLastResponseTime() {
        !$this->trace && $this->throwTraceNotEnabled();
        return $this->getSoapClient()->__getLastResponseTime();
    }

    /**
     * 
     * @throws ClientException
     */
    private function throwTraceNotEnabled() {
        throw new ClientException('Trace is not enabled! Set trace property to TRUE.');
    }

    /**
     * 
     * @param \Ondrejnov\EET\Receipt $receipt
     * @return array
     */
    public function getCheckCodes(Receipt $receipt) {
        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $objKey->loadKey($this->key, TRUE);

        $arr = [
            $receipt->dic_popl,
            $receipt->id_provoz,
            $receipt->id_pokl,
            $receipt->porad_cis,
            $receipt->dat_trzby->format('c'),
            Format::price($receipt->celk_trzba)
        ];
        $sign = $objKey->signData(join('|', $arr));

        return [
            'pkp' => [
                '_' => $sign,
                'digest' => 'SHA256',
                'cipher' => 'RSA2048',
                'encoding' => 'base64'
            ],
            'bkp' => [
                '_' => Format::BKB(sha1($sign)),
                'digest' => 'SHA1',
                'encoding' => 'base16'
            ]
        ];
    }

    /**
     * 
     * @param Receipt $receipt
     * @param boolean $check
     * @return boolean|string
     */
    public function send(Receipt $receipt, $check = FALSE) {
        $this->initSoapClient();

        $response = $this->processData($receipt, $check);

        isset($response->Chyba) && $this->processError($response->Chyba);

        return $check ? TRUE : $response->Potvrzeni->fik;
    }

    /**
     * 
     * @throws RequirementsException
     * @return void
     */
    private function checkRequirements() {
        if (!class_exists('\SoapClient')) {
            throw new RequirementsException('Class SoapClient is not defined! Please, allow php extension php_soap.dll in php.ini');
        }
    }

    /**
     * Get (or if not exists: initialize and get) SOAP client.
     * 
     * @return SoapClient
     */
	public function getSoapClient() {
        !isset($this->soapClient) && $this->initSoapClient();
        return $this->soapClient;
    }

    /**
     * Require to initialize a new SOAP client for a new request.
     * 
     * @return void
     */
    private function initSoapClient() {
    	if ($this->soapClient === NULL) {
			$this->soapClient = new SoapClient($this->service, $this->key, $this->cert, $this->trace);
		}
    }

    public function prepareData($receipt, $check = FALSE) {
		$head = [
			'uuid_zpravy' => $receipt->uuid_zpravy,
			'dat_odesl' => time(),
			'prvni_zaslani' => $receipt->prvni_zaslani,
			'overeni' => $check
		];

		$body = [
			'dic_popl' => $receipt->dic_popl,
			'dic_poverujiciho' => $receipt->dic_poverujiciho,
			'id_provoz' => $receipt->id_provoz,
			'id_pokl' => $receipt->id_pokl,
			'porad_cis' => $receipt->porad_cis,
			'dat_trzby' => $receipt->dat_trzby->format('c'),
			'celk_trzba' => Format::price($receipt->celk_trzba),
			'zakl_nepodl_dph' => Format::price($receipt->zakl_nepodl_dph),
			'zakl_dan1' => Format::price($receipt->zakl_dan1),
			'dan1' => Format::price($receipt->dan1),
			'zakl_dan2' => Format::price($receipt->zakl_dan2),
			'dan2' => Format::price($receipt->dan2),
			'zakl_dan3' => Format::price($receipt->zakl_dan3),
			'dan3' => Format::price($receipt->dan3),
			'rezim' => $receipt->rezim
		];

		return [
			'Hlavicka' => $head,
			'Data' => $body,
			'KontrolniKody' => $this->getCheckCodes($receipt)
		];
	}

    /**
     * 
     * @param Receipt $receipt
     * @param boolean $check
     * @return object
     */
    private function processData(Receipt $receipt, $check = FALSE) {
        $data = $this->prepareData($receipt, $check);

        return $this->getSoapClient()->OdeslaniTrzby($data);
    }

    /**
     * @param $error
     * @throws ServerException
     */
    private function processError($error) {
        if ($error->kod) {
            $msgs = [
                -1 => 'Docasna technicka chyba zpracovani – odeslete prosim datovou zpravu pozdeji',
                2 => 'Kodovani XML neni platne',
                3 => 'XML zprava nevyhovela kontrole XML schematu',
                4 => 'Neplatny podpis SOAP zpravy',
                5 => 'Neplatny kontrolni bezpecnostni kod poplatnika (BKP)',
                6 => 'DIC poplatnika ma chybnou strukturu',
                7 => 'Datova zprava je prilis velka',
                8 => 'Datova zprava nebyla zpracovana kvuli technicke chybe nebo chybe dat',
            ];
            $msg = isset($msgs[$error->kod]) ? $msgs[$error->kod] : '';
            throw new ServerException($msg, $error->kod);
        }
    }

}
