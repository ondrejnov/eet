<?php

namespace Ondrejnov\EET\Test;

use Ondrejnov\EET\Dispatcher as Tested;
use Ondrejnov\EET\Exceptions\ServerException;
use Ondrejnov\EET\Receipt;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

class Dispatcher extends \Tester\TestCase {

    public function testSendOk() {
        $fik = $this->getTestDispatcher()->send($this->getExampleReceipt());
        Assert::type('string', $fik);
    }

    public function testSendError() {
        $r = $this->getExampleReceipt();
        $r->dic_popl = 'x';
        Assert::exception(function() use ($r) {
            $this->getTestDispatcher()->send($r);
        }, ServerException::class);
    }

    /**
     * 
     * @return Tested
     */
    private function getTestDispatcher() {
        return new Tested(PLAYGROUND_WSDL, DIR_CERT . '/eet.key', DIR_CERT . '/eet.pem');
    }

    /**
     * @return Receipt
     */
    private function getExampleReceipt() {
        $r = new Receipt();
        $r->uuid_zpravy = 'b3a09b52-7c87-4014-a496-4c7a53cf9120';
        $r->dic_popl = 'CZ72080043';
        $r->id_provoz = '181';
        $r->id_pokl = '1';
        $r->porad_cis = '1';
        $r->dat_trzby = new \DateTime();
        $r->celk_trzba = 1000;
        return $r;
    }

}

(new Dispatcher)->run();
