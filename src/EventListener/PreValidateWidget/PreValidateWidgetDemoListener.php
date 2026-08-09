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

namespace Markocupic\ImportFromCsvBundle\EventListener\PreValidateWidget;

use Markocupic\ImportFromCsvBundle\Event\PreValidateWidgetEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: PreValidateWidgetEvent::NAME)]
class PreValidateWidgetDemoListener
{
    private string|null $curlErrorMsg;

    public function __invoke(PreValidateWidgetEvent $event): void
    {
        $widget = $event->getWidget();
        $record = $event->getCsvRecord();

        // tl_member
        if ('tl_super_member' === $widget->strTable) {
            // Get geolocation from a given address
            if ('geolocation' === $widget->strField) {
                $strStreet = $record['street'];
                $strCity = $record['city'];
                $strCountry = $record['country'];

                $strStreet = str_replace(' ', '+', $strStreet);
                $strCity = str_replace(' ', '+', $strCity);
                $strAddress = $strStreet.',+'.$strCity.',+'.$strCountry;

                // Get Position from Google Maps
                $coords = $this->curlGetCoordinates(\sprintf('https://maps.googleapis.com/maps/api/geocode/json?address=%s&sensor=false', $strAddress));

                if (null !== $coords && \is_array($coords['results'][0]['geometry'])) {
                    $latPos = $coords['results'][0]['geometry']['location']['lat'];
                    $lngPos = $coords['results'][0]['geometry']['location']['lng'];

                    $widget->value = $latPos.','.$lngPos;
                } else {
                    // Error handling
                    if ('' !== $this->curlErrorMsg) {
                        $widget->addError($this->curlErrorMsg);
                    } else {
                        $widget->addError(\sprintf('Setting geolocation for (%s) failed!', $strAddress));
                    }
                }
            }
        }
    }

    /**
     * Curl helper method.
     */
    private function curlGetCoordinates(string $url): array|null
    {
        // is cURL installed on the webserver?
        if (!\function_exists('curl_init')) {
            $this->curlErrorMsg = 'Sorry cURL is not installed on your webserver!';

            return null;
        }

        // Set a timeout to avoid the OVER_QUERY_LIMIT
        usleep(25000);

        // Create a new cURL resource handle
        $ch = curl_init();

        // Set URL to download
        curl_setopt($ch, CURLOPT_URL, $url);

        // Should cURL return or print out the data? (true = return, false = print)
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Timeout in seconds
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        // Download the given URL and return output
        $dataCoord = json_decode(curl_exec($ch), true);

        // Close the cURL resource, and free system resources
        curl_close($ch);

        return $dataCoord;
    }
}
