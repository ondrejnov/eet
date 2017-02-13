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
     * Certificate key passphrase
     * @var string
     */
    private $passphrase;

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
     * @var array [warning code => message]
     */
    private $warnings;

    /**
     * @var \stdClass
     */
    private $wholeResponse;
    
    /**
     * @var string
     */
    private $pkpCode;
    
    /**
     * @var string
     */
    private $bkpCode;

    /**
     *
     * @param string $key
     * @param string $cert
     */
    public function __construct($service, $key, $cert, $passphrase = NULL) {
        $this->service = $service;
        $this->key = $key;
        $this->cert = $cert;
        $this->passphrase = $passphrase;
        $this->warnings = array();
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
        if ($this->passphrase) {
            $objKey->passphrase = $this->passphrase;
        }
        $objKey->loadKey($this->key, TRUE);

        $arr = [
            $receipt->dic_popl,
            $receipt->id_provoz,
            $receipt->id_pokl,
            $receipt->porad_cis,
            $receipt->dat_trzby->format('c'),
            Format::price($receipt->celk_trzba)
        ];

        $this->pkpCode = $objKey->signData(join('|', $arr));
        $this->bkpCode = Format::BKP(sha1($this->pkpCode));

        return [
            'pkp' => [
                '_' => $this->pkpCode,
                'digest' => 'SHA256',
                'cipher' => 'RSA2048',
                'encoding' => 'base64'
            ],
            'bkp' => [
                '_' => $this->bkpCode,
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
        $this->wholeResponse = $response;

        isset($response->Chyba) && $this->processError($response->Chyba);
        isset($response->Varovani) && $this->warnings = $this->processWarnings($response->Varovani);
        return $check ? TRUE : $response->Potvrzeni->fik;
    }
    
    /**
     * Returns array of warnings if the last response contains any, empty array otherwise.
     * 
     * @return array [warning code => message]
     */
    public function getWarnings()
    {
        return $this->warnings;
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
            $this->soapClient = new SoapClient($this->service, $this->key, $this->cert, $this->trace, $this->passphrase);
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
            'cest_sluz' => Format::price($receipt->cest_sluz),
            'pouzit_zboz1' => Format::price($receipt->pouzit_zboz1),
            'pouzit_zboz2' => Format::price($receipt->pouzit_zboz2),
            'pouzit_zboz3' => Format::price($receipt->pouzit_zboz3),
            'urceno_cerp_zuct' => Format::price($receipt->urceno_cerp_zuct),
            'cerp_zuct' => Format::price($receipt->cerp_zuct),
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
                -1 => 'Dočasná technická chyba zpracování – odešlete prosím datovou zprávu později.',
                2 => 'Kódování XML není platné.',
                3 => 'XML zpráva nevyhověla kontrole XML schématu.',
                4 => 'Neplatný podpis SOAP zprávy.',
                5 => 'Neplatný kontrolní bezpečnostní kód poplatníka (BKP).',
                6 => 'DIČ poplatníka má chybnou strukturu.',
                7 => 'Datová zpráva je příliš velká.',
                8 => 'Datová zpráva nebyla zpracována kvůli technické chybě nebo chybě dat.',
            ];
            $msg = isset($msgs[$error->kod]) ? $msgs[$error->kod] : '';
            throw new ServerException($msg, $error->kod);
        }
    }

    /**
     * @param \stdClass|array $warnings
     * @return array [warning code => message]
     */
    private function processWarnings($warnings) {
        $result = array();
        if(\count($warnings) === 1) {
            $result[\intval($warnings->kod_varov)] = $this->getWarningMsg($warnings->kod_varov);
        } else {
            foreach ($warnings as $warning) {
                $result[\intval($warning->kod_varov)] = $this->getWarningMsg($warning->kod_varov);
            }
        }
        return $result;
    }

    /**
     * @param int $id warning code
     * @return string warning message
     */
    private function getWarningMsg($id)
    {
      $result = 'Neznámé varování, zkontrolujte technickou specifikaci.';
      $msgs = [
                1 => 'DIČ poplatníka v datové zprávě se neshoduje s DIČ v certifikátu.',
                2 => 'Chybný formát DIČ pověřujícího poplatníka.',
                3 => 'Chybná hodnota PKP.',
                4 => 'Datum a čas přijetí tržby je novější než datum a čas přijetí zprávy.',
                5 => 'Datum a čas přijetí tržby je výrazně v minulosti.',
            ];
      if (\array_key_exists( $id, $msgs )) {
          $result = $msgs[$id];
      }
      return $result;
    }

    /**
     * @return \stdClass
     */
    public function getWholeResponse()
    {
        return $this->wholeResponse;
    }

    /**
     * 
     * @return string
     */
    public function getPkpCode() {
        return base64_encode($this->pkpCode);
    }

    /**
     * 
     * @return string
     */
    public function getBkpCode() {
        return $this->bkpCode;
    }
    
    

}
