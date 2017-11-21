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
    protected $token = '61e5f04ca1794253ed17e6bb986c1702';
    protected $secret = '68db1902ad1bb26d34b3f597488b9b28';
    protected $workspace = 'demo.example@actualreports.com';
    protected $host = 'http://127.0.0.3';// 'https://staging.pdfgeneratorapi.com';
    protected $templateId = 21650;

    public function setUp()
    {
        parent::setUp();

        $this->client = new \ActualReports\PDFGeneratorAPI\Client($this->token, $this->secret, $this->workspace);
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

    public function testEditorDataUrl()
    {
        $url = $this->client->editor($this->templateId, $this->host.'/assets/web/data/qbo_invoice.json');
        $compareUrl = $this->host.'/api/v3/templates/21650/editor?token=61e5f04ca1794253ed17e6bb986c1702&workspace=demo.example%40actualreports.com&signature=f119b90f5a0a10b09f735be6f7b46d27c02b82b03b31097f577fc0fba7e617b2&data=http%3A%2F%2F127.0.0.3%2Fassets%2Fweb%2Fdata%2Fqbo_invoice.json';
        $this->assertEquals($compareUrl, $url);
    }

    public function testEditorDataArray()
    {
        $url = $this->client->editor($this->templateId, ['DocNumber' => 1123123123]);
        $compareUrl = $this->host.'/api/v3/templates/21650/editor?token=61e5f04ca1794253ed17e6bb986c1702&workspace=demo.example%40actualreports.com&signature=f119b90f5a0a10b09f735be6f7b46d27c02b82b03b31097f577fc0fba7e617b2&data=%7B%22DocNumber%22%3A1123123123%7D';
        $this->assertEquals($compareUrl, $url);
    }

    public function testErrorResponse()
    {
        $this->setExpectedException(Exception::class);
        $this->client->output($this->templateId, [],'doc');
    }
}