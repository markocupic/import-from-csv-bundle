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

namespace Markocupic\ImportFromCsvBundle\Reader;

final class CsvLineReader
{
    /**
     * @return list<string>
     */
    public function readLines(\SplFileObject $file, int $offset = 0, int $limit = -1): array
    {
        $file->setFlags(\SplFileObject::DROP_NEW_LINE | \SplFileObject::SKIP_EMPTY | \SplFileObject::READ_AHEAD);

        $rows = [];

        foreach (new \LimitIterator($file, $offset, $limit) as $line) {
            $rows[] = $line;
        }

        return $rows;
    }

    public function countLines(\SplFileObject $file): int
    {
        $file->setFlags(\SplFileObject::READ_AHEAD | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);

        $count = iterator_count($file); // iterates once through the file, O(1) memory usage
        $file->rewind(); // reset pointer in case the file is read afterwards

        return $count;
    }
}
