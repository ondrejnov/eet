<?php

declare(strict_types=1);

namespace Ondrejnov\EET;

use Ondrejnov\EET\Exceptions\ClientException;
use Ondrejnov\EET\Exceptions\ValidationException;
use Ondrejnov\EET\Utils\Format;

/**
 * Registered sale (e-trzba) in the data format of the EET interface v4.
 */
class Receipt
{
    const UUID_MASK = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/D';
    const EIC_MASK = '/^CZ[0-9]{8,10}$/D';
    const UNIT_MASK = '/^[1-9][0-9]{0,8}$/D';
    const CASH_REGISTER_MASK = '/^[0-9a-zA-Z.,:;\/#\-_ ]{1,20}$/D';
    const SERIAL_NUMBER_MASK = '/^[0-9a-zA-Z.,:;\/#\-_ ]{1,25}$/D';

    /** Items of the previous EET version with a direct replacement. */
    const RENAMED_ITEMS = [
        'dic_popl' => 'eic_popl',
        'dic_poverujiciho' => 'eic_poverujiciho',
        'id_provoz' => 'id_jednotky',
    ];

    /**
     * The types of the items are checked by validate() before sending.
     *
     * @var string|null message UUID, a new one is generated for every sending when left empty
     */
    public $uuid_zpravy;

    /** @var bool FALSE for every other attempt to send the same sale */
    public $prvni_zaslani = true;

    /** @var string|null */
    public $eic_popl;

    /** @var string|null */
    public $eic_poverujiciho;

    /** @var bool|null */
    public $povereni_vice_popl;

    /** @var int|string|null */
    public $id_jednotky;

    /** @var int|string|null */
    public $id_pokl;

    /** @var int|string|null */
    public $porad_cis;

    /** @var \DateTimeInterface|null */
    public $dat_trzby;

    /** @var int|float|string|null */
    public $celk_trzba;

    /** @var int|float|string|null */
    public $urceno_cerp_zuct;

    /** @var int|float|string|null */
    public $cerp_zuct;

    /**
     * Returns a copy for another attempt to send the same sale: the data stay untouched,
     * the new message gets a new UUID and prvni_zaslani = false.
     *
     * @return static
     */
    public function forResend()
    {
        $copy = clone $this;
        $copy->uuid_zpravy = null;
        $copy->prvni_zaslani = false;

        return $copy;
    }

    /**
     * @throws ValidationException
     */
    public function validate()
    {
        $errors = [];

        $this->checkMask($errors, 'uuid_zpravy', $this->uuid_zpravy, self::UUID_MASK, false);
        $this->checkMask($errors, 'eic_popl', $this->eic_popl, self::EIC_MASK, true);
        $this->checkMask($errors, 'eic_poverujiciho', $this->eic_poverujiciho, self::EIC_MASK, false);
        $this->checkMask($errors, 'id_jednotky', $this->id_jednotky, self::UNIT_MASK, true);
        $this->checkMask($errors, 'id_pokl', $this->id_pokl, self::CASH_REGISTER_MASK, true);
        $this->checkMask($errors, 'porad_cis', $this->porad_cis, self::SERIAL_NUMBER_MASK, true);

        if ($this->dat_trzby === null) {
            $errors[] = 'dat_trzby is required';
        } elseif (!$this->dat_trzby instanceof \DateTimeInterface) {
            $errors[] = 'dat_trzby must be an instance of DateTimeInterface';
        }

        if (!is_bool($this->prvni_zaslani)) {
            $errors[] = 'prvni_zaslani must be a boolean';
        }

        if ($this->povereni_vice_popl !== null && !is_bool($this->povereni_vice_popl)) {
            $errors[] = 'povereni_vice_popl must be a boolean';
        }

        if ($this->celk_trzba === null) {
            $errors[] = 'celk_trzba is required';
        }

        foreach (['celk_trzba', 'urceno_cerp_zuct', 'cerp_zuct'] as $item) {
            try {
                Format::price($this->$item);
            } catch (ValidationException $e) {
                $errors[] = $item . ': ' . $e->getMessage();
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    /**
     * A misspelled or removed item must not silently disappear from a tax message.
     *
     * @throws ClientException
     */
    public function __set(string $name, $value)
    {
        $hint = isset(self::RENAMED_ITEMS[$name])
            ? ', use ' . self::RENAMED_ITEMS[$name]
            : '';

        throw new ClientException(sprintf('EET 2.0 has no item %s%s.', $name, $hint));
    }

    /**
     * @param list<string> $errors
     * @param mixed $value
     */
    private function checkMask(array &$errors, string $item, $value, string $mask, bool $required)
    {
        if ($value === null) {
            if ($required) {
                $errors[] = $item . ' is required';
            }

            return;
        }

        if (!is_int($value) && !is_string($value)) {
            $errors[] = sprintf('%s must be an integer or a string', $item);

            return;
        }

        if (preg_match($mask, (string) $value) !== 1) {
            $errors[] = sprintf('%s has an invalid format: "%s"', $item, $value);
        }
    }
}
