<?php

namespace Carnage\JwtZendAuth\Authentication\Storage;

use Carnage\JwtZendAuth\Service\Jwt as JwtService;
use Lcobucci\JWT\Token;
use Zend\Authentication\Storage\StorageInterface;

/**
 * Class Jwt
 * @package Carnage\JwtZendAuth\Authentication\Storage
 */
class Jwt implements StorageInterface
{
    private static $claimName = 'session-data';

    /**
     * @var bool
     */
    private $hasReadClaimData = false;

    /**
     * @var Token
     */
    private $token;

    /**
     * @var StorageInterface
     */
    private $wrapped;

    /**
     * @var JwtService
     */
    private $jwt;

    /**
     * @var int
     */
    private $expirationSecs;

    /**
     * @param JwtService $jwt
     * @param StorageInterface $wrapped
     * @param int $expirationSecs
     */
    public function __construct(JwtService $jwt, StorageInterface $wrapped, $expirationSecs = 600)
    {
        $this->jwt = $jwt;
        $this->wrapped = $wrapped;
        $this->expirationSecs = $expirationSecs;
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return $this->read() === null;
    }

    /**
     * @return mixed
     */
    public function read()
    {
        if (!$this->hasReadClaimData) {
            $this->hasReadClaimData = true;
            if ($this->shouldRefreshToken()) {
                $this->writeToken($this->retrieveClaim());
            }
        }

        return $this->retrieveClaim();
    }

    /**
     * @param mixed $contents
     */
    public function write($contents)
    {
        if ($contents !== $this->read()) {
            $this->writeToken($contents);
        }
    }

    /**
     * @return void
     */
    public function clear()
    {
        $this->wrapped->clear();
    }

    /**
     * @return bool
     */
    private function hasTokenValue()
    {
        return ($this->wrapped->read() !== null);
    }

    /**
     * @return Token|null
     */
    private function retrieveToken()
    {
        if ($this->token === null) {
            $this->token = $this->jwt->parseToken($this->wrapped->read());
        }

        return $this->token;
    }

    /**
     * @return mixed|null
     */
    private function retrieveClaim()
    {
        if (!$this->hasTokenValue()) {
            return null;
        }

        try {
            return $this->retrieveToken()->getClaim(self::$claimName);
        } catch (\OutOfBoundsException $e) {
            return null;
        }
    }

    /**
     * @return bool
     */
    private function shouldRefreshToken()
    {
        if (!$this->hasTokenValue()) {
            return false;
        }

        try {
            return date('U') >= ($this->retrieveToken()->getClaim('iat') + 60);
        } catch (\OutOfBoundsException $e) {
            return false;
        }
    }

    /**
     * @param $claim
     */
    private function writeToken($claim)
    {
        $this->token = $this->jwt->createSignedToken(self::$claimName, $claim, $this->expirationSecs);
        $this->wrapped->write(
            $this->token->getPayload()
        );
    }
}