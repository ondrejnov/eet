<?php

declare(strict_types=1);

namespace Ondrejnov\EET;

use Ondrejnov\EET\Exceptions\ClientException;

/**
 * Cash register certificate (pokladni certifikat) with its private key.
 */
final class Certificate
{
    /** @var resource|\OpenSSLAsymmetricKey */
    private $privateKey;

    /** @var string */
    private $certificatePem;

    /**
     * @param resource|\OpenSSLAsymmetricKey $privateKey
     */
    private function __construct($privateKey, string $certificatePem)
    {
        $this->privateKey = $privateKey;
        $this->certificatePem = $certificatePem;

        if (!openssl_x509_check_private_key($this->certificatePem, $this->privateKey)) {
            throw new ClientException('The private key does not belong to the certificate.');
        }
    }

    /**
     * Loads a .p12 file as issued by CA EET.
     *
     * @throws ClientException
     */
    public static function fromPkcs12(
        string $path,
        #[\SensitiveParameter]
        string $password
    ): self
    {
        $content = self::readFile($path);

        if (!openssl_pkcs12_read($content, $parts, $password)) {
            throw new ClientException(self::describePkcs12Failure($path));
        }

        $privateKey = openssl_pkey_get_private($parts['pkey']);
        if ($privateKey === false) {
            throw new ClientException('Cannot read the private key from ' . $path . ': ' . self::lastOpenSslError());
        }

        return new self($privateKey, $parts['cert']);
    }

    /**
     * Loads a PEM private key and a PEM certificate.
     *
     * @param string|null $passphrase
     *
     * @throws ClientException
     */
    public static function fromPem(
        string $keyPath,
        string $certificatePath,
        #[\SensitiveParameter]
        $passphrase = null
    ): self {
        $key = self::readFile($keyPath);
        $privateKey = $passphrase === null ? openssl_pkey_get_private($key) : openssl_pkey_get_private($key, $passphrase);
        if ($privateKey === false) {
            throw new ClientException('Cannot read the private key from ' . $keyPath . ': ' . self::lastOpenSslError());
        }

        return new self($privateKey, self::readFile($certificatePath));
    }

    /**
     * @return resource|\OpenSSLAsymmetricKey
     */
    public function getPrivateKey()
    {
        return $this->privateKey;
    }

    /**
     * @return string DER certificate in base64, the form used by wsse:BinarySecurityToken
     */
    public function getBase64(): string
    {
        if (!openssl_x509_export($this->certificatePem, $pem)) {
            throw new ClientException('Cannot read the certificate: ' . self::lastOpenSslError());
        }

        return (string) preg_replace('/-----[A-Z ]+-----|\s+/', '', $pem);
    }

    /**
     * @return string EIC of the taxpayer, stored as the certificate common name
     */
    public function getEic(): string
    {
        return (string) ($this->parse()['subject']['CN'] ?? '');
    }

    public function getValidTo(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('@' . $this->parse()['validTo_time_t']);
    }

    public function __debugInfo(): array
    {
        return ['eic' => $this->getEic(), 'validTo' => $this->getValidTo()->format(DATE_ATOM)];
    }

    private function parse(): array
    {
        $parsed = openssl_x509_parse($this->certificatePem);
        if ($parsed === false) {
            throw new ClientException('Cannot read the certificate: ' . self::lastOpenSslError());
        }

        return $parsed;
    }

    private static function readFile(string $path): string
    {
        $content = is_file($path) ? file_get_contents($path) : false;
        if ($content === false) {
            throw new ClientException('Cannot read file ' . $path);
        }

        return $content;
    }

    private static function describePkcs12Failure(string $path): string
    {
        $error = self::lastOpenSslError();
        $message = 'Cannot read PKCS#12 file ' . $path . ': ' . $error;

        if (stripos($error, 'unsupported') !== false) {
            $message .= '. The file uses a legacy cipher disabled in OpenSSL 3, convert it to PEM with'
                . ' "openssl pkcs12 -legacy" and use Certificate::fromPem().';
        }

        return $message;
    }

    private static function lastOpenSslError(): string
    {
        $errors = [];
        while (($error = openssl_error_string()) !== false) {
            $errors[] = $error;
        }

        return $errors === [] ? 'unknown error' : implode('; ', $errors);
    }
}
