<?php

declare(strict_types=1);

/*
 * This file is part of Import From CSV Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/import-from-csv-bundle
 */

namespace Markocupic\ImportFromCsvBundle\Listener\ContaoHooks;

use Contao\CoreBundle\ServiceAnnotation\Hook;
use Markocupic\ImportFromCsvBundle\Import\Field\Field;
use Markocupic\ImportFromCsvBundle\Import\ImportFromCsv;

/**
 * @Hook(ImportFromCsvHookExample::HOOK, priority=ImportFromCsvHookExample::PRIORITY)
 */
class ImportFromCsvHookExample
{
    public const HOOK = 'importFromCsv';
    public const PRIORITY = 100;

    /**
     * @var string
     */
    private $curlErrorMsg;

    public function __invoke(Field $objField, int $line, ImportFromCsv $objBackendModule = null): void
    {
        // tl_member
        if ('tl_super_member' === $objField->getTablename()) {
            // Get geolocation from a given address
            if ('geolocation' === $objField->getName()) {
                // Do custom validation and skip the Contao-Widget-Input-Validation
                $objField->setSkipWidgetValidation(true);

                $arrRecord = $objField->getRecord();

                $strStreet = $arrRecord['street'];
                $strCity = $arrRecord['city'];
                $strCountry = $arrRecord['country'];

                $strStreet = str_replace(' ', '+', $strStreet);
                $strCity = str_replace(' ', '+', $strCity);
                $strAddress = $strStreet.',+'.$strCity.',+'.$strCountry;

                // Get Position from GoogleMaps
                $arrPos = $this->curlGetCoordinates(sprintf('http://maps.googleapis.com/maps/api/geocode/json?address=%s&sensor=false', $strAddress));

                if (null !== $arrPos && \is_array($arrPos['results'][0]['geometry'])) {
                    $latPos = $arrPos['results'][0]['geometry']['location']['lat'];
                    $lngPos = $arrPos['results'][0]['geometry']['location']['lng'];

                    $objField->setValue($latPos.','.$lngPos);
                } else {
                    // Error handling
                    if ('' !== $this->curlErrorMsg) {
                        $objField->addError($this->curlErrorMsg);
                    } else {
                        $objField->addError(sprintf('Setting geolocation for (%s) failed!', $strAddress));
                    }
                }
            }
        }
    }

    /**
     * Curl helper method.
     */
    private function curlGetCoordinates(string $url): ?array
    {
        // is cURL installed on the webserver?
        if (!\function_exists('curl_init')) {
            $this->curlErrorMsg = 'Sorry cURL is not installed on your webserver!';

            return null;
        }

        // Set a timout to avoid the OVER_QUERY_LIMIT
        usleep(25000);

        // Create a new cURL resource handle
        $ch = curl_init();

        // Set URL to download
        curl_setopt($ch, CURLOPT_URL, $url);

        // Should cURL return or print out the data? (true = return, false = print)
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Timeout in seconds
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        // Download the given URL, and return output
        $arrOutput = json_decode(curl_exec($ch), true);

        // Close the cURL resource, and free system resources
        curl_close($ch);

        return $arrOutput;
    }
}
