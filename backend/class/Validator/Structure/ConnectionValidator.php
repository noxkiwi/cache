<?php declare(strict_types = 1);
namespace noxkiwi\cache\Validator\Structure;

use noxkiwi\validator\Validator\Number\NaturalValidator;
use noxkiwi\validator\Validator\Number\PortValidator;
use noxkiwi\validator\Validator\StructureValidator;
use noxkiwi\validator\Validator\TextValidator;

/**
 * I am the validator for a cache server connection.
 *
 * @package      noxkiwi\cache
 * @author       Jan Nox <jan.nox@pm.me>
 * @license      https://nox.kiwi/license
 * @copyright    2021 noxkiwi
 * @version      1.0.0
 * @link         https://nox.kiwi/
 */
final class ConnectionValidator extends StructureValidator
{
    /**
     * @inheritDoc
     */
    protected function __construct(array $options = null)
    {
        parent::__construct($options);
        $this->structureDesign = [
            'host'    => TextValidator::class,
            'port'    => PortValidator::class,
            'timeout' => NaturalValidator::class
        ];
    }
}
