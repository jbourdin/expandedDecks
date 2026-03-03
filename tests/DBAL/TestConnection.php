<?php

declare(strict_types=1);

/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\DBAL;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;

/**
 * Connection wrapper that intercepts transaction calls during test execution.
 *
 * In normal mode (during schema creation + fixture loading), all calls pass through.
 * In test mode (after beginTestTransaction()), application-level beginTransaction()
 * creates savepoints, commit() releases them, and rollBack() rolls back to them.
 * The outer test transaction stays open until rollbackTestTransaction() is called.
 */
class TestConnection extends AbstractConnectionMiddleware
{
    private bool $testTransactionActive = false;
    private int $savepointCounter = 0;

    public function __construct(Connection $connection)
    {
        parent::__construct($connection);
    }

    /**
     * Begin the outer test transaction. Called in setUp().
     */
    public function beginTestTransaction(): void
    {
        parent::beginTransaction();
        $this->testTransactionActive = true;
        $this->savepointCounter = 0;
    }

    /**
     * Roll back the outer test transaction. Called in tearDown().
     */
    public function rollbackTestTransaction(): void
    {
        parent::rollBack();
        $this->testTransactionActive = false;
        $this->savepointCounter = 0;
    }

    public function beginTransaction(): void
    {
        if ($this->testTransactionActive) {
            ++$this->savepointCounter;
            parent::exec('SAVEPOINT test_sp_'.$this->savepointCounter);

            return;
        }

        parent::beginTransaction();
    }

    public function commit(): void
    {
        if ($this->testTransactionActive) {
            if ($this->savepointCounter > 0) {
                parent::exec('RELEASE SAVEPOINT test_sp_'.$this->savepointCounter);
                --$this->savepointCounter;
            }

            // At level 0: no-op (outer test transaction stays open)
            return;
        }

        parent::commit();
    }

    public function rollBack(): void
    {
        if ($this->testTransactionActive) {
            if ($this->savepointCounter > 0) {
                parent::exec('ROLLBACK TO SAVEPOINT test_sp_'.$this->savepointCounter);
                --$this->savepointCounter;
            }

            // At level 0: no-op
            return;
        }

        parent::rollBack();
    }
}
