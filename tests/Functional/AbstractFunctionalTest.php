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

namespace App\Tests\Functional;

use App\DataFixtures\DevFixtures;
use App\Tests\DBAL\StaticDriver;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AbstractFunctionalTest extends WebTestCase
{
    private static bool $fixturesLoaded = false;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        if (!self::$fixturesLoaded) {
            $this->initializeDatabase();
            self::$fixturesLoaded = true;
        }

        $conn = StaticDriver::getConnection();
        \assert(null !== $conn);
        $conn->beginTestTransaction();
    }

    protected function tearDown(): void
    {
        $conn = StaticDriver::getConnection();
        if (null !== $conn) {
            $conn->rollbackTestTransaction();
        }

        parent::tearDown();
    }

    protected function loginAs(string $email): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Login', [
            '_email' => $email,
            '_password' => 'password',
        ]);
    }

    private function initializeDatabase(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $metadata = $em->getMetadataFactory()->getAllMetadata();

        $schemaTool = new SchemaTool($em);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $loader = new Loader();
        /** @var DevFixtures $fixture */
        $fixture = static::getContainer()->get(DevFixtures::class);
        $loader->addFixture($fixture);

        $executor = new ORMExecutor($em, new ORMPurger());
        $executor->execute($loader->getFixtures());
    }
}
