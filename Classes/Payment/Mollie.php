<?php
namespace NeosRulez\Shop\Payment\Mollie\Payment;

use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Doctrine\ORM\Mapping as ORM;
use NeosRulez\Shop\Domain\Model\Order;
use NeosRulez\Shop\Payment\Payment\AbstractPayment;
use NeosRulez\Shop\Payment\Mollie\Domain\Repository\MollieRepository;
use Mollie\MollieClient;

/**
 * @Flow\Scope("singleton")
 */
class Mollie extends AbstractPayment
{

    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @Flow\Inject
     * @var MollieRepository
     */
    protected $mollieRepository;

    /**
     * @param array $payment
     * @param array $args
     * @param string $successUri
     * @return string
     */
    public function execute(array $payment, array $args, string $successUri): string
    {
        $order = $this->orderRepository->findByOrderNumber($args['order_number']);
        $order->setCanceled(false);
        $order->setDone(true);
        $this->orderRepository->update($order);
        return $this->createPayment($payment['apiKey'], number_format($args['summary']['total'], 2, '.', ''), $args['order_number'], $successUri);
    }

    /**
     * @param string $apiKey
     * @param int $total
     * @param string $orderNumber
     * @param string $successUri
     * @return string
     */
    private function createPayment(string $apiKey, int $total, string $orderNumber, string $successUri): string
    {
        $mollie = new Mollie\Api\MollieApiClient();

        $mollie->setApiKey($apiKey);
        $uri = parse_url($successUri);
        $mollieWebhookUrl =  $uri['scheme'] . '://' . $uri['host'] . '/payments/webhook/' . $orderNumber;

        $payment = $mollie->payments->create([
            "amount" => [
                "currency" => "EUR",
                "value" => $total
            ],
            "description" => 'Order: ' . $orderNumber,
            "redirectUrl" => $successUri,
            "webhookUrl" => $mollieWebhookUrl
        ]);

        $this->molllieRepository->createMolliePayment($orderNumber, $payment->getId());

        return $payment->getCheckoutUrl();
    }

    /**
     * @return array
     */
    public function getPaymentNodeSecrets(): array
    {
        $context = $this->contextFactory->create();
        $paymentNode = (new FlowQuery(array($context->getCurrentSiteNode())))->find('[instanceof NeosRulez.Shop.Payment.Mollie:Payment.Mollie]')->context(array('workspaceName' => 'live'))->filter('[_hidden=false]')->sort('_index', 'ASC')->get();
        if(count($paymentNode) > 0 && $paymentNode[0]->hasProperty('webhookEndpointKey') && $paymentNode[0]->hasProperty('mollieApiKey')) {
            return [
                'webhookEndpointKey' => $paymentNode[0]->getProperty('webhookEndpointKey'),
                'mollieApiKey' => $paymentNode[0]->getProperty('apiKey')
            ];
        }
        return [];
    }

    /**
     * @return string
     */
    public function webhook(): string
    {
        $paymentNode = $this->getPaymentNodeSecrets();
        if(!empty($paymentNode)) {

            $mollieApiKey = $paymentNode['mollieApiKey'];
            $mollie =  new Mollie\Api\MollieApiClient();
            $mollie->setApiKey($mollieApiKey);

            $mollieWebhookKey = $paymentNode['webhookEndpointKey'];
            $payment = null;

            try {
                $payment = $mollie->webhooks->process($_POST, $mollieWebhookKey);
            } catch(\UnexpectedValueException $e) {
                // Invalid payload
                http_response_code(400);
                exit();
            } catch (Mollie\Api\Exceptions\ApiException $e) {
                // Invalid signature
                http_response_code(400);
                exit();
            }

            if ($payment !== null && $payment->getType() === 'payment.paid') {
                return $payment.getId();
            }
            else{
                return '';
            }
        }
        return '';
    }
}

