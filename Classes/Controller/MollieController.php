<?php
namespace NeosRulez\Shop\Payment\Mollie\Controller;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use NeosRulez\Shop\Domain\Repository\OrderRepository;
use NeosRulez\Shop\Payment\Mollie\Domain\Repository\MollieRepository;
use NeosRulez\Shop\Payment\Mollie\Payment\Mollie;
use NeosRulez\Shop\Service\FinisherService;

/**
 * @Flow\Scope("singleton")
 */
class MollieController extends ActionController
{

    /**
     * @Flow\Inject
     * @var Mollie
     */
    protected $mollie;

    /**
     * @Flow\Inject
     * @var MollieRepository
     */
    protected $mollieRepository;

    /**
     * @Flow\Inject
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @Flow\Inject
     * @var FinisherService
     */
    protected $finisherService;

    /**
     * @return void
     */
    public function webhookAction(string $orderId): bool
    {
        $paymentId = $this->mollie->webhook();
        if($paymentId !== '') {
            $mollie = new Mollie\Api\MollieApiClient();
            $mollie->setApiKey($this->mollie['args']['apiKey']);
            $payment = $mollie->payments->get($paymentId);

            if($payment->isPaid()) {
                $order = $this->orderRepository->findByOrderNumber($orderId);
                $order->setPaid(true);
                $this->orderRepository->update($order);
                $this->persistenceManager->persistAll();

                $this->finisherService->initAfterPaymentFinishers($order->getInvoicedata());
                $this->view->assign('value', ['response' => true]);
            }
            $this->view->assign('value', ['response' => false]);
        }
        $this->view->assign('value', ['response' => false]);
    }

}
