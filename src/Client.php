<?php
/**
 * Created by tanel @14.11.17 11:13
 */

namespace ActualReports\PDFGeneratorAPI;

use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class Client
{
    const FORMAT_PDF = 'pdf';
    const FORMAT_HTML = 'html';

    const REQUEST_POST = 'POST';
    const REQUEST_GET = 'GET';
    const REQUEST_DELETE = 'DELETE';

    const ACCESS_PRIVATE = 'private';
    const ACCESS_ORGANIZATION = 'organization';

    /**
     * @var string
     */
    private $key;
    /**
     * @var string
     */
    protected $secret;

    /**
     * @var $workspace
     */
    protected $workspace;

    /**
     * @var string
     */
    protected $baseUrl = 'https://us1.pdfgeneratorapi.com/api/v3/';

    /**
     * @var int
     */
    protected $timeout = 120;

    /**
     * @var \GuzzleHttp\Client
     */
    protected $httpClient;

    /**
     * @var bool
     */
    protected $useTimestamp = false;

    /**
     * Client constructor.
     *
     * @param string $key
     * @param string $secret
     * @param string $workspace
     * @param integer $timeout
     * @param boolean $useTimestamp
     */
    public function __construct($key, $secret, $workspace = null, $timeout = null, $useTimestamp = false)
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->workspace = $workspace;
        $this->useTimestamp = $useTimestamp;

        if ($timeout)
        {
            $this->timeout = $timeout;
        }
    }

    /**
     * Set unique workspace identifier
     *
     * @param string $workspace
     *
     * @return $this
     */
    public function setWorkspace($workspace)
    {
        $this->workspace = $workspace;
        return $this;
    }

    /**
     * @param bool $status
     */
    public function setUseTimestamp($status = true)
    {
        $this->useTimestamp = $status;
    }

    /**
     * Set request timeout
     *
     * @param integer $timeout
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
        $this->httpClient = null;
    }

    /**
     * Set api base url
     *
     * @param string $url
     */
    public function setBaseUrl($url)
    {
        $this->baseUrl = preg_replace('/\/$/', '', $url).'/';
        $this->httpClient = null;
    }

    /**
     * @param string $resource
     * @param integer $timestamp
     *
     * @return string
     * @throws Exception
     */
    protected function createSignature($resource, $timestamp)
    {
        if (!$this->key)
        {
            throw new Exception('Missing api key');
        }
        if (!$this->secret)
        {
            throw new Exception('Missing api secret');
        }
        if (!$this->workspace)
        {
            throw new Exception('Missing workspace id');
        }

        $data = [
          'key' => $this->key,
          'workspace' => $this->workspace,
          'resource' => $resource
        ];

        if ($this->useTimestamp)
        {
            $data['timestamp'] = $timestamp;
        }

        ksort($data);

        return hash_hmac('sha256', implode('', $data), $this->secret);
    }

    /**
     * @return \GuzzleHttp\Client
     */
    protected function getHttpClient()
    {
        if (!$this->httpClient)
        {
            $this->httpClient = new \GuzzleHttp\Client([
                'base_uri' => $this->baseUrl,
                'timeout' => $this->timeout
            ]);
        }

        return $this->httpClient;
    }

    /**
     * @param string $method
     * @param $resource
     * @param array $params
     * @param array $headers
     *
     * @return null|\stdClass
     * @throws \ActualReports\PDFGeneratorAPI\Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function request($method = self::REQUEST_POST, $resource, array $params = [], array $headers = [])
    {
        $timestamp = time();
        $signature = $this->createSignature($resource, $timestamp);
        $method = strtoupper($method);

        if ($this->useTimestamp)
        {
            $headers['X-Auth-Timestamp'] = $timestamp;
        }

        $options = [
            'headers' => array_merge($headers, [
                'X-Auth-Key' => $this->key,
                'X-Auth-Workspace' => $this->workspace,
                'X-Auth-Signature' => $signature,
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept' => 'application/json'
            ])
        ];

        /**
         * If POST and data is not an url then send data in body as json string
         */
        if ($method === self::REQUEST_POST && isset($params['data']) && !self::isUrl($params['data']))
        {
            if (is_string($params['data']))
            {
                $params['data'] = self::stringToArray($params['data']);
            }

            if ($params['data'])
            {
                $options[\GuzzleHttp\RequestOptions::JSON] = $params['data'];
                unset($params['data']);
            }
        }

        $options[\GuzzleHttp\RequestOptions::QUERY] = $params;
        return $this->handleRequest($method, $resource, $options);
    }

    /**
     * @param $method
     * @param $resource
     * @param $options
     *
     * @return null|\stdClass
     * @throws \ActualReports\PDFGeneratorAPI\Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function handleRequest($method, $resource, $options)
    {
        $response = null;
        try
        {
            $response = $this->handleResponse($this->getHttpClient()->request($method, $resource, $options));
        }
        catch (RequestException $e)
        {
            $this->handleException($e);
        }

        return $response;
    }

    /**
     * @param \GuzzleHttp\Exception\RequestException $e
     *
     * @throws \ActualReports\PDFGeneratorAPI\Exception
     */
    protected function handleException(RequestException $e)
    {
        $message = $e->getMessage();
        $code = $e->getCode();

        $response = $e->getResponse();
        if ($response)
        {
            $contents = null;
            try
            {
                $contents = \GuzzleHttp\json_decode($response->getBody()->getContents());
            }
            catch (\InvalidArgumentException $e)
            {
            }

            if ($contents && property_exists($contents, 'error'))
            {
                $message = $contents->error;
                $code = $contents->status;
            }
        }

        throw new Exception($message, $code, $e);
    }

    /**
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return \stdClass
     * @throws \ActualReports\PDFGeneratorAPI\Exception
     */
    protected function handleResponse(ResponseInterface $response)
    {
        $contents = $response->getBody()->getContents();
        try
        {
            return $contents ? \GuzzleHttp\json_decode($contents) : null;
        }
        catch (\InvalidArgumentException $e)
        {
            throw new Exception('Unable to decode PDF Generator API response', $e->getCode(), $e);
        }
    }

    /**
     * Returns list of templates available in active workspace
     *
     * @param array $access
     * @param array $tags
     *
     * @return array
     * @throws \ActualReports\PDFGeneratorAPI\Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getAll(array $access = [], array $tags = [])
    {
        $response = $this->request(self::REQUEST_GET, 'templates', [
            'access' => implode(',', $access),
            'tags' => implode(',', $tags)
        ]);

        return $response->response;
    }

    /**
     * Returns template configuration
     *
     * @param integer $template
     *
     * @return \stdClass
     * @throws \ActualReports\PDFGeneratorAPI\Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function get($template)
    {
        $response = $this->request(self::REQUEST_GET, 'templates/'.$template);
        return $response->response;
    }

    /**
     * Creates blank template into active workspace and returns template info
     * @param string $name
     *
     * @return \stdClass
     * @throws \ActualReports\PDFGeneratorAPI\Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function create($name)
    {
        $response = $this->request(self::REQUEST_POST, 'templates', [
            'data' =>[
                'name' => $name,
                'layout' => [
                    'format' => 'A4',
                    'unit' => 'cm',
                    'orientation' => 'portrait',
                    'rotation' => 0,
                    'height' => 29.7,
                    'width' => 21,
                    'margins' => ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0],
                    'repeatLayout' => null
                ],
                'pages' => [[
                    'width' => 21,
                    'height' => 29.7,
                    'border' => false,
                    'margins' => ['right' => 0, 'bottom' => 0],
                    'components' => []
                ]]
            ]
        ]);

        return $response->response;
    }

    /**
     * Creates copy of given template to active workspace
     *
     * @param integer $template
     * @param string $newName
     *
     * @return \stdClass
     * @throws \ActualReports\PDFGeneratorAPI\Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function copy($template, $newName = null)
    {
        $response = $this->request(self::REQUEST_POST, 'templates/'.$template.'/copy', [
            'name' => $newName
        ]);

        return $response->response;
    }

    /**
     * Returns document as base64 encoded string for given template and data
     *
     * @param integer $template
     * @param string|array|\stdClass $data
     * @param string $format
     * @param string $name
     * @param array $params
     *
     * @return \stdClass
     * @throws \ActualReports\PDFGeneratorAPI\Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function output($template, $data, $format = self::FORMAT_PDF, $name = null, array $params = [])
    {
        return $this->request(self::REQUEST_POST, 'templates/'.$template.'/output', array_merge([
            'data' => $data,
            'format' => $format,
            'name' => $name
        ], $params));
    }

    /**
     * Creates editor url
     * 
     * @param integer $template
     * @param array|\stdClass|string $data
     * @param array $params
     *
     * @return string
     * @throws \ActualReports\PDFGeneratorAPI\Exception
     */
    public function editor($template, $data = null, array $params = [])
    {
        $timestamp = time();
        $resource = 'templates/'.$template.'/editor';
        $params = array_merge([
            'key' => $this->key,
            'workspace' => $this->workspace,
            'signature' => $this->createSignature($resource, $timestamp)
        ], $params);

        if ($this->useTimestamp)
        {
            $params['timestamp'] = $timestamp;
        }

        if ($data)
        {
            $params['data'] = self::dataToString($data);
        }

        return $this->baseUrl.$resource.'?'.http_build_query($params);
    }

    /**
     * Delete template
     *
     * @param integer $template
     *
     * @return \stdClass
     * @throws \ActualReports\PDFGeneratorAPI\Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function delete($template)
    {
        return $this->request(self::REQUEST_DELETE, 'templates/'.$template);
    }

    /**
     * @param mixed $string
     *
     * @return boolean
     */
    protected static function isUrl($string)
    {
        return is_string($string) && filter_var($string, FILTER_VALIDATE_URL) ? true : false;
    }

    /**
     * @param string $string
     *
     * @return array|null
     */
    protected static function stringToArray($string)
    {
        $data = null;

        try
        {
            $data = \GuzzleHttp\json_decode($string, true);
        }
        catch (\InvalidArgumentException $e)
        {

        }

        return $data;
    }

    /**
     * Turns data into string,
     *
     * @param array|string|\stdClass $data
     *
     * @return string
     */
    protected static function dataToString($data)
    {
        if (!is_string($data))
        {
            try
            {
                $data = \GuzzleHttp\json_encode($data);
            }
            catch (\InvalidArgumentException $e)
            {

            }
        }

        return $data;
    }
}