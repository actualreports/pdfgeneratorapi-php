<?php
/**
 * Created by tanel @14.11.17 11:13
 */

namespace PDFGeneratorAPI;

use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class Client
{
    const FORMAT_PDF = 'pdf';
    const FORMAT_HTML = 'html';

    const REQUEST_POST = 'POST';
    const REQUEST_GET = 'GET';

    const ACCESS_PRIVATE = 'private';
    const ACCESS_ORGANIZATION = 'organization';

    /**
     * @var string
     */
    private $token;
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
    protected $baseUrl = 'https://pdfgeneratorapi.com/api/v3/';

    /**
     * @var int
     */
    protected $timeout = 120;

    /**
     * @var \GuzzleHttp\Client
     */
    protected $httpClient;

    /**
     * Client constructor.
     *
     * @param $token
     * @param $secret
     * @param string $workspace
     */
    public function __construct($token, $secret, $workspace = null)
    {
        $this->token = $token;
        $this->secret = $secret;
        $this->workspace = $workspace;
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
     * @param array|string $data
     *
     * @return array|mixed
     */
    protected static function data($data)
    {
        if (is_string($data))
        {
            try
            {
                $data = \GuzzleHttp\json_decode($data);
            }
            catch (\InvalidArgumentException $e)
            {

            }
        }
        else
        {
            $data = (array) $data;
        }

        return $data;
    }

    /**
     * @param string $resource
     *
     * @return string
     * @throws \Exception
     */
    protected function createSignature($resource)
    {
        if (!$this->token)
        {
            throw new Exception('Missing api token');
        }
        if (!$this->secret)
        {
            throw new Exception('Missing api secret');
        }
        if (!$this->workspace)
        {
            throw new Exception('Missing workspace id');
        }

        $data = $this->token.$this->workspace.$resource;

        return hash_hmac('sha256', $data, $this->secret);
    }

    /**
     * @return \GuzzleHttp\Client
     */
    protected function getHttpClient()
    {
        if (!$this->httpClient){
            $this->httpClient = new \GuzzleHttp\Client([
                'base_uri' => $this->baseUrl,
                'timeout' => $this->timeout
            ]);
        }

        return $this->httpClient;
    }

    /**
     * @param string $resource
     *
     * @return array
     */
    protected function getRequestHeaders($resource)
    {
        return [
            'Token' => $this->token,
            'Workspace' => $this->workspace,
            'Signature' => $this->createSignature($resource),
            'Content-Type' => 'application/json'
        ];
    }

    /**
     * @param string $method
     * @param string $resource
     * @param array $params
     * @param array $headers
     *
     * @return \stdClass
     * @throws \PDFGeneratorAPI\Exception
     */
    public function request($method = self::REQUEST_POST, $resource, array $params = [], array $headers = [])
    {
        $signature = $this->createSignature($resource);
        $method = strtoupper($method);

        $options = [];

        $params['data'] = isset($params['data']) ? self::data($params['data']) : null;

        if ($method === self::REQUEST_POST)
        {
            if ($params['data'] && is_array($params['data']))
            {
                $options['body'] = \GuzzleHttp\json_encode($params['data']);
                unset($params['data']);
            }

            $options['query'] = $params;
            $options['headers'] = array_merge($headers, [
                'Token' => $this->token,
                'Workspace' => $this->workspace,
                'Signature' => $signature,
                'Content-Type' => 'application/json; charset=utf-8'
            ]);
        }
        else if ($method === self::REQUEST_GET)
        {
            $options['query'] = array_merge($params, [
                'token' => $this->token,
                'workspace' => $this->workspace,
                'signature' => $signature,
            ]);
        }
        return $this->handleRequest($method, $resource, $options);
    }

    /**
     * @param $method
     * @param $resource
     * @param $options
     *
     * @return \stdClass
     */
    protected function handleRequest($method, $resource, $options)
    {
        try
        {
            return $this->handleResponse($this->getHttpClient()->request($method, $resource, $options));
        }
        catch (RequestException $e)
        {
            $this->handleException($e);
        }
    }

    /**
     * @param \GuzzleHttp\Exception\RequestException $e
     *
     * @throws \PDFGeneratorAPI\Exception
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
     * @throws \PDFGeneratorAPI\Exception
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
     * Returns list templates available in active workspace and returns template info
     *
     * @param array $access
     * @param array $tags
     *
     * @return \stdClass
     */
    public function get(array $access = [], array $tags = [])
    {
        return $this->request(self::REQUEST_GET, 'templates', [
            'access' => implode(',', $access),
            'tags' => implode(',', $tags)
        ]);
    }

    /**
     * Creates blank template into active workspace and returns template info
     * @param string $name
     *
     * @return \stdClass
     */
    public function create($name)
    {
        return $this->request(self::REQUEST_POST, 'templates', [
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
    }

    /**
     * Creates copy of given template to active workspace
     * @param integer $template
     * @param string $newName
     *
     * @return \stdClass
     */
    public function copy($template, $newName = null)
    {
        return $this->request(self::REQUEST_POST, 'templates/'.$template.'/copy', [
            'name' => $newName
        ]);
    }

    /**
     * Returns document as base64 encoded string for given template and data
     *
     * @param integer $template
     * @param string|array|\stdClass $data
     * @param string $format
     *
     * @return \stdClass
     */
    public function output($template, $data, $format = self::FORMAT_PDF)
    {
        return $this->request(self::REQUEST_POST, 'templates/'.$template.'/output', [
            'data' => $data,
            'format' => $format,
        ]);
    }

    public function editor($template)
    {
        $resource = 'templates/'.$template.'/editor';
        $query = http_build_query(array_merge(array(
            'token' => $this->token,
            'workspace' => $this->workspace,
            'signature' => $this->createSignature($resource)
        ), $params));

        return preg_replace('/([a-zA-Z])[\/]+/', '$1/', implode('/', array($this->baseUrl, $resource))).'?'.$query;
    }
}