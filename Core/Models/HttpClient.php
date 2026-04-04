<?php
namespace Core\Models;
class HttpClient{
    public static function get(string $url, array $headers = [], int $timeout = 10){
        return self::request('GET', $url, null, $headers, $timeout);
    }

    public static function post(string $url, array|string $data = [], array $headers = [], int $timeout = 10){
        return self::request('POST', $url, $data, $headers, $timeout);
    }

    public static function postJson(string $url, array $jsonData = [], array $headers = [], int $timeout = 10){
        $headers[] = "Content-Type: application/json";

        return self::request(
            'POST',
            $url,
            json_encode($jsonData, JSON_UNESCAPED_UNICODE),
            $headers,
            $timeout
        );
    }

    public static function request(
        string $method,
        string $url,
        array|string|null $data = null,
        array $headers = [],
        int $timeout = 10
    ) {
        $curl = curl_init();

        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_FOLLOWLOCATION => true, // xử lý redirect
        ];

        // thêm body
        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            if (is_array($data)) {
                // Mặc định POST form (x-www-form-urlencoded)
                $data = http_build_query($data);
                $headers[] = "Content-Type: application/x-www-form-urlencoded";
            }
            $options[CURLOPT_POSTFIELDS] = $data;
        }

        // thêm header
        if (!empty($headers)) {
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($curl, $options);

        // thực thi
        $response = curl_exec($curl);
        $error    = curl_error($curl);
        $info     = curl_getinfo($curl);

        curl_close($curl);

        return [
            "success"  => $error === "",
            "status"   => $info['http_code'] ?? 0,
            "body"     => $response,
            "error"    => $error,
            "curlInfo" => $info
        ];
    }
}
