<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Database;

use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SitePortal\Core\Database\Connection;

final class ConnectionTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        $this->db = Connection::create([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
    }

    public function testConnectionAttributesAndDriver(): void
    {
        self::assertTrue($this->db->isSqlite());
        self::assertFalse($this->db->isMysql());
        self::assertSame('sqlite', $this->db->getDriver());
        self::assertInstanceOf(PDO::class, $this->db->getPdo());
    }

    public function testQueryAndFetchPreparedStatements(): void
    {
        $this->db->query('CREATE TABLE test_items (id INTEGER PRIMARY KEY, title TEXT)');
        $affected = $this->db->execute(
            'INSERT INTO test_items (id, title) VALUES (:id, :title)',
            ['id' => 1, 'title' => 'Örnek Başlık']
        );

        self::assertSame(1, $affected);

        $row = $this->db->fetch('SELECT * FROM test_items WHERE id = :id', ['id' => 1]);
        self::assertIsArray($row);
        self::assertSame(1, (int) $row['id']);
        self::assertSame('Örnek Başlık', $row['title']);

        $all = $this->db->fetchAll('SELECT * FROM test_items');
        self::assertCount(1, $all);
    }

    public function testTransactionCommit(): void
    {
        $this->db->query('CREATE TABLE test_tx (id INTEGER PRIMARY KEY, val TEXT)');

        $result = $this->db->transaction(function (Connection $db): string {
            $db->execute('INSERT INTO test_tx (id, val) VALUES (1, "A")');
            $db->execute('INSERT INTO test_tx (id, val) VALUES (2, "B")');
            return 'done';
        });

        self::assertSame('done', $result);
        $rows = $this->db->fetchAll('SELECT * FROM test_tx');
        self::assertCount(2, $rows);
    }

    public function testTransactionRollbackOnException(): void
    {
        $this->db->query('CREATE TABLE test_tx_fail (id INTEGER PRIMARY KEY, val TEXT)');

        try {
            $this->db->transaction(function (Connection $db): void {
                $db->execute('INSERT INTO test_tx_fail (id, val) VALUES (1, "A")');
                throw new RuntimeException('Zorunlu hata');
            });
        } catch (RuntimeException $e) {
            self::assertSame('Zorunlu hata', $e->getMessage());
        }

        $rows = $this->db->fetchAll('SELECT * FROM test_tx_fail');
        self::assertCount(0, $rows);
    }
}
