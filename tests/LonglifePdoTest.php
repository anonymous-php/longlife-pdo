<?php

use PHPUnit\Framework\TestCase;


final class MysqliPDOTest extends TestCase
{

    public function testStatementCache()
    {
        list($host, $port, $dbname) = array(getenv('host'), getenv('port'), getenv('dbname'));

        $pdo = new \Anonymous\Longlife\LonglifePdo(
            "mysql:host={$host};port={$port};dbname={$dbname}",
            getenv('username'),
            getenv('password')
        );

        $query = "SELECT * FROM `test` WHERE id = :id";

        $statement1 = $pdo->prepare($query);
        $this->assertInstanceOf(\PDOStatement::class, $statement1);

        $statement2 = $pdo->prepare($query);
        $this->assertInstanceOf(\PDOStatement::class, $statement2);

        $this->assertNotSame($statement1, $statement2);

        $pdo->setStatementsCacheLimit(10);

        $statement1 = $pdo->prepare($query);
        $this->assertInstanceOf(\PDOStatement::class, $statement1);

        $statement2 = $pdo->prepare($query);
        $this->assertInstanceOf(\PDOStatement::class, $statement2);

        $this->assertSame($statement1, $statement2);
    }

    /**
     * @expectedException \Exception
     */
    public function testErrorOnLostConnection()
    {
        list($host, $port, $dbname) = array(getenv('host'), getenv('port'), getenv('dbname'));

        $pdo1 = new \Anonymous\Longlife\LonglifePdo(
            "mysql:host={$host};port={$port};dbname={$dbname}",
            getenv('username'),
            getenv('password')
        );

        $pdo2 = new \Anonymous\Longlife\LonglifePdo(
            "mysql:host={$host};port={$port};dbname={$dbname}",
            getenv('username'),
            getenv('password')
        );

        $connection1 = $pdo1->getPdo();
        $this->assertTrue((bool)$connection1->query('SELECT 1 + 1'));

        $query = "SHOW PROCESSLIST";
        $processes = $pdo1->fetchAll($query);

        foreach ($processes as $process) {
            if ($process['Info'] != $query) {
                continue;
            }

            $pdo2->exec("KILL {$process['Id']}");
        }

        $connection2 = $pdo1->getPdo();
        $this->assertSame($connection1, $connection2);

        set_error_handler(function () { throw new Exception(); });
        $connection2->query('SELECT 1 + 1');
        restore_error_handler();
    }

    public function testReconnection()
    {
        list($host, $port, $dbname) = array(getenv('host'), getenv('port'), getenv('dbname'));

        $pdo1 = new \Anonymous\Longlife\LonglifePdo(
            "mysql:host={$host};port={$port};dbname={$dbname}",
            getenv('username'),
            getenv('password')
        );

        $pdo1->setCheckConnectionTimeout(1);

        $pdo2 = new \Anonymous\Longlife\LonglifePdo(
            "mysql:host={$host};port={$port};dbname={$dbname}",
            getenv('username'),
            getenv('password')
        );

        $connection1 = $pdo1->getPdo();
        $this->assertTrue((bool)$connection1->query('SELECT 1 + 1'));

        $query = "SHOW PROCESSLIST";
        $processes = $pdo1->fetchAll($query);

        foreach ($processes as $process) {
            if ($process['Info'] != $query) {
                continue;
            }

            $pdo2->exec("KILL {$process['Id']}");
            sleep(2);
        }

        $connection2 = $pdo1->getPdo();
        $this->assertTrue((bool)$connection2->query('SELECT 1 + 1'));

        $this->assertNotSame($connection1, $connection2);
    }

}