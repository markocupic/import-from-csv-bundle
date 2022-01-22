<?php

declare(strict_types=1);

/*
 * This file is part of Import From CSV Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/import-from-csv-bundle
 */

namespace Markocupic\ImportFromCsvBundle\Import\Field;

use Contao\BackendUser;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\Input;
use Contao\StringUtil;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

class Formatter
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var EncoderFactoryInterface 
     */
    private $encoderFactory;

    public function __construct(ContaoFramework $framework, EncoderFactoryInterface $encoderFactory)
    {
        $this->framework = $framework;
        $this->encoderFactory = $encoderFactory;
    }

    /**
     * @param Field $objField
     * @return void
     */
    public function setCorrectDateFormat(Field $objField): void
    {
        $value = $objField->getValue();
        $arrDca = $objField->getDca();
        $rgxp = $arrDca['eval']['rgxp'] ?? null;

        if (('date' === $rgxp || 'datim' === $rgxp || 'time' === $rgxp) && '' !== $value) {
            $configAdapter = $this->framework->getAdapter(Config::class);
            $df = $configAdapter->get($rgxp.'Format');

            if (false !== ($tstamp = strtotime($objField->getValue()))) {
                $objField->setValue(date($df, $tstamp));

                if (null !== ($objWidget = $objField->getWidget())) {
                    $objWidget->value = $objField->getValue();
                }
                $inputAdapter = $this->framework->getAdapter(Input::class);
                $inputAdapter->setPost($objField->getName(), $objField->getValue());
            }
        }
    }

    /**
     * @param Field $objField
     * @param string $strArrDelim
     * @return void
     */
    public function setCorrectArrayValue(Field $objField, string $strArrDelim): void
    {
        $arrDca = $objField->getDca();

        $objWidget = $objField->getWidget();

        $value = $objField->getValue();

        if (!\is_array($value) && $objWidget && isset($arrDca['eval']['multiple']) && $arrDca['eval']['multiple']) {
            // Convert CSV fields
            if (isset($arrDca['eval']['csv'])) {
                if (null === $value || '' === $value) {
                    $objField->setValue([]);
                } else {
                    $objField->setValue(explode($arrDca['eval']['csv'], $value));
                }
            } elseif (false !== strpos($value, $strArrDelim)) {
                // Value is e.g. 3||4
                $objField->setValue(explode($strArrDelim, $value));
            } else {
                // The value is a serialized array or simple value e.g 3
                $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
                $objField->setValue($stringUtilAdapter->deserialize($value, true));
            }

            $inputAdapter = $this->framework->getAdapter(Input::class);
            $inputAdapter->setPost($objField->getName(), $objField->getValue());
            $objWidget->value = $objField->getValue();
        }
    }

    /**
     * @param Field $objField
     * @return void
     */
    public function convertDateToTimestamp(Field $objField): void
    {
        $value = $objField->getValue();
        $arrDca = $objField->getDca();
        $rgxp = $arrDca['eval']['rgxp'] ?? null;

        if (('date' === $rgxp || 'datim' === $rgxp || 'time' === $rgxp) && '' !== $value) {
            if (false !== ($tstamp = strtotime($objField->getValue()))) {
                $objField->setValue($tstamp);

                if (null !== ($objWidget = $objField->getWidget())) {
                    $objWidget->value = $objField->getValue();
                }
                $inputAdapter = $this->framework->getAdapter(Input::class);
                $inputAdapter->setPost($objField->getName(), $objField->getValue());
            }
        }
    }

    /**
     * @param Field $objField
     * @return void
     */
    public function encodePassword(Field $objField): void
    {
        $arrDca = $objField->getDca();

        // Encode password, if validation was skipped
        if (($arrDca['inputType'] ?? null) && 'password' === $arrDca['inputType']) {
            if (\is_string($objField->getValue()) && '' !== $objField->getValue()) {
                if ('tl_user' === $objField->getTableName()) {
                    $encoder = $this->encoderFactory->getEncoder(BackendUser::class);
                } else {
                    $encoder = $this->encoderFactory->getEncoder(FrontendUser::class);
                }
                $objField->setValue($encoder->encodePassword($objField->getValue(), null));

                if (null !== ($objWidget = $objField->getWidget())) {
                    $objWidget->value = $objField->getValue();
                }
                $inputAdapter = $this->framework->getAdapter(Input::class);
                $inputAdapter->setPost($objField->getName(), $objField->getValue());
            }
        }
    }

    /**
     * @param Field $objField
     * @return void
     */
    public function setCorrectEmptyValue(Field $objField): void
    {
        $objWidget = $objField->getWidget();
        $arrDca = $objField->getDca();

        // Set the correct empty value
        if ($arrDca && $objWidget && '' === $objField->getValue()) {
            $objField->setValue($objWidget->getEmptyValue());

            // Set the correct empty value
            if (empty($objField->getValue())) {
                /*
                 * Hack Because Contao doesn't handle correct empty string input f.ex username
                 * @see https://github.com/contao/core-bundle/blob/master/src/Resources/contao/library/Contao/Widget.php#L1526-1527
                 */
                if (($arrDca['sql'] ?? null) && '' !== $arrDca['sql']) {
                    $sql = $arrDca['sql'];

                    if (false === strpos($sql, 'NOT NULL')) {
                        if (false !== strpos($sql, 'NULL')) {
                            $objField->setValue(null);


                        }
                    }
                }
            }

            $objWidget->value = $objField->getValue();
            $inputAdapter = $this->framework->getAdapter(Input::class);
            $inputAdapter->setPost($objField->getName(), $objField->getValue());

        }
    }
}
