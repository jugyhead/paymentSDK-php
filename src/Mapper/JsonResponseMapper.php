<?php
/**
 * Shop System SDK - Terms of Use
 *
 * The SDK offered are provided free of charge by Wirecard AG and are explicitly not part
 * of the Wirecard AG range of products and services.
 *
 * They have been tested and approved for full functionality in the standard configuration
 * (status on delivery) of the corresponding shop system. They are under General Public
 * License Version 3 (GPLv3) and can be used, developed and passed on to third parties under
 * the same terms.
 *
 * However, Wirecard AG does not provide any guarantee or accept any liability for any errors
 * occurring when used in an enhanced, customized shop system configuration.
 *
 * Operation in an enhanced, customized configuration is at your own risk and requires a
 * comprehensive test phase by the user of the plugin.
 *
 * Customers use the SDK at their own risk. Wirecard AG does not guarantee their full
 * functionality neither does Wirecard AG assume liability for any disadvantages related to
 * the use of the SDK. Additionally, Wirecard AG does not guarantee the full functionality
 * for customized shop systems or installed SDK of other vendors of plugins within the same
 * shop system.
 *
 * Customers are responsible for testing the SDK's functionality before starting productive
 * operation.
 *
 * By installing the SDK into the shop system the customer agrees to these terms of use.
 * Please do not use the SDK if you do not agree to these terms of use!
 */

namespace Wirecard\PaymentSdk\Mapper;

use Wirecard\PaymentSdk\Response\FailureResponse;
use Wirecard\PaymentSdk\Response\InteractionResponse;
use Wirecard\PaymentSdk\Response\SuccessResponse;
use Wirecard\PaymentSdk\Transaction\Transaction;
use Wirecard\PaymentSdk\Exception\MalformedResponseException;
use Wirecard\PaymentSdk\Response\Response;

/**
 * Class JsonResponseMapper
 * @package Wirecard\PaymentSdk\Mapper
 */
class JsonResponseMapper extends ResponseMapper
{
    /**
     * Map the json Response from Wirecard's Payment Page to ResponseObjects
     *
     * @param string $jsonPayload
     * @param Transaction $transaction
     * @throws \InvalidArgumentException
     * @throws MalformedResponseException
     * @return Response
     * @since 3.5.0
     */
    public function map($jsonPayload, Transaction $transaction = null)
    {
        $payload = json_decode(parent::map($jsonPayload));
        switch ($this->checkResponse($payload)) {
            case "success":
                $response = new SuccessResponse($payload);
                break;
            case "interaction":
                $response = new InteractionResponse($payload, $payload->{'payment-redirect-url'});
                break;
            case "error":
            default:
                $response = new FailureResponse($payload);
                break;
        }

        return $response;
    }

    /**
     * @param $payload
     * @return null|string
     * @since 3.5.0
     */
    private function checkResponse($payload)
    {
        $response = null;
        if (key_exists('errors', $payload)
            || isset($payload->{'payment'}) && $payload->{'payment'}->{'transaction-state'} === 'failed') {
            $response = "error";
        } else {
            if (key_exists('payment-redirect-url', $payload)) {
                $response = "interaction";
            } else {
                if ($payload->{'payment'}->{'transaction-state'} === 'success') {
                    $response = "success";
                } else {
                    throw new MalformedResponseException('Malformed response caught! Expected error, success or interaction response.');
                }
            }
        }

        return $response;
    }

    /**
     * Response validation process
     *
     * @param string $responseBase64
     * @param string $signatureBase64
     * @param string $merchantSecretKey
     * @return bool
     * @since 3.5.0
     */
    protected function validateSignature($responseBase64, $signatureBase64 = null, $merchantSecretKey = null)
    {
        $signature = hash_hmac('sha256', $responseBase64, $merchantSecretKey, true);
        return hash_equals($signature, base64_decode($signatureBase64));
    }
}