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
    public $uuid_zpravy = FALSE;

    /**
     * Head part: first sending
     * @var boolean */
    public $prvni_zaslani = TRUE;

    /** @var string */
    public $dic_popl = FALSE;

    /** @var string */
    public $dic_poverujiciho = FALSE;

    /** @var string */
    public $id_provoz = FALSE;

    /** @var string */
    public $id_pokl = FALSE;

    /** @var string */
    public $porad_cis = FALSE;

    /** @var \DateTime */
    public $dat_trzby = FALSE;

    /** @var float */
    public $celk_trzba = FALSE;

    /** @var float */
    public $zakl_nepodl_dph = FALSE;

    /** @var float */
    public $zakl_dan1 = FALSE;

    /** @var float */
    public $dan1 = FALSE;

    /** @var float */
    public $zakl_dan2 = FALSE;

    /** @var float */
    public $dan2 = FALSE;

    /** @var float */
    public $zakl_dan3 = FALSE;

    /** @var float */
    public $dan3 = FALSE;

    /** @var float */
    public $cest_sluz = FALSE;

    /** @var float */
    public $pouzit_zboz1 = FALSE;

    /** @var float */
    public $pouzit_zboz2 = FALSE;

    /** @var float */
    public $pouzit_zboz3 = FALSE;

    /** @var float */
    public $urceno_cerp_zuct = FALSE;

    /** @var float */
    public $cerp_zuct = FALSE;

    /** @var int */
    public $rezim = FALSE;

}
