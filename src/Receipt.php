<?php

namespace Ondrejnov\EET;

use Ondrejnov\EET\Exceptions\ServerException;
use Ondrejnov\EET\SoapClient;
use Ondrejnov\EET\Utils\Format;

/**
 * Receipt for Ministry of Finance
 */
class Receipt {

    /**
     * Head part: message identifier
     * @var string */
    public $uuid_zpravy;

    /**
     * Head part: first sending
     * @var boolean */
    public $prvni_zaslani = TRUE;

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

    /** @var \DateTime */
    public $dat_trzby;

    /** @var float */
    public $celk_trzba;

    /** @var float */
    public $zakl_nepodl_dph;

    /** @var float */
    public $zakl_dan1;

    /** @var float */
    public $dan1;

    /** @var float */
    public $zakl_dan2;

    /** @var float */
    public $dan2;

    /** @var float */
    public $zakl_dan3;

    /** @var float */
    public $dan3;

    /** @var float */
    public $cest_sluz;

    /** @var float */
    public $pouzit_zboz1;

    /** @var float */
    public $pouzit_zboz2;

    /** @var float */
    public $pouzit_zboz3;

    /** @var float */
    public $urceno_cerp_zuct;

    /** @var float */
    public $cerp_zuct;

    /** @var int */
    public $rezim = 0;

}
