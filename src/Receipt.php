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
    private const UUID_MASK = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/D';
    private const EIC_MASK = '/^CZ[0-9]{8,10}$/D';
    private const UNIT_MASK = '/^[1-9][0-9]{0,8}$/D';
    private const CASH_REGISTER_MASK = '/^[0-9a-zA-Z.,:;\/#\-_ ]{1,20}$/D';
    private const SERIAL_NUMBER_MASK = '/^[0-9a-zA-Z.,:;\/#\-_ ]{1,25}$/D';

    /** Items of the previous EET version with a direct replacement. */
    private const RENAMED_ITEMS = [
        'dic_popl' => 'eic_popl',
        'dic_poverujiciho' => 'eic_poverujiciho',
        'id_provoz' => 'id_jednotky',
    ];

    /** Message UUID, a new one is generated for every sending when left empty. */
    public ?string $uuid_zpravy = null;

    /** FALSE for every other attempt to send the same sale. */
    public bool $prvni_zaslani = true;

    public ?string $eic_popl = null;

    public ?string $eic_poverujiciho = null;

    public ?bool $povereni_vice_popl = null;

    public int|string|null $id_jednotky = null;

    public int|string|null $id_pokl = null;

    public int|string|null $porad_cis = null;

    public ?\DateTimeInterface $dat_trzby = null;

    public int|float|string|null $celk_trzba = null;

    public int|float|string|null $urceno_cerp_zuct = null;

    public int|float|string|null $cerp_zuct = null;

    /**
     * Returns a copy for another attempt to send the same sale: the data stay untouched,
     * the new message gets a new UUID and prvni_zaslani = false.
     */
    public function forResend(): static
    {
        $copy = clone $this;
        $copy->uuid_zpravy = null;
        $copy->prvni_zaslani = false;

        return $copy;
    }

    /**
     * @throws ValidationException
     */
    public function validate(): void
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
    public function __set(string $name, mixed $value): void
    {
        $hint = isset(self::RENAMED_ITEMS[$name])
            ? ', use ' . self::RENAMED_ITEMS[$name]
            : '';

        throw new ClientException(sprintf('EET 2.0 has no item %s%s.', $name, $hint));
    }

    /**
     * @param list<string> $errors
     */
    private function checkMask(array &$errors, string $item, int|string|null $value, string $mask, bool $required): void
    {
        if ($value === null) {
            if ($required) {
                $errors[] = $item . ' is required';
            }

            return;
        }

        if (preg_match($mask, (string) $value) !== 1) {
            $errors[] = sprintf('%s has an invalid format: "%s"', $item, $value);
        }
    }
}
