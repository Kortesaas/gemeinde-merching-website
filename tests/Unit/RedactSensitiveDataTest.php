<?php

namespace Tests\Unit;

use App\Logging\RedactSensitiveData;
use PHPUnit\Framework\TestCase;

class RedactSensitiveDataTest extends TestCase
{
    public function test_secrets_are_redacted_recursively(): void
    {
        $result = RedactSensitiveData::redact([
            'user_id' => 5,
            'password' => 'geheim',
            'input' => [
                'email' => 'a@example.test',
                'recovery_code' => 'ABCDE-12345',
                'remember_token' => 'x',
                'two_factor_secret' => 'y',
            ],
            'headers' => ['Authorization' => 'Bearer z', 'Cookie' => 'c'],
        ]);

        $this->assertSame(5, $result['user_id']);
        $this->assertSame('[redacted]', $result['password']);
        $this->assertSame('a@example.test', $result['input']['email']);
        $this->assertSame('[redacted]', $result['input']['recovery_code']);
        $this->assertSame('[redacted]', $result['input']['remember_token']);
        $this->assertSame('[redacted]', $result['input']['two_factor_secret']);
        $this->assertSame('[redacted]', $result['headers']['Authorization']);
        $this->assertSame('[redacted]', $result['headers']['Cookie']);
    }
}
