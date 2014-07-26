<?php

class AsrUtilTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var AsrUtil
     */
    private $util;

    /**
     * @var AsrSigningAlgorithm
     */
    private $algorithm;

    private $secretKey = 'AWS4wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY';
    private $accessKeyId = 'AKIDEXAMPLE';
    private $baseCredentials = array('us-east-1', 'iam', 'aws4_request');

    protected function setUp()
    {
        $this->util = new AsrUtil();
        $this->algorithm = new AsrSigningAlgorithm(AsrUtil::SHA256);
    }

    /**
     * @test
     */
    public function itShouldSignRequest()
    {
        $this->assertEquals($this->authorizationHeader(), $this->callSignRequest());
    }

    /**
     * @test
     */
    public function itShouldGenerateCanonicalHash()
    {
        $headers = new AsrHeaders($this->headers());
        $request = new AsrRequest('POST', $this->url(), $this->payload(), $headers);
        $result = $request->canonicalizeUsing($this->algorithm);
        $this->assertEquals('3511de7e95d28ecd39e9513b642aee07e54f4941150d8df8bf94b328ef7e55e2', $result);
    }

    /**
     * @test
     */
    public function itShouldCalculateSigningKey()
    {
        $credentials = new AsrCredentials('20120215TIRRELEVANT', $this->accessKeyId, $this->baseCredentials);
        $result = $credentials->generateSigningKeyUsing($this->algorithm, $this->secretKey);
        $this->assertEquals('f4780e2d9f65fa895f9c67b32ce1baf0b0d8a43505a000a1a9e090d414db404d', bin2hex($result));
    }

    /**
     * @return array
     */
    private function headers()
    {
        return array(
            'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8',
            'Host' => 'iam.amazonaws.com',
            'X-Amz-Date' => '20110909T233600Z',
        );
    }

    /**
     * @return string
     */
    private function payload()
    {
        return 'Action=ListUsers&Version=2010-05-08';
    }

    /**
     * @return string
     */
    private function url()
    {
        return 'http://iam.amazonaws.com/';
    }

    /**
     * @return array
     */
    private function callSignRequest()
    {
        return $this->util->signRequest(
            AsrUtil::SHA256,
            $this->secretKey,
            $this->accessKeyId,
            $this->baseCredentials,
            '20110909T233600Z',
            'POST',
            $this->url(),
            $this->payload(),
            $this->headers()
        );
    }

    /**
     * @return array
     */
    private function authorizationHeader()
    {
        return array(
            'Authorization' =>
                'AWS4-HMAC-SHA256 '.
                'Credential=AKIDEXAMPLE/20110909/us-east-1/iam/aws4_request, '.
                'SignedHeaders=content-type;host;x-amz-date, '.
                'Signature=ced6826de92d2bdeed8f846f0bf508e8559e98e4b0199114b84c54174deb456c',
            'X-Amz-Date'    => '20110909T233600Z',
        );
    }
}
