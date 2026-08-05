<?php

declare(strict_types=1);

/*
 * This file is part of Import From CSV Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/import-from-csv-bundle
 */

namespace Markocupic\ImportFromCsvBundle\Tests\Import;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DC_Table;
use Contao\TestCase\ContaoTestCase;
use Contao\Widget;
use Markocupic\ImportFromCsvBundle\Import\WidgetFactory;
use Markocupic\ImportFromCsvBundle\Tests\Fixtures\SpyWidget;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class WidgetFactoryTest extends ContaoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        SpyWidget::$lastGetAttributesArgs = null;

        // In Contao maps $GLOBALS['BE_FFL'] the inputType to its widget class.
        // We register our spy widget as the "text" (fallback) widget.
        $GLOBALS['BE_FFL'] = [
            'text' => SpyWidget::class,
        ];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['BE_FFL']);
        SpyWidget::$lastGetAttributesArgs = null;

        parent::tearDown();
    }

    public function testCreateReturnsWidgetOfResolvedInputType(): void
    {
        $GLOBALS['BE_FFL']['myInput'] = SpyWidget::class;

        $factory = new WidgetFactory($this->mockFrameworkWithoutRequest(), new RequestStack());

        $widget = $factory->create(['inputType' => 'myInput'], 'firstname', 'tl_member', 'Marko');

        $this->assertInstanceOf(Widget::class, $widget);
        $this->assertInstanceOf(SpyWidget::class, $widget);

        // The attributes returned by getAttributesFromDca (via the framework adapter)
        // are passed into the widget constructor.
        $this->assertSame(['generated' => true], $widget->capturedAttributes);
        $this->assertSame('firstname', SpyWidget::$lastGetAttributesArgs['strName']);
        $this->assertSame('tl_member', SpyWidget::$lastGetAttributesArgs['strTable']);
    }

    public function testUnknownInputTypeFallsBackToTextWidget(): void
    {
        $factory = new WidgetFactory($this->mockFrameworkWithoutRequest(), new RequestStack());

        // 'doesNotExist' is not registered in BE_FFL -> must fall back to 'text'.
        $widget = $factory->create(['inputType' => 'doesNotExist'], 'firstname', 'tl_member', 'Marko');

        $this->assertInstanceOf(SpyWidget::class, $widget);
    }

    public function testDcTableIsNullWithoutRequest(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework
            ->method('getAdapter')
            ->willReturnCallback(static fn (string $class): Adapter => new Adapter($class))
        ;

        // Without a request no DC_Table may be instantiated.
        $framework
            ->expects($this->never())
            ->method('createInstance')
        ;

        $factory = new WidgetFactory($framework, new RequestStack());

        $factory->create(['inputType' => 'text'], 'firstname', 'tl_member', 'Marko');

        // getDcTable() returned null, which is handed to getAttributesFromDca as $objDca.
        $this->assertNull(SpyWidget::$lastGetAttributesArgs['objDca']);
    }

    public function testDcTableIsCreatedOnceAndCachedWithRequest(): void
    {
        $dcTable = $this->createMock(DC_Table::class);

        $framework = $this->createMock(ContaoFramework::class);
        $framework
            ->method('getAdapter')
            ->willReturnCallback(static fn (string $class): Adapter => new Adapter($class))
        ;

        // Proof of caching: two create() calls for the same table, but the
        // DC_Table is instantiated only ONCE.
        $framework
            ->expects($this->once())
            ->method('createInstance')
            ->with(DC_Table::class, ['tl_member'])
            ->willReturn($dcTable)
        ;

        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $factory = new WidgetFactory($framework, $requestStack);

        $factory->create(['inputType' => 'text'], 'firstname', 'tl_member', 'Marko');
        $factory->create(['inputType' => 'text'], 'lastname', 'tl_member', 'Cupic');

        // The cached DC_Table is passed as the DataContainer context.
        $this->assertSame($dcTable, SpyWidget::$lastGetAttributesArgs['objDca']);
    }

    private function mockFrameworkWithoutRequest(): ContaoFramework
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework
            ->method('getAdapter')
            ->willReturnCallback(static fn (string $class): Adapter => new Adapter($class))
        ;

        return $framework;
    }
}
