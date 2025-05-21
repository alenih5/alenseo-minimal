<?php

namespace Alenseo;

use PHPUnit\Framework\TestCase;
use Defuse\Crypto\Key;
use Defuse\Crypto\Crypto;

class EncryptionTest extends TestCase
{
    private $encryption_key;

    protected function setUp(): void
    {
        $this->encryption_key = Key::createNewRandomKey();
    }

    public function testEncryptionAndDecryption()
    {
        $api_key = 'test_api_key';
        $encrypted = Crypto::encrypt($api_key, $this->encryption_key);
        $decrypted = Crypto::decrypt($encrypted, $this->encryption_key);

        $this->assertEquals($api_key, $decrypted, 'Decrypted API key should match the original.');
    }
}
