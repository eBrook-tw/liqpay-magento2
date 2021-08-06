<?php
namespace Pronko\LiqPayRedirect\Model;

use InvalidArgumentException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Helper\Data;
use Magento\Sales\Api\Data\OrderInterface;
use Pronko\LiqPayApi\Api\Data\PaymentMethodCodeInterface;
use Pronko\LiqPayGateway\Gateway\Config;
use Pronko\LiqPayGateway\Gateway\Request\Encoder;
use Pronko\LiqPayGateway\Gateway\Request\SignatureFactory;
use Pronko\LiqPayGateway\Gateway\Validator\CurrencyValidator;
use Pronko\LiqPaySdk\Api\CheckoutRedirectUrlInterface;

class LiqPayServer
{
    const CURRENCY_RUB = 'RUB';
    const CURRENCY_RUR = 'RUR';

    // success
    const STATUS_SUCCESS           = 'success';
    const STATUS_WAIT_COMPENSATION = 'wait_compensation';
    // const STATUS_SUBSCRIBED        = 'subscribed';

    // processing
    const STATUS_PROCESSING  = 'processing';

    // failure
    const STATUS_FAILURE     = 'failure';
    const STATUS_ERROR       = 'error';

    // wait
    const STATUS_WAIT_SECURE = 'wait_secure';
    const STATUS_WAIT_ACCEPT = 'wait_accept';
    const STATUS_WAIT_CARD   = 'wait_card';

    // sandbox
    const STATUS_SANDBOX     = 'sandbox';


    /**
     * @var Config
     */
    private Config $config;
    /**
     * @var CurrencyValidator
     */
    private CurrencyValidator $currencyValidator;

    /**
     * @var Encoder
     */
    private $encoder;

    /**
     * @var Json
     */
    private $serializer;

    private SignatureFactory $signatureFactory;

    private Data $paymentHelper;

    public function __construct(
        Config $config,
        CurrencyValidator $currencyValidator,
        Encoder $encoder,
        Json $serializer,
        SignatureFactory $signatureFactory,
        Data $paymentHelper
    ) {
        $this->config = $config;
        $this->currencyValidator = $currencyValidator;
        $this->encoder = $encoder;
        $this->serializer = $serializer;
        $this->signatureFactory = $signatureFactory;
        $this->paymentHelper = $paymentHelper;
    }

    /**
     * @param array $params
     * @return string
     */
    public function cnbForm(array $params): string
    {
        $language = 'ru';
        if (isset($params['language']) && $params['language'] == 'en') {
            $language = 'en';
        }

        $params = $this->cnbParams($params);
        $data = $this->encodeParams($params);
        $signature = $this->cnbSignature($params);

        return sprintf(
            '
            <form method="POST" action="%s" accept-charset="utf-8" target="POPUPW">
                %s
                %s
                <input type="image" src="//static.liqpay.ua/buttons/p1%s.radius.png" name="btn_text" />
            </form>
            ',
            $this->_getRedirectUrl(),
            sprintf('<input type="hidden" name="%s" value="%s" />', 'data', $data),
            sprintf('<input type="hidden" name="%s" value="%s" />', 'signature', $signature),
            $language
        );
    }

    /**
     * @param OrderInterface|null $order
     * @return string
     */
    public function getLiqPayDescription(OrderInterface $order = null): string
    {
        $description = trim($this->config->getOrderDescription($order->getStoreId()));
        $params = [
            '{order_id}' => $order->getIncrementId()
        ];
        return strtr($description, $params);
    }

    /**
     * @return string
     */
    protected function _getRedirectUrl(): string
    {
        return CheckoutRedirectUrlInterface::REDIRECT_URL;
    }

    /**
     * cnb_params
     *
     * @param array $params
     *
     * @return array $params
     */
    private function cnbParams($params)
    {
        $params['public_key'] = $this->config->getPublicKey();

        if (!isset($params['version'])) {
            throw new InvalidArgumentException('version is null');
        }
        if (!isset($params['amount'])) {
            throw new InvalidArgumentException('amount is null');
        }
        if (!isset($params['currency'])) {
            throw new InvalidArgumentException('currency is null');
        }
        if (!$this->currencyValidator->validate($params)->isValid()) {
            throw new InvalidArgumentException('currency is not supported');
        }
        if ($params['currency'] == self::CURRENCY_RUR) {
            $params['currency'] = self::CURRENCY_RUB;
        }
        if (!isset($params['description'])) {
            throw new InvalidArgumentException('description is null');
        }

        return $params;
    }

    /**
     * encode_params
     *
     * @param array $params
     * @return string
     */
    private function encodeParams(array $params): string
    {
        return $encryptedData = $this->encoder->encode($this->serializer->serialize($params));
    }

    /**
     * @param $params
     * @return string
     */
    private function cnbSignature($params): string
    {
        $params = $this->cnbParams($params);
        $json = $this->encodeParams($params);
        return $this->signatureFactory->create($json);
    }

    public function getDecodedData($data)
    {
        return json_decode(base64_decode($data), true, 1024);
    }

    /**
     * @param OrderInterface $order
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function checkOrderIsLiqPayPayment(OrderInterface $order): bool
    {
        $method = $order->getPayment()->getMethod();
        $methodCode = $this->paymentHelper->getMethodInstance($method)->getCode();
        return $methodCode == PaymentMethodCodeInterface::CODE;
    }

    /**
     * @param $data
     * @param $receivedPublicKey
     * @param $receivedSignature
     * @return bool
     */
    public function securityOrderCheck($data, $receivedPublicKey, $receivedSignature): bool
    {
        $isSecurityCheck = true;
        if ($isSecurityCheck) {
            $publicKey = $this->config->getPublicKey();
            if ($publicKey !== $receivedPublicKey) {
                return false;
            }

            $generatedSignature = $this->signatureFactory->create($data);

            return $receivedSignature === $generatedSignature;
        } else {
            return true;
        }
    }
}
