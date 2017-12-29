<?php
/**
 * Created by tanel @14.11.17 11:23
 */

namespace ActualReports\PDFGeneratorAPI\Tests;

use ActualReports\PDFGeneratorAPI\Exception;

class Client extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \ActualReports\PDFGeneratorAPI\Client
     */
    protected $client;
    protected $key = '61e5f04ca1794253ed17e6bb986c1702';
    protected $secret = '68db1902ad1bb26d34b3f597488b9b28';
    protected $workspace = 'demo.example@actualreports.com';
    protected $host = 'https://staging.pdfgeneratorapi.com';
    protected $templateId = 21650;
    protected $timeout = 300;

    public function setUp()
    {
        parent::setUp();

        $this->client = new \ActualReports\PDFGeneratorAPI\Client($this->key, $this->secret, $this->workspace, $this->timeout);
        $this->client->setBaseUrl($this->host.'/api/v3');
    }

    public function testGetTemplates()
    {
        $result = $this->client->getAll();
        $this->assertEquals(83, count($result));
    }

    public function testGetTemplate()
    {
        $result = $this->client->get($this->templateId);
        $this->assertEquals('QuickBooks Invoice Blue', $result->name);
    }

    public function testOutputDataArray()
    {
        $data = $this->getData();
        $result = $this->client->output($this->templateId, (array) $data);
        $this->assertEquals('application/pdf', $result->meta->{'content-type'});
    }

    public function testOutputDataObject()
    {
        $result = $this->client->output($this->templateId, $this->getData());
        $this->assertEquals('application/pdf', $result->meta->{'content-type'});
    }

    public function testOutputDataUrl()
    {
        $result = $this->client->output($this->templateId, $this->host.'/assets/web/data/qbo_invoice.json');
        $this->assertEquals('application/pdf', $result->meta->{'content-type'});
    }

    public function testOutputDataString()
    {
        $result = $this->client->output($this->templateId, \GuzzleHttp\json_encode(['DocNumber' => 1123123123]));
        $this->assertEquals('application/pdf', $result->meta->{'content-type'});
    }

    public function testErrorResponse()
    {
        $this->setExpectedException(Exception::class);
        $this->client->output($this->templateId, [],'doc');
    }

    protected function getData()
    {
        $data = new \stdClass();
        $data->DocNumber = 12818812;
        return $data;
    }
}