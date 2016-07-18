<?php
namespace eet;
require_once ('SoapClient.php');
require_once ('EETException.php');

class Receipt
{
	// hlavicka
	public $uuid_zpravy;
	public $prvni_zaslani = TRUE;

	// data
	/** @var string */
	public $dic_popl;
	/** @var string */
	public $dic_poverujiciho;
	/** @var string */
	public $id_provoz;
	/** @var string */
	public $id_pokl;
	/** @var string */
	public $porad_cis;
	/** @var  \DateTime */
	public $dat_trzby;
	/** @var  float */
	public $celk_trzba = 0;
	/** @var  float */
	public $zakl_nepodl_dph = 0;
	/** @var  float */
	public $zakl_dan1 = 0;
	/** @var  float */
	public $dan1 = 0;
	/** @var  float */
	public $zakl_dan2 = 0;
	/** @var  float */
	public $dan2 = 0;
	/** @var  float */
	public $zakl_dan3 = 0;
	/** @var  float */
	public $dan3 = 0;
	/** @var  int */
	public $rezim = 0;

	// certifikat
	private $key;
	private $cert;


	public function __construct($key, $cert)
	{
		$this->key = $key;
		$this->cert = $cert;
	}

	public function check()
	{
		try {
			return $this->send(TRUE);
		}
		catch (EETException $e) {
			return FALSE;
		}
	}

	public function send($check = FALSE)
	{
		$hlavicka = [
			'uuid_zpravy' => $this->uuid_zpravy,
			'dat_odesl' => time(),
			'prvni_zaslani' => $this->prvni_zaslani,
			'overeni' => $check
		];

		$data = [
			'dic_popl' => $this->dic_popl,
			'dic_poverujiciho' => $this->dic_poverujiciho,
			'id_provoz' => $this->id_provoz,
			'id_pokl' => $this->id_pokl,
			'porad_cis' => $this->porad_cis,
			'dat_trzby' => $this->dat_trzby->format('c'),
			'celk_trzba' => $this->priceFormat($this->celk_trzba),
			'zakl_nepodl_dph' => $this->priceFormat($this->zakl_nepodl_dph),
			'zakl_dan1' => $this->priceFormat($this->zakl_dan1),
			'dan1' => $this->priceFormat($this->dan1),
			'zakl_dan2' => $this->priceFormat($this->zakl_dan2),
			'dan2' => $this->priceFormat($this->dan2),
			'zakl_dan3' => $this->priceFormat($this->zakl_dan3),
			'dan3' => $this->priceFormat($this->dan3),
			'rezim' => $this->rezim
		];


		$soapClient = new \eet\SoapClient($this->key, $this->cert);
		$response = $soapClient->OdeslaniTrzby([
				'Hlavicka' => $hlavicka,
				'Data' => $data,
				'KontrolniKody' => $this->getCheckCodes()
			]
		);
		if (isset($response->Chyba)) {
			$this->processError($response->Chyba);
		}
		if ($check) {
			return TRUE;
		}
		else {
			return $response->Potvrzeni->fik;
		}
	}

	public function getCheckCodes()
	{
		$objKey = new \XMLSecurityKey(\XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
		$objKey->loadKey($this->key, TRUE);

		$arr = [
			$this->dic_popl, 
			$this->id_provoz,
			$this->id_pokl,
			$this->porad_cis,
			$this->dat_trzby->format('c'),
			$this->priceFormat($this->celk_trzba)
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
				'_' => $this->formatBKB(sha1($sign)),
				'digest' => 'SHA1',
				'encoding' => 'base16'
			]
		];
	}

	private function formatBKB($code) {
		$r = '';
		for ($i = 0; $i < 40; $i++) {
			if ($i % 8 == 0 && $i != 0) {
				$r.= '-';
			}
			$r .= $code[$i];
		}
		return $r;
	}

	private function priceFormat($value) {
		return number_format($value, 2, '.', '');
	}

	private function processError($error) {
		if ($error->kod) {
			$msgs = [
				-1 => 'Docasna technicka chyba zpracovani – odeslete prosim datovou zpravu pozdeji',
				2  => 'Kodovani XML neni platne',
				3  => 'XML zprava nevyhovela kontrole XML schematu',
				4  => 'Neplatny podpis SOAP zpravy',
				5  => 'Neplatny kontrolni bezpecnostni kod poplatnika (BKP)',
				6  => 'DIC poplatnika ma chybnou strukturu',
				7  => 'Datova zprava je prilis velka',
				8  => 'Datova zprava nebyla zpracovana kvuli technicke chybe nebo chybe dat',
			];
			$msg = isset($msgs[$error->kod]) ? $msgs[$error->kod] : '';
			throw new EETException($msg, $error->kod);
		}
	}

}