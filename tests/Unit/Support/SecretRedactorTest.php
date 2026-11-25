<?php

namespace Tests\Unit\Support;

use App\Logging\RedactSecretsTap;
use App\Support\SecretRedactor;
use App\Support\SentryScrubber;
use Illuminate\Support\Facades\Log;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Tests\TestCase;

class SecretRedactorTest extends TestCase
{
    private const TOKEN = '7185618354:AAH-test-token_abcdefgh';
    private const FB = 'EAAB+fb/access=token';

    private array $saved = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setEnv('TELEGRAM_API_TOKEN', self::TOKEN);
        $this->setEnv('FACEBOOK_ACCESS_CODE', self::FB);
        $this->setEnv('TELEGRAM_ENABLE', 'true-but-long-enough');
        $this->setEnv('DISCORD_WEBHOOK_TOKEN_ERRORS', 'short');
        SecretRedactor::flush();
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $name => $value) {
            if ($value === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $value;
            }
        }
        SecretRedactor::flush();
        parent::tearDown();
    }

    private function setEnv(string $name, string $value): void
    {
        $this->saved[$name] ??= $_ENV[$name] ?? null;
        $_ENV[$name] = $value;
    }

    /** @test */
    public function it_masks_secret_env_values_in_text_and_their_url_encodings(): void
    {
        $text = 'Client error: `POST https://api.telegram.org/bot' . self::TOKEN . '/sendMessage` '
            . 'and https://graph.facebook.com/me?access_token=' . rawurlencode(self::FB);

        $redacted = SecretRedactor::redact($text);

        $this->assertStringNotContainsString(self::TOKEN, $redacted);
        $this->assertStringNotContainsString(rawurlencode(self::FB), $redacted);
        $this->assertStringContainsString('https://api.telegram.org/bot[redacted]/sendMessage', $redacted);
    }

    /** @test */
    public function only_secret_names_with_long_enough_values_count(): void
    {
        $this->assertSame('true-but-long-enough short', SecretRedactor::redact('true-but-long-enough short'));
    }

    /** @test */
    public function arrays_and_exceptions_are_redacted(): void
    {
        $e = new \RuntimeException('failed: /bot' . self::TOKEN . '/x', 0, new \LogicException('inner ' . self::TOKEN));

        $value = SecretRedactor::redactValue(['a' => ['b' => self::TOKEN], 'exception' => $e, 'n' => 3]);

        $this->assertSame('[redacted]', $value['a']['b']);
        $this->assertSame(3, $value['n']);
        $this->assertIsString($value['exception']);
        $this->assertStringContainsString('RuntimeException(code: 0): failed: /bot[redacted]/x', $value['exception']);
        $this->assertStringContainsString('[previous exception]', $value['exception']);
        $this->assertStringNotContainsString(self::TOKEN, $value['exception']);
    }

    /** @test */
    public function log_channels_with_the_tap_never_write_the_secret(): void
    {
        $path = sys_get_temp_dir() . '/redact-' . uniqid() . '.log';
        config(['logging.channels.redact_test' => [
            'driver' => 'single',
            'tap' => [RedactSecretsTap::class],
            'path' => $path,
        ]]);

        Log::channel('redact_test')->error('Telegram sendMessage failed: /bot' . self::TOKEN . '/sendMessage', [
            'exception' => new \RuntimeException('POST https://api.telegram.org/bot' . self::TOKEN . '/sendMessage'),
        ]);

        $written = file_get_contents($path);
        @unlink($path);
        $this->assertStringContainsString('/bot[redacted]/sendMessage', $written);
        $this->assertStringNotContainsString(self::TOKEN, $written);
    }

    /** @test */
    public function the_writing_channels_have_the_tap(): void
    {
        foreach (['single', 'anepc', 'daily', 'stderr', 'syslog', 'errorlog'] as $channel) {
            $this->assertContains(RedactSecretsTap::class, config("logging.channels.$channel.tap", []), $channel);
        }
    }

    /** @test */
    public function sentry_events_are_scrubbed(): void
    {
        $event = Event::createEvent();
        $event->setExceptions([new ExceptionDataBag(new \RuntimeException('POST /bot' . self::TOKEN . '/sendMessage'))]);
        $event->setMessage('failed %s', [self::TOKEN]);
        $event->setBreadcrumb([new Breadcrumb('error', Breadcrumb::TYPE_HTTP, 'http', 'POST /bot' . self::TOKEN, [
            'url' => 'https://api.telegram.org/bot' . self::TOKEN . '/sendMessage',
        ])]);
        $event->setExtra(['token' => self::TOKEN]);
        $event->setRequest(['url' => 'https://api.fogos.pt/x?k=' . self::TOKEN, 'headers' => ['key' => ['w']]]);

        $event = SentryScrubber::beforeSend($event);

        $this->assertStringNotContainsString(self::TOKEN, $event->getExceptions()[0]->getValue());
        $this->assertSame(['[redacted]'], $event->getMessageParams());
        $crumb = $event->getBreadcrumbs()[0];
        $this->assertSame('POST /bot[redacted]', $crumb->getMessage());
        $this->assertStringNotContainsString(self::TOKEN, $crumb->getMetadata()['url']);
        $this->assertSame(['token' => '[redacted]'], $event->getExtra());
        $this->assertStringNotContainsString(self::TOKEN, $event->getRequest()['url']);
        $this->assertSame('[Filtered]', $event->getRequest()['headers']['key']);
    }
}
