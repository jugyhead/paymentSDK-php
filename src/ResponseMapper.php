<?php
namespace Wirecard\PaymentSdk;

/**
 * Class ResponseMapper
 * @package Wirecard\PaymentSdk
 */
class ResponseMapper
{
    /**
     * map the xml Response from engine to ResponseObjects
     *
     * @param $xmlResponse
     * @return FailureResponse|InteractionResponse|SuccessResponse
     * @throws MalformedResponseException
     */
    public function map($xmlResponse)
    {
        $decodedResponse = base64_decode($xmlResponse);
        $xmlResponse = (base64_encode($decodedResponse) === $xmlResponse) ? $decodedResponse : $xmlResponse;
        //we need to use internal_errors, because we don't want to throw errors on invalid xml responses
        $oldErrorHandling = libxml_use_internal_errors(true);
        $response = simplexml_load_string($xmlResponse);
        //reset to old value after string is loaded
        libxml_use_internal_errors($oldErrorHandling);
        if (!$response instanceof \SimpleXMLElement) {
            throw new MalformedResponseException('Response is not a valid xml string.');
        }

        //we have to string cast all fields, otherwise the contain SimpleXMLElements

        if (isset($response->{'transaction-state'})) {
            $state = (string)$response->{'transaction-state'};
        } else {
            throw new MalformedResponseException('Missing transaction state in response.');
        }

        $statusCollection = $this->getStatusCollection($response);
        if ($state !== 'success') {
            return new FailureResponse($xmlResponse, $statusCollection);
        }

        $transactionId = $this->getTransactionId($response);

        $paymentMethod = $this->getPaymentMethod($response);
        $redirectUrl = $this->getRedirectUrl($paymentMethod);
        if ($redirectUrl !== null) {
            return new InteractionResponse($xmlResponse, $statusCollection, $transactionId, $redirectUrl);
        } else {
            $providerTransactionId = $this->retrieveProviderTransactionId($response);
            return new SuccessResponse(
                $xmlResponse,
                $statusCollection,
                $transactionId,
                $providerTransactionId
            );
        }
    }

    /**
     * get the collection of status returned by elastic engine
     * @param \SimpleXMLElement $payment
     * @return StatusCollection
     * @throws MalformedResponseException
     */
    private function getStatusCollection($payment)
    {
        $collection = new StatusCollection();

        /**
         * @var $statuses \SimpleXMLElement
         */
        $statuses = $payment->statuses;
        if (count($statuses->status) > 0) {
            foreach ($statuses->status as $statusNode) {
                /**
                 * @var $statusNode \SimpleXMLElement
                 */
                $attributes = $statusNode->attributes();

                if ((string)$attributes['code'] !== '') {
                    $code = (string)$attributes['code'];
                } else {
                    throw new MalformedResponseException('Missing status code in response.');
                }
                if ((string)$attributes['description'] !== '') {
                    $description = (string)$attributes['description'];
                } else {
                    throw new MalformedResponseException('Missing status description in response.');
                }
                if ((string)$attributes['severity'] !== '') {
                    $severity = (string)$attributes['severity'];
                } else {
                    throw new MalformedResponseException('Missing status severity in response.');
                }
                $status = new Status($code, $description, $severity);
                $collection->add($status);
            }
        } else {
            throw new MalformedResponseException('Statuses is empty in response.');
        }

        return $collection;
    }

    /**
     * @param \SimpleXMLElement $response
     * @return string
     * @throws MalformedResponseException
     */
    private function getTransactionId(\SimpleXMLElement $response)
    {
        if (isset($response->{'transaction-id'})) {
            return (string)$response->{'transaction-id'};
        } else {
            throw new MalformedResponseException('Missing transaction-id in response');
        }
    }

    /**
     * @param \SimpleXMLElement $response
     * @return mixed
     * @throws MalformedResponseException
     */
    private function getPaymentMethod(\SimpleXMLElement $response)
    {
        if (isset($response->{'payment-methods'})) {
            $paymentMethods = $response->{'payment-methods'};
        } else {
            throw new MalformedResponseException('Missing payment methods in response');
        }

        if (isset($paymentMethods->{'payment-method'})) {
            $paymentMethod = $paymentMethods->{'payment-method'};
        } else {
            throw new MalformedResponseException('Payment methods is empty in response.');
        }

        if (count($paymentMethod) === 1) {
            return $paymentMethod[0];
        } else {
            throw new MalformedResponseException('More payment methods in response.');
        }
    }

    /**
     * @param \SimpleXMLElement $paymentMethod
     * @return string|null
     */
    private function getRedirectUrl(\SimpleXMLElement $paymentMethod)
    {
        if (isset($paymentMethod['url'])) {
            return (string)$paymentMethod['url'];
        } else {
            return null;
        }
    }

    /**
     * @param $xmlResponse
     * @return string
     * @throws MalformedResponseException
     */
    private function retrieveProviderTransactionId($xmlResponse)
    {
        $result = null;
        foreach ($xmlResponse->{'statuses'}->{'status'} as $status) {
            if ($result === null) {
                $result = $status['provider-transaction-id'];
            }

            if (strcmp($result, $status['provider-transaction-id']) !== 0) {
                throw new MalformedResponseException('More different provider transaction ID-s in response.');
            }
        }

        if ($result === null) {
            throw new MalformedResponseException('No provider transaction ID in response.');
        }

        return (string)$result;
    }
}
