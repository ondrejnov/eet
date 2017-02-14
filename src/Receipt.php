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
    public $zakl_nepodl_dph = null;

    /** @var float */
    public $zakl_dan1 = null;

    /** @var float */
    public $dan1 = null;

    /** @var float */
    public $zakl_dan2 = null;

    /** @var float */
    public $dan2 = null;

    /** @var float */
    public $zakl_dan3 = null;

    /** @var float */
    public $dan3 = null;

    /** @var float */
    public $cest_sluz = null;

    /** @var float */
    public $pouzit_zboz1 = null;

    /** @var float */
    public $pouzit_zboz2 = null;

    /** @var float */
    public $pouzit_zboz3 = null;

    /** @var float */
    public $urceno_cerp_zuct = null;

    /** @var float */
    public $cerp_zuct = null;

    /** @var int */
    public $rezim;

}
