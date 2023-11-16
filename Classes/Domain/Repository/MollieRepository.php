<?php
namespace NeosRulez\Shop\Payment\Mollie\Domain\Repository;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Repository;
use NeosRulez\Shop\Domain\Model\Order;
use NeosRulez\Shop\Payment\Mollie\Domain\Model\Mollie;

/**
 * @Flow\Scope("singleton")
 */
class MollieRepository extends Repository
{

    /**
     * @param int $orderNumber
     * @param string $paymentId
     * @return void
     */
    public function createMolliePayment(int $orderNumber, string $paymentId): void
    {
        $newMollie = new Mollie();
        $newMollie->setOrderNumber($orderNumber);
        $newMollie->setPaymentId($paymentId);
        $this->add($newMollie);
    }

}
