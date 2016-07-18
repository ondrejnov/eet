<?php
namespace eet;

class SoapClient extends \SoapClient
{
	private $key;
	private $cert;

	public function __construct($key, $cert)
	{
		parent::__construct(__DIR__.'/../EETServiceSOAP.wsdl', ['trace' => 1]);
		$this->key = $key;
		$this->cert = $cert;
	}

	public function __doRequest($request, $location, $saction, $version, $one_way = NULL)
	{
		$doc = new \DOMDocument('1.0');
		$doc->loadXML($request);

		$objWSSE = new \WSSESoap($doc);
		$objWSSE->addTimestamp();

		$objKey = new \XMLSecurityKey(\XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
		$objKey->loadKey($this->key, TRUE);
		$objWSSE->signSoapDoc($objKey, ["algorithm" => \XMLSecurityDSig::SHA256]);

		$token = $objWSSE->addBinaryToken(file_get_contents($this->cert));
		$objWSSE->attachTokentoSig($token);

		return parent::__doRequest($objWSSE->saveXML(), $location, $saction, $version);
	}
}