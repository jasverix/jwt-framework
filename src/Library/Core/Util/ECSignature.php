<?php

declare(strict_types=1);

namespace Jose\Component\Core\Util;

use InvalidArgumentException;
use function is_string;
use const STR_PAD_LEFT;

/**
 * @internal
 */
final readonly class ECSignature
{
    private const string ASN1_SEQUENCE = '30';

    private const string ASN1_INTEGER = '02';

    private const int ASN1_MAX_SINGLE_BYTE = 128;

    private const string ASN1_LENGTH_2BYTES = '81';

    private const string ASN1_BIG_INTEGER_LIMIT = '7f';

    private const string ASN1_NEGATIVE_INTEGER = '00';

    private const int BYTE_SIZE = 2;

    public static function toAsn1(string $signature, int $length): string
    {
        $signature = bin2hex($signature);

        if (self::octetLength($signature) !== $length) {
            throw new InvalidArgumentException('Invalid signature length.');
        }

        $pointR = self::preparePositiveInteger(mb_substr($signature, 0, $length, '8bit'));
        $pointS = self::preparePositiveInteger(mb_substr($signature, $length, null, '8bit'));

        $lengthR = self::octetLength($pointR);
        $lengthS = self::octetLength($pointS);

        $totalLength = $lengthR + $lengthS + self::BYTE_SIZE + self::BYTE_SIZE;
        $lengthPrefix = $totalLength > self::ASN1_MAX_SINGLE_BYTE ? self::ASN1_LENGTH_2BYTES : '';

        $bin = hex2bin(
            self::ASN1_SEQUENCE
            . $lengthPrefix . dechex($totalLength)
            . self::ASN1_INTEGER . dechex($lengthR) . $pointR
            . self::ASN1_INTEGER . dechex($lengthS) . $pointS
        );
        if (! is_string($bin)) {
            throw new InvalidArgumentException('Unable to parse the data');
        }

        return $bin;
    }

    public static function fromAsn1(string $signature, int $length): string
    {
        $message = bin2hex($signature);
        $position = 0;

        if (self::readAsn1Content($message, $position, self::BYTE_SIZE) !== self::ASN1_SEQUENCE) {
            throw new InvalidArgumentException('Invalid data. Should start with a sequence.');
        }

        if (self::readAsn1Content($message, $position, self::BYTE_SIZE) === self::ASN1_LENGTH_2BYTES) {
            $position += self::BYTE_SIZE;
        }

        $pointR = self::retrievePositiveInteger(self::readAsn1Integer($message, $position));
        $pointS = self::retrievePositiveInteger(self::readAsn1Integer($message, $position));

        $bin = hex2bin(
            mb_str_pad($pointR, $length, '0', STR_PAD_LEFT, '8bit') . mb_str_pad(
                $pointS,
                $length,
                '0',
                STR_PAD_LEFT,
                '8bit'
            )
        );
        if (! is_string($bin)) {
            throw new InvalidArgumentException('Unable to parse the data');
        }

        return $bin;
    }

    private static function octetLength(string $data): int
    {
        return (int) (mb_strlen($data, '8bit') / self::BYTE_SIZE);
    }

    private static function preparePositiveInteger(string $data): string
    {
        if (mb_substr($data, 0, self::BYTE_SIZE, '8bit') > self::ASN1_BIG_INTEGER_LIMIT) {
            return self::ASN1_NEGATIVE_INTEGER . $data;
        }

        while (mb_strpos($data, self::ASN1_NEGATIVE_INTEGER, 0, '8bit') === 0
            && mb_substr($data, 2, self::BYTE_SIZE, '8bit') <= self::ASN1_BIG_INTEGER_LIMIT) {
            $data = mb_substr($data, 2, null, '8bit');
        }

        return $data;
    }

    private static function readAsn1Content(string $message, int &$position, int $length): string
    {
        $content = mb_substr($message, $position, $length, '8bit');
        $position += $length;

        return $content;
    }

    private static function readAsn1Integer(string $message, int &$position): string
    {
        if (self::readAsn1Content($message, $position, self::BYTE_SIZE) !== self::ASN1_INTEGER) {
            throw new InvalidArgumentException('Invalid data. Should contain an integer.');
        }

        $length = (int) hexdec(self::readAsn1Content($message, $position, self::BYTE_SIZE));

        return self::readAsn1Content($message, $position, $length * self::BYTE_SIZE);
    }

    private static function retrievePositiveInteger(string $data): string
    {
        while (mb_strpos($data, self::ASN1_NEGATIVE_INTEGER, 0, '8bit') === 0
            && mb_substr($data, 2, self::BYTE_SIZE, '8bit') > self::ASN1_BIG_INTEGER_LIMIT) {
            $data = mb_substr($data, 2, null, '8bit');
        }

        return $data;
    }
}
