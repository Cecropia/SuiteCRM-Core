<?php

namespace App\Tests\unit\core\legacy;

use ApiPlatform\Core\Exception\ItemNotFoundException;
use App\Tests\UnitTester;
use AspectMock\Test;
use Codeception\Test\Unit;
use Exception;
use App\Legacy\CurrencyHandler;
use App\Legacy\DateTimeHandler;
use App\Legacy\UserPreferenceHandler;
use App\Legacy\UserPreferences\CurrencyPreferenceMapper;
use App\Legacy\UserPreferences\DateFormatPreferenceMapper;
use App\Legacy\UserPreferences\TimeFormatPreferenceMapper;
use App\Legacy\UserPreferences\UserPreferencesMappers;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use User;

/**
 * Class UserPreferencesHandlerTest
 * @package App\Tests\unit\core\legacy
 */
class UserPreferencesHandlerTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var UserPreferenceHandler
     */
    protected $handler;

    /**
     * @throws Exception
     * @noinspection StaticClosureCanBeUsedInspection
     */
    protected function _before(): void
    {
        $session = new Session(new MockArraySessionStorage('PHPSESSID'));
        $session->start();

        $projectDir = $this->tester->getProjectDir();
        $legacyDir = $this->tester->getLegacyDir();
        $legacySessionName = $this->tester->getLegacySessionName();
        $defaultSessionName = $this->tester->getDefaultSessionName();
        $legacyScope = $this->tester->getLegacyScope();

        $exposedUserPreferences = [
            'global' => [
                'timezone' => true,
                'datef' => true,
                'timef' => true,
                'currency' => true
            ]
        ];

        $mockPreferences = [
            'global' => [
                'timezone' => 'UTC',
                'datef' => 'm/d/Y',
                'timef' => 'H:i',
                'currency' => -99
            ]
        ];

        $self = $this;

        test::double(UserPreferenceHandler::class, [
            'getCurrentUser' => function () use ($self, $mockPreferences) {

                return $self->make(
                    User::class,
                    [
                        'id' => '123',
                        'getPreference' => function ($name, $category = 'global') use ($self, $mockPreferences) {
                            return $mockPreferences[$category][$name];
                        },
                    ]
                );
            },
            'init' => function () {
            },
            'startLegacyApp' => function () {
            },
            'close' => function () {
            }
        ]);

        $dateTimeHandler = new DateTimeHandler(
            $projectDir,
            $legacyDir,
            $legacySessionName,
            $defaultSessionName,
            $legacyScope,
            $this->tester->getDatetimeFormatMap(),
            $session
        );

        $currencyHandler = new CurrencyHandler(
            $projectDir,
            $legacyDir,
            $legacySessionName,
            $defaultSessionName,
            $legacyScope,
            $session
        );

        $mappersArray = [
            'datef' => new DateFormatPreferenceMapper($dateTimeHandler),
            'timef' => new TimeFormatPreferenceMapper($dateTimeHandler),
            'currency' => new CurrencyPreferenceMapper($currencyHandler)
        ];

        /** @var UserPreferencesMappers $mappers */
        $mappers = $this->make(
            UserPreferencesMappers::class,
            [
                'get' => static function (string $key) use ($mappersArray) {
                    return $mappersArray[$key] ?? null;
                },
                'hasMapper' => static function (string $key) use ($mappersArray) {
                    if (isset($mappersArray[$key])) {
                        return true;
                    }

                    return false;
                },
            ]
        );

        $systemConfigKeyMap = [
            'datef' => 'date_format',
            'timef' => 'time_format'
        ];

        $this->handler = new UserPreferenceHandler(
            $projectDir,
            $legacyDir,
            $legacySessionName,
            $defaultSessionName,
            $legacyScope,
            $exposedUserPreferences,
            $mappers,
            $systemConfigKeyMap,
            $session
        );
    }

    // tests

    /**
     * Test empty config key handling in UserPreferenceHandler
     */
    public function testEmptyUserPreferenceKeyCheck(): void
    {
        $noKeyUserPreference = $this->handler->getUserPreference('');
        static::assertNull($noKeyUserPreference);
    }

    /**
     * Test invalid config key handling in UserPreferenceHandler
     */
    public function testInvalidExposedUserPreferenceCheck(): void
    {
        $this->expectException(ItemNotFoundException::class);
        $this->handler->getUserPreference('dbconfig');
    }

    /**
     * Test retrieval of first level user preference config in UserPreferenceHandler
     */
    public function testGetUserPreferenceTimezone(): void
    {
        $userPref = $this->handler->getUserPreference('global');

        static::assertNotNull($userPref);
        static::assertEquals('global', $userPref->getId());

        $userPreferences = $userPref->getItems();

        static::assertEquals('UTC', $userPreferences['timezone']);
    }

    /**
     * Test date format preference transformation
     */
    public function testDateFormatMapping(): void
    {
        $userPref = $this->handler->getUserPreference('global');

        static::assertNotNull($userPref);
        static::assertEquals('global', $userPref->getId());

        $userPreferences = $userPref->getItems();

        static::assertArrayHasKey('date_format', $userPreferences);
        $format = $userPreferences['date_format'];
        static::assertNotNull($format);
        static::assertEquals('MM/dd/yyyy', $format);
    }

    /**
     * Test time format preference transformation
     */
    public function testTimeFormatMapping(): void
    {
        $userPref = $this->handler->getUserPreference('global');

        static::assertNotNull($userPref);
        static::assertEquals('global', $userPref->getId());

        $userPreferences = $userPref->getItems();

        static::assertArrayHasKey('time_format', $userPreferences);
        $format = $userPreferences['time_format'];

        static::assertNotNull($format);
        static::assertEquals('HH:mm', $format);
    }

    /**
     * Test currency preference mapping
     */
    public function testCurrencyMapping(): void
    {
        $userPref = $this->handler->getUserPreference('global');

        static::assertNotNull($userPref);
        static::assertEquals('global', $userPref->getId());

        $userPreferences = $userPref->getItems();

        static::assertArrayHasKey('currency', $userPreferences);
        $currencyPref = $userPreferences['currency'];

        static::assertNotNull($currencyPref);
        static::assertNotEmpty($currencyPref);

        static::assertArrayHasKey('id', $currencyPref);
        static::assertNotEmpty($currencyPref['id']);
        static::assertEquals($currencyPref['id'], -99);
        static::assertArrayHasKey('name', $currencyPref);
        static::assertNotEmpty($currencyPref['name']);
        static::assertArrayHasKey('symbol', $currencyPref);
        static::assertNotEmpty($currencyPref['symbol']);
        static::assertArrayHasKey('iso4217', $currencyPref);
        static::assertNotEmpty($currencyPref['iso4217']);
    }
}
