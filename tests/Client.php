<?php
/**
 * Created by tanel @14.11.17 11:23
 */

namespace PDFGeneratorAPI\Tests;

use PDFGeneratorAPI\Exception;

class Client extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PDFGeneratorAPI\Client
     */
    protected $client;
    protected $token = '61e5f04ca1794253ed17e6bb986c1702';
    protected $secret = '68db1902ad1bb26d34b3f597488b9b28';
    protected $workspace = 'demo.example@actualreports.com';
    protected $host = 'http://127.0.0.3';
    protected $templateId = 21650;

    public function setUp()
    {
        parent::setUp();

        $this->client = new \PDFGeneratorAPI\Client($this->token, $this->secret, $this->workspace);
        $this->client->setBaseUrl($this->host.'/api/v3');
    }

    public function testGetTemplates()
    {
        $result = $this->client->get();
        $this->assertEquals(81, count($result->response));
    }

    public function testOutputDataArray()
    {
        $result = $this->client->output($this->templateId, ['DocNumber' => 1123123123]);
        $this->assertEquals('application/pdf', $result->meta->{'content-type'});
    }

    public function testOutputDataObject()
    {
        $data = new \stdClass();
        $data->DocNumber = 12818812;
        $result = $this->client->output($this->templateId, $data);
        $this->assertEquals('application/pdf', $result->meta->{'content-type'});
    }

    public function testOutputDataUrl()
    {
        $result = $this->client->output($this->templateId, $this->host.'/assets/web/data/qbo_invoice.json');
        $this->assertEquals('application/pdf', $result->meta->{'content-type'});
    }

    public function testErrorResponse()
    {
        $this->setExpectedException(Exception::class);
        $response = $this->client->output($this->templateId, [],'doc');
    }
}