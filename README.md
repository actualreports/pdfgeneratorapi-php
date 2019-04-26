# REST API wrapper for pdfgeneratorapi.com
REST API wrapper for [PDF Generator API](https://pdfgeneratorapi.com).

### Install
Require this package with composer using the following command:
```bash
composer require actualreports/pdfgeneratorapi-php
```

### Usage
```php
$client = new \ActualReports\PDFGeneratorAPI\Client($key, $secret);
$client->setBaseUrl('https://us1.pdfgeneratorapi.com/api/v3/');
$client->setWorkspace('unique@workspace.com');
```

```php
$data = [
    "DocNumber" => "12312", 
    "TotalAmt" => 1231.12
];
$content = $client->output(21650, $data);
```

```php
header('Content-type: '.$content->meta->{'content-type'});
header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0, max-age=1');
header('Pragma: public');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
header('Content-Disposition: inline; filename="'.$content->meta->name.'"');
die(base64_decode($content->response));
```
