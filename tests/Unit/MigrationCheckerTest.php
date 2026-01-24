<?php

declare(strict_types=1);

namespace Roslov\MigrationChecker\Tests\Unit;

use Codeception\Test\Unit;
use PHPUnit\Framework\Attributes\CoversClass;
use Roslov\MigrationChecker\Contract\EnvironmentInterface;
use Roslov\MigrationChecker\Contract\MigrationInterface;
use Roslov\MigrationChecker\Contract\PrinterInterface;
use Roslov\MigrationChecker\Contract\SchemaStateComparerInterface;
use Roslov\MigrationChecker\Db\State;
use Roslov\MigrationChecker\Exception\SchemaDiffersException;
use Roslov\MigrationChecker\MigrationChecker;
use Roslov\MigrationChecker\Tests\Support\UnitTester;

/**
 * Tests the main migration checker logic.
 */
#[CoversClass(MigrationChecker::class)]
final class MigrationCheckerTest extends Unit
{
    /**
     * @var UnitTester Tester
     */
    protected UnitTester $tester;

    /**
     * Tests successful migration check.
     */
    public function testSuccessfulCheck(): void
    {
        $I = $this->tester;
        $I->wantTo('ensure that migration check is successful');
        $I->amGoingTo('test migration check with two successful iterations');

        $environment = $this->createMock(EnvironmentInterface::class);
        $migration = $this->createMock(MigrationInterface::class);
        $comparer = $this->createMock(SchemaStateComparerInterface::class);
        $printer = $this->createMock(PrinterInterface::class);

        $I->expect('both environment `prepare` and `cleanUp` methods to be called once');
        $environment->expects(self::once())->method('prepare');
        $environment->expects(self::once())->method('cleanUp');

        $I->expect('two iterations, then stop');
        $migration->expects(self::exactly(3))
            ->method('canUp')
            ->willReturnOnConsecutiveCalls(true, true, false);

        $I->comment('per iteration: up, down, then up before next step → 2 ups + 1 down');
        $I->expectTo('have two iterations → 4 ups, 2 downs');
        $migration->expects(self::exactly(4))->method('up');
        $migration->expects(self::exactly(2))->method('down');

        $I->comment('per iteration: `saveState` before `up`, then `saveState` after `down` => 2 saves');
        $I->expectTo('have two iterations → 4 saves');
        $comparer->expects(self::exactly(4))->method('saveState');

        $I->expectTo('call `statesEqual` once per iteration');
        $comparer->expects(self::exactly(2))
            ->method('statesEqual')
            ->willReturn(true);

        $I->expect('the diff will not be displayed');
        $printer->expects(self::never())->method('displayDiff');

        $checker = new MigrationChecker(
            $environment,
            $migration,
            $comparer,
            $printer,
        );

        $checker->check();
    }

    /**
     * Tests failed migration check when down-migration is incorrect.
     */
    public function testFailedCheck(): void
    {
        $I = $this->tester;
        $I->wantTo('ensure that migration check is failed');
        $I->amGoingTo('test migration check with one non-successful iterations');

        $environment = $this->createMock(EnvironmentInterface::class);
        $migration = $this->createMock(MigrationInterface::class);
        $comparer = $this->createMock(SchemaStateComparerInterface::class);
        $printer = $this->createMock(PrinterInterface::class);

        $I->expect('only environment `prepare` method is called');
        $environment->expects(self::once())->method('prepare');
        $environment->expects(self::never())->method('cleanUp');

        $I->expect('two iterations, then failure');
        $migration->expects(self::once())
            ->method('canUp')
            ->willReturn(true);

        $I->expect('migration `up` and `down` methods are called once and migration state will be saved twice');
        $migration->expects(self::once())->method('up');
        $migration->expects(self::once())->method('down');
        $comparer->expects(self::exactly(2))->method('saveState');

        $I->expect('migration state will be compared unsuccessfully and diff will be displayed');
        $previousState = new State('previous-state');
        $currentState = new State('current-state');
        $comparer->expects(self::once())->method('statesEqual')->willReturn(false);
        $comparer->expects(self::once())->method('getPreviousState')->willReturn($previousState);
        $comparer->expects(self::once())->method('getCurrentState')->willReturn($currentState);
        $printer->expects(self::once())->method('displayDiff')->with($previousState, $currentState);

        $checker = new MigrationChecker(
            $environment,
            $migration,
            $comparer,
            $printer,
        );

        $I->expect('the check will fail with an exception');
        $this->expectException(SchemaDiffersException::class);

        $checker->check();
    }
}
