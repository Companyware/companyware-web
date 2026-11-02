<?php
declare(strict_types=1);
namespace Companyware\Core\Framework\Api;
/** @api since Companyware public API 1.0 */
interface ApiOperationProviderInterface
{
    /** @return array<string,array<string,mixed>> */
    public function paths():array;
}
