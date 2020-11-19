<?php
namespace Walleta\Client;

class HttpRequest
{
    /**
     * @param string $url Url
     * @param array $data Data
     * @return \Walleta\Client\Response
     */
    public function post($url, $data)
    {
        $jsonData = json_encode($data);

        $options = array(
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData),
            ),
        );

        $curl = curl_init($url);
        curl_setopt_array($curl, $options);

        $body = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        return new Response($httpCode, $body);
    }
}