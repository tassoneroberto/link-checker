<?php

namespace LinkChecker;

class Mega implements CheckerInterface
{
    const API_URL = 'https://g.api.mega.co.nz';

    const REGEX = [
        'old' => [
            'valid' => '/https:\/\/mega\.nz\/#F?![a-zA-Z0-9]{8}(![a-zA-Z0-9_-]*)?/',
            'with_key' => '/https:\/\/mega\.nz\/#F?![a-zA-Z0-9]{8}(![a-zA-Z0-9_-]+)/',
            'without_key' => '/https:\/\/mega\.nz\/#F?![a-zA-Z0-9]{8}!?/'
        ],
        'new' => [
            'valid' => '/https:\/\/mega\.nz\/(file|folder)\/[a-zA-Z0-9]{8}(#[a-zA-Z0-9_-]*)?/',
            'with_key' => '/https:\/\/mega\.nz\/(file|folder)\/[a-zA-Z0-9]{8}(#[a-zA-Z0-9_-]+)/',
            'without_key' => '/https:\/\/mega\.nz\/(file|folder)\/[a-zA-Z0-9]{8}#?/'
        ]
    ];

    public static function isOnline(
        string $link,
        bool $verifyCertificate = true,
        bool $verbose = false,
    ): bool {
        if (!self::isValid($link)) {
            throw new \InvalidArgumentException("The link $link is not valid");
        }

        $id = self::getIdAndKey($link)[0];
        $type = self::getType($link);
        switch ($type) {
            case Constants::TYPE_FOLDER:
                $data = [
                    'a' => 'f',
                    'c' => 1,
                    'r' => 1,
                    'ca' => 1,
                ];
                break;
            case Constants::TYPE_FILE:
                $data = [
                    'a' => 'g',
                    'p' => $id,
                ];
                break;
            default:
                throw new \InvalidArgumentException("Invalid type $type");
        }

        $url = self::API_URL . '/cs?id=' .
            substr((string) (mt_rand() / mt_getrandmax()), 2, 10) . '&n=' . $id;
        $payload = [$data];

        $result = HTTPClient::request(
            $url,
            $payload,
            HTTPClient::TYPE_POST,
            $verifyCertificate,
            $verbose
        )[0];
        if (!$result) {
            throw new \Exception('Connection failed');
        }
        if (is_numeric($result) && intval($result) <= 0) {
            return false;
        }
        return true;
    }

    public static function isValid(string $link): bool
    {
        return preg_replace(self::REGEX['new']['valid'], '', $link) === '' ||
            preg_replace(self::REGEX['old']['valid'], '', $link) === '';
    }

    /**
     * @param string $link              MEGA link
     * @return string[]                 id and decryption key
     * @throws InvalidArgumentException if the MEGA link is not valid
     */
    public static function getIdAndKey(string $link): array
    {
        if (!self::isValid($link)) {
            throw new \InvalidArgumentException("The link $link is not valid");
        }
        if (!self::isNewFormat($link)) {
            $link = self::convertFromOldFormat($link);
        }

        $last = explode('/', $link)[4];
        if (str_contains($last, '#')) {
            [$id, $key] = explode('#', $last);
            if ($key === '') {
                $key = null;
            }
            return [$id, $key];
        } else {
            return [$last, null];
        }
    }

    /**
     * @param string $link              MEGA link in the new format
     * @return string                   link type `file` or `folder`
     * @throws InvalidArgumentException if the MEGA link is not valid
     */
    public static function getType(string $link): string
    {
        if (!self::isValid($link)) {
            throw new \InvalidArgumentException("The link $link is not valid");
        }
        if (!self::isNewFormat($link)) {
            $link = self::convertFromOldFormat($link);
        }
        return explode('/', $link)[3];
    }

    /**
     * @param string $link              MEGA link, old or new format
     * @return bool                     true if the decryption key is included in the link
     * @throws InvalidArgumentException if the MEGA link is not valid
     */
    public static function containsKey(string $link): bool
    {
        if (!self::isValid($link)) {
            throw new \InvalidArgumentException("The link $link is not valid");
        }
        return preg_replace(self::REGEX['new']['with_key'], '', $link) === '' ||
            preg_replace(self::REGEX['old']['with_key'], '', $link) === '';
    }

    /**
     * @param string $link              MEGA link, old or new format
     * @return bool                     true if the link's format is new
     * @throws InvalidArgumentException if the MEGA link is not valid
     */
    public static function isNewFormat(string $link): bool
    {
        if (!self::isValid($link)) {
            throw new \InvalidArgumentException("The link $link is not valid");
        }
        return preg_replace(self::REGEX['new']['valid'], '', $link) === '';
    }

    /**
     * @param string $link              MEGA link in the old format
     * @return string                   link converted to the new format
     * @throws InvalidArgumentException if the MEGA link is not valid
     */
    public static function convertFromOldFormat(string $link): string
    {
        if (!self::isValid($link)) {
            throw new \InvalidArgumentException("The link $link is not valid");
        }
        if (self::isNewFormat($link)) {
            return trim($link);
        }

        $link = trim($link);

        $splittedLink = explode('!', $link);
        $id = $splittedLink[1];
        if (count($splittedLink) === 3) {
            $key = $splittedLink[2];
        }
        $newFormatLink = 'https://mega.nz/' .
            (str_contains($link, '/#F!') ? Constants::TYPE_FOLDER : Constants::TYPE_FILE) . '/' . $id;
        if (!empty($key)) {
            $newFormatLink .= '#' . $key;
        }

        return $newFormatLink;
    }
}
