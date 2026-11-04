<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Security\Session;

final class SessionTest extends TestCase
{
    public function testSessionLifecycleAndDataStorage(): void
    {
        $session = Session::createInMemory([
            'lifetime_seconds' => 1800,
            'cookie_secure' => true,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
        ]);

        self::assertTrue($session->start());
        self::assertTrue($session->isStarted());

        $session->set('user_id', 42);
        $session->set('role', 'admin');

        self::assertTrue($session->has('user_id'));
        self::assertSame(42, $session->get('user_id'));
        self::assertSame('admin', $session->get('role'));
        self::assertNull($session->get('non_existent'));
        self::assertSame('default', $session->get('non_existent', 'default'));

        $session->remove('role');
        self::assertFalse($session->has('role'));

        $session->clear();
        self::assertFalse($session->has('user_id'));
    }

    public function testSessionRegenerationChangesId(): void
    {
        $session = Session::createInMemory();
        $oldId = $session->id();

        self::assertTrue($session->regenerate());
        $newId = $session->id();

        self::assertNotEmpty($newId);
        self::assertNotSame($oldId, $newId);
    }

    public function testSessionFlashDataIsSingleUse(): void
    {
        $session = Session::createInMemory();
        $session->setFlash('status', 'success');

        self::assertSame('success', $session->getFlash('status'));
        // Second read returns default because flash was consumed
        self::assertNull($session->getFlash('status'));
        self::assertSame('empty', $session->getFlash('status', 'empty'));
    }

    public function testSessionCookieParams(): void
    {
        $session = new Session([
            'name' => 'custom_sess',
            'lifetime_seconds' => 3600,
            'cookie_secure' => true,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Strict',
        ], true);

        $params = $session->getCookieParams();
        self::assertSame('custom_sess', $params['name']);
        self::assertSame(3600, $params['lifetime']);
        self::assertTrue($params['secure']);
        self::assertTrue($params['httponly']);
        self::assertSame('Strict', $params['samesite']);
    }
}
