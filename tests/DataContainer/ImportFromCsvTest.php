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

namespace Markocupic\ImportFromCsvBundle\Tests\DataContainer;

use Markocupic\ImportFromCsvBundle\DataContainer\ImportFromCsv;
use PHPUnit\Framework\TestCase;

class ImportFromCsvTest extends TestCase
{
    public function testFormatsTheSqlDefinitionAsPlainText(): void
    {
        $method = new \ReflectionMethod(ImportFromCsv::class, 'formatSqlDefinition');
        $instance = (new \ReflectionClass(ImportFromCsv::class))->newInstanceWithoutConstructor();

        $this->assertSame("varchar(255) NOT NULL default ''", $method->invoke($instance, "varchar(255) NOT NULL default ''"));
        $this->assertSame('integer, unsigned, autoincrement', $method->invoke($instance, ['type' => 'integer', 'unsigned' => true, 'autoincrement' => true]));
        $this->assertSame("string(255), default: ''", $method->invoke($instance, ['type' => 'string', 'length' => 255, 'default' => '']));
        $this->assertSame("boolean, default: 'false'", $method->invoke($instance, ['type' => 'boolean', 'default' => false]));
        $this->assertSame('text, nullable', $method->invoke($instance, ['type' => 'text', 'notnull' => false]));
        $this->assertSame('', $method->invoke($instance, null));

        foreach ([$method->invoke($instance, ['type' => 'string', 'length' => 255, 'default' => '<b>'])] as $label) {
            $this->assertStringNotContainsString('<span', $label);
        }
    }
}
