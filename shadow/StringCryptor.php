<?php

declare(strict_types=1);

namespace Shadow;

use Shared\MimimalCmsConfig;

/**
 * Encrypt and decrypt strings using AES-CBC and obtain hashes of encrypted strings using HKDF.
 * 
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */
class StringCryptor implements StringCryptorInterface
{
    public string $hkdfKey;
    public string $opensslKey;

    public function __construct()
    {
        $this->setSeacretKey(
            MimimalCmsConfig::$stringCryptorHkdfKey,
            MimimalCmsConfig::$stringCryptorOpensslKey
        );
    }

    public function setSeacretKey(string $hkdfKey, string $opensslKey)
    {
        $this->hkdfKey = $hkdfKey;
        $this->opensslKey = $opensslKey;
    }

    public function encryptAndHashString(string $string): string
    {
        $encryptedString = $this->encryptAesCbcString($string);
        $hash = $this->hashHkdf($encryptedString);

        return $this->encodeBase64URL($encryptedString . '#' . $hash);
    }

    public function verifyHashAndDecrypt(string $encryptedString): string
    {
        $components = explode("#", $this->decodeBase64URL($encryptedString));

        if (count($components) !== 2) {
            throw new \RuntimeException('Invalid format for the Base64 URL encoded string.');
        }

        $aesCbcEncryptedString = $components[0];
        $hash = $components[1];

        if (!$this->hkdfEquals($aesCbcEncryptedString, $hash)) {
            throw new \RuntimeException('Invalid hash for the Base64 URL encoded string.');
        }

        try {
            $decryptedString = $this->decryptAesCbcString($aesCbcEncryptedString);
        } catch (\RuntimeException $e) {
            throw new \LogicException('Hash is valid but decryption fails: ' . $e->getMessage());
        }

        return $decryptedString;
    }

    public function encryptAndHashWithValidity(string $string, int $expires): string
    {
        if ($expires < time()) {
            throw new \InvalidArgumentException(
                'Invalid parameter value for expires: only time after now allowed.'
            );
        }

        if (strlen((string) $expires) !== 10) {
            throw new \InvalidArgumentException(
                'Invalid parameter value for expires: Unix time should be 10 digits.'
            );
        }

        $encryptedString = $this->encryptAesCbcString($string);
        $hash = $this->hashHkdf($encryptedString . (string) $expires);

        return (string) $expires . 'd' . $this->encodeBase64URL($encryptedString . '#' . $hash);
    }

    public function verifyHashAndDecryptWithValidity(string $encryptedString): array|false
    {
        $data = substr($encryptedString, 11);
        $components = explode("#", $this->decodeBase64URL($data));

        if (count($components) !== 2) {
            throw new \RuntimeException('Invalid format for the Base64 URL encoded string.');
        }

        $expires = strtok($encryptedString, 'd');
        $aesCbcEncryptedString = $components[0];
        $hash = $components[1];

        if (!$this->hkdfEquals($aesCbcEncryptedString . $expires, $hash)) {
            throw new \RuntimeException('Invalid hash for the Base64 URL encoded string.');
        }

        if ((int) $expires < time()) {
            return false;
        }

        try {
            $decryptedString = $this->decryptAesCbcString($aesCbcEncryptedString);
        } catch (\RuntimeException $e) {
            throw new \LogicException('Hash is valid but decryption fails: ' . $e->getMessage());
        }

        return [(int) $expires, $decryptedString];
    }

    public function hashHkdf(string $string): string
    {
        $salt = random_bytes(16);
        $hash = hash_hkdf('SHA3-224', $this->hkdfKey, 0, $string, $salt);

        // Return the Base64 encoded hash with the salt in the format of `hash`@`salt`.
        return base64_encode($hash) . '@' . base64_encode($salt);
    }

    public function hkdfEquals(string $string, string $hashedString): bool
    {
        $components = explode('@', $hashedString);

        if (count($components) !== 2) {
            throw new \RuntimeException('Invalid format for the HKDF hashed string.');
        }

        $hash = base64_decode($components[0]);
        $salt = base64_decode($components[1]);

        $reHash = hash_hkdf('SHA3-224', $this->hkdfKey, 0, $string, $salt);

        return hash_equals($hash, $reHash);
    }

    public function encryptAesCbcString(string $targetString): string
    {
        $iv = random_bytes(16);

        $encryptedData = openssl_encrypt(
            $targetString,
            'AES-256-CBC',
            $this->opensslKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encryptedData === false) {
            throw new \LogicException('Encryption failed.');
        }

        // Return the Base64-encoded encrypted string in the format `string`@`iv`.
        return base64_encode($encryptedData) . '@' . base64_encode($iv);
    }

    public function decryptAesCbcString(string $encryptedString): string
    {
        $components = explode('@', $encryptedString);
        if (count($components) !== 2) {
            throw new \RuntimeException('Invalid format for the encrypted string.');
        }

        $encryptedData = base64_decode($components[0]);
        $iv = base64_decode($components[1]);

        $decryptedData = openssl_decrypt(
            $encryptedData,
            'AES-256-CBC',
            $this->opensslKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decryptedData === false) {
            throw new \RuntimeException('Decryption failed.');
        }

        return $decryptedData;
    }

    public function encodeBase64URL(string $string): string
    {
        $base64 = base64_encode($string);
        $urlSafe = strtr($base64, '+/', '-_');
        return rtrim($urlSafe, '=');
    }

    public function decodeBase64URL(string $encodedString): string
    {
        $str = strtr($encodedString, '-_', '+/');
        $padding = strlen($str) % 4;
        if ($padding !== 0) {
            $str = str_pad($str, strlen($str) + (4 - $padding), '=', STR_PAD_RIGHT);
        }
        return base64_decode($str);
    }
}
