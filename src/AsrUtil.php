<?php

class AsrUtil
{
    /**
     * @var SigningAlgorithm
     */
    private $algorithm;

    /**
     * @param SigningAlgorithm $algorithm
     */
    public function __construct(SigningAlgorithm $algorithm)
    {
        $this->algorithm = $algorithm;
    }

    public function signRequest($secretKey, $fullDate, $method, $url, $payload, $headers)
    {
        $region          = 'us-east-1';
        $service         = 'iam';
        $shortDate       = substr($fullDate, 0, 8);
        $signedHeaders   = array_keys($headers);
        $canonicalHash   = $this->generateCanonicalHash($method, $url, $payload, $headers, $signedHeaders);
        $credentialScope = $this->generateCredentialScope($shortDate, $region, $service);
        $stringToSign    = $this->generateStringToSign($fullDate, $credentialScope, $canonicalHash);
        $signingKey      = $this->generateSigningKey($shortDate, $region, $service, $secretKey);
        return $this->algorithm->hmac($stringToSign, $signingKey, false);
    }

    public function sign($stringToSign, $date, $region, $service, $secretKey)
    {
        $signingKey = $this->generateSigningKey($date, $region, $service, $secretKey);
        return $this->algorithm->hmac($stringToSign, $signingKey, false);
    }

    public function generateSigningKey($date, $region, $service, $secretKey)
    {
        $key = $secretKey;
        foreach (array($date, $region, $service, 'aws4_request') as $data) {
            $key = $this->algorithm->hmac($data, $key, true);
        }
        return $key;
    }

    public function generateStringToSign($date, $credentialScope, $canonicalHash)
    {
        return implode("\n", array($this->algorithm->getName(), $date, $credentialScope, $canonicalHash));
    }

    public function generateCanonicalHash($method, $url, $payload, array $headers, array $signedHeaders)
    {
        $urlParts = parse_url($url);

        $path = $urlParts['path'];
        $query = isset($urlParts['query']) ? $urlParts['query'] : '';

        $requestLines = array_merge(
            array(strtoupper($method), $path, $query),
            $this->convertHeaders($headers),
            array('', $this->convertSignedHeaders($signedHeaders), $this->algorithm->hash($payload))
        );

        return $this->algorithm->hash(implode("\n", $requestLines));
    }

    private function convertHeaders($headers)
    {
        $result = array();
        foreach ($headers as $key => $value) {
            $result []= strtolower($key) . ':' . $this->trimHeaderValue($value);
        }
        return $result;
    }

    /**
     * @param $value
     * @return string
     */
    private function trimHeaderValue($value)
    {
        return trim($value);
    }

    /**
     * @param array $signedHeaders
     * @return string
     */
    private function convertSignedHeaders(array $signedHeaders)
    {
        return implode(';', array_map('strtolower', $signedHeaders));
    }

    /**
     * @param $fullDate
     * @param $region
     * @param $service
     * @return string
     */
    private function generateCredentialScope($fullDate, $region, $service)
    {
        return $fullDate . '/' . $region . '/' . $service . '/' . 'aws4_request';
    }
}

class SigningAlgorithm
{
    const SHA_256 = 'sha256';

    /**
     * @var string
     */
    private $algorithm;

    /**
     * @param string $algorithm
     */
    public function __construct($algorithm)
    {
        $this->algorithm = $algorithm;
    }

    public function getName()
    {
        return 'AWS4-HMAC-' . strtoupper($this->algorithm);
    }

    public function hmac($data, $key, $raw = false)
    {
        return hash_hmac($this->algorithm, $data, $key, $raw);
    }

    public function hash($data, $raw = false)
    {
        return hash($this->algorithm, $data, $raw);
    }
}
