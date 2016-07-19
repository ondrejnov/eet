<?php

namespace Ondrejnov\EET;

use Ondrejnov\EET\Exceptions\RequirementsException;
use Ondrejnov\EET\Exceptions\ServerException;
use Ondrejnov\EET\SoapClient;
use Ondrejnov\EET\Utils\Format;

/**
 * Receipt for Ministry of Finance
 */
class Dispatcher {

    /**
     * Certificate key
     * @var string */
    private $key;

    /**
     * Certificate
     * @var string */
    private $cert;

    /**
     * Receipt for Ministry of Finance
     * @var Receipt */
    private $receipt;

    /**
     * 
     * @param string $key
     * @param string $cert
     */
    public function __construct($key, $cert) {
        $this->key = $key;
        $this->cert = $cert;
        $this->checkRequirements();
    }

    private function checkRequirements() {
        if (!class_exists('\SoapClient')) {
            throw new RequirementsException('Class SoapClient is not defined! Please, allow php extension php_soap.dll in php.ini');
        }
    }

    /**
     * 
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
     * @param Receipt $receipt
     * @param boolean $check
     * @return boolean|string
     */
    public function send(Receipt $receipt, $check = FALSE) {
        $response = $this->processData($receipt, $check);

        isset($response->Chyba) && $this->processError($response->Chyba);

        return $check ? TRUE : $response->Potvrzeni->fik;
    }

    /**
     * 
     * @param Receipt $receipt
     * @param boolean $check
     * @return object
     */
    private function processData(Receipt $receipt, $check = FALSE) {
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


        $soapClient = new SoapClient($this->key, $this->cert);
        return $soapClient->OdeslaniTrzby([
                    'Hlavicka' => $head,
                    'Data' => $body,
                    'KontrolniKody' => $this->getCheckCodes($receipt)
                        ]
        );
    }

    public function getCheckCodes(Receipt $receipt) {
        $objKey = new \XMLSecurityKey(\XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
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
