<?php

class ControllerExtensionPaymentPaylabs extends Controller
{

    const SANDBOX_BASE_URL = 'https://sit-pay.paylabs.co.id';
    const PRODUCTION_BASE_URL = 'https://pay.paylabs.co.id';

    public function index()
    {
        $this->language->load('extension/payment/paylabs');
        $data['action'] = $this->url->link('extension/payment/paylabs/send');
        $data['button_confirm'] = $this->language->get('button_confirm');

        return $this->load->view('extension/payment/paylabs', $data);
    }

    public function send()
    {
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/paylabs');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $data = array();
        $data['paylabs_mid'] = $this->config->get('payment_paylabs_mid');
        $data['ap_amount'] = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
        $data['orderid'] = $this->session->data['order_id'];

        $successUrl = $this->url->link('checkout/success&');
        $data['ap_returnurl'] = $successUrl;
        $data['ap_notifyurl'] = $this->url->link('extension/payment/paylabs/payment_notification') . '&order_id=' . $this->session->data['order_id'];

        $data['ap_cancelurl'] = $this->url->link('checkout/checkout', '', 'SSL');

        $date = date("Y-m-d") . "T" . date("H:i:s.B") . "+07:00";
        $merchantId = $data['paylabs_mid'];
        $privateKey = $this->getPrivateKey();
        $requestId = (string) $order_info['order_id'] . "-" . $order_info['invoice_prefix'];
        $merchantTradeNo = $requestId;
        $fullName = $order_info['payment_firstname'] . " " . $order_info['payment_lastname'];

        $body = [
            'requestId' => $requestId,
            'merchantId' => $merchantId,
            'merchantTradeNo' => $merchantTradeNo,
            'amount' => number_format($data['ap_amount'], 2, '.', ''),
            'phoneNumber' => strlen($order_info['telephone']) > 3 ? $order_info['telephone'] : "000000",
            'productName' => 'Order #' . $data['orderid'],
            'redirectUrl' => $data['ap_returnurl'],
            'notifyUrl' => $data['ap_notifyurl'],
            'payer' => $fullName
        ];

        try {
            $path = "/payment/v2/h5/createLink";
            $sign = self::generateHash($privateKey, $body, $path, $date);
            if ($sign->status == false) {
                die("Error created signature, please contact administrator.");
            }
            $url = $this->getBaseUrl() . $path;

            $sendRequest = self::createTrascation($url, $body, $sign->sign, $date);

            if (isset($sendRequest->url)) {
                $message = 'Transaction via Paylabs <a href="' . $sendRequest->url . '" target="_NEW">' . $sendRequest->url . '</a>';
                $this->load->model('checkout/order');
                $this->model_checkout_order->addOrderHistory($data['orderid'], 1, $message);

                if (isset($this->session->data['order_id'])) {
                    $this->cart->clear();
                }

                header('location: ' . $sendRequest->url);
            } else {
                var_dump($sendRequest);
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        die;
    }

    public function payment_notification()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            die('This endpoint should not be opened using browser (HTTP GET).');
            exit();
        }
        $this->load->model('checkout/order');

        // Menggunakan getallheaders() (jika tersedia)
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            // Jika getallheaders() tidak tersedia, gunakan apache_request_headers()
            $headers = apache_request_headers();
        }

        $jsonData = file_get_contents("php://input");

        if (!$jsonData or !$headers) {
            die();
        }

        $sign = isset($headers['x-signature']) ? $headers['x-signature'] : '';
        $timestamp = isset($headers['x-timestamp']) ? $headers['x-timestamp'] : '';

        $data = json_decode($jsonData, true);

        $status = $data['status'];
        $errCode = $data['errCode'];
        $merchantTradeNo = $data['merchantTradeNo'];
        $split = explode("-", $merchantTradeNo);
        $orderId = isset($split[0]) ? $split[0] : '';

        $publicKey = $this->getPublicKey();
        $validate = self::validateTransaction($publicKey, $sign, $jsonData, $timestamp);

        if ($validate == true && $status == '02' && $errCode == "0") {
            $newStatus = $this->config->get('payment_paylabs_order_status_id');
            $message = 'Payment Success - Paylabs ID ' . $data['platformTradeNo'];
            $this->model_checkout_order->addOrderHistory($orderId, $newStatus, $message);

            $privateKey = $this->getPrivateKey();
            $date = date("Y-m-d") . "T" . date("H:i:s.B") . "+07:00";
            $requestId = $data['merchantTradeNo'] . "-" . $data['successTime'];

            $response = array(
                "merchantId" => $data['merchantId'],
                "requestId" => $requestId,
                "errCode" => "0"
            );

            $signature = self::generateHash($privateKey, $response, "/index.php", $date);
            if ($signature->status == false) return false;

            // Set HTTP response headers
            header("Content-Type: application/json;charset=utf-8");
            header("X-TIMESTAMP: " . $date);
            header("X-SIGNATURE: " . $signature->sign);
            header("X-PARTNER-ID: " . $data['merchantId']);
            header("X-REQUEST-ID: " . $requestId);

            // Encode the response as JSON and output it
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            die();
        }

        var_dump($headers);
        var_dump($jsonData);
    }

    public function getBaseUrl()
    {
        $sandboxMode = $this->config->get('payment_paylabs_mode');
        return $sandboxMode ? self::SANDBOX_BASE_URL : self::PRODUCTION_BASE_URL;
    }

    public function getPublicKey()
    {
        return $this->config->get('payment_paylabs_mode') ?
            $this->config->get('payment_paylabs_pub_key_sandbox') : $this->config->get('payment_paylabs_pub_key');
    }

    public function getPrivateKey()
    {
        return $this->config->get('payment_paylabs_mode') ?
            $this->config->get('payment_paylabs_priv_key_sandbox') : $this->config->get('payment_paylabs_priv_key');
    }

    public static function validateTransaction($publicKey, $signature, $dataToSign, $date)
    {
        $binary_signature = base64_decode($signature);
        $dataToSign = json_encode(json_decode($dataToSign), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $shaJson  = strtolower(hash('sha256', $dataToSign));
        $signatureAfter = "POST:/index.php:" . $shaJson . ":" . $date;
        $publicKey = openssl_pkey_get_public($publicKey);

        if ($publicKey === false) {
            die("Error loading public key");
        }

        $algo =  OPENSSL_ALGO_SHA256;
        $verificationResult = openssl_verify($signatureAfter, $binary_signature, $publicKey, $algo);

        if ($verificationResult === 1) {
            return true;
        } elseif ($verificationResult === 0) {
            return false;
        } else {
            die("Error while verifying the signature.");
        }
    }

    public static function generateHash($privateKey, $body, $path, $date)
    {
        if (openssl_pkey_get_private($privateKey) === false) {
            return (object) ['status' => false, 'desc' => 'Private key not valid.'];
        }

        $jsonBody = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $shaJson  = strtolower(hash('sha256', $jsonBody));
        $signatureBefore = "POST:" . $path . ":" . $shaJson . ":" . $date;
        // 	var_dump($signatureBefore);
        $binary_signature = "";

        $algo = OPENSSL_ALGO_SHA256;
        openssl_sign($signatureBefore, $binary_signature, $privateKey, $algo);
        $signature = base64_encode($binary_signature);

        return (object) ['status' => true, 'sign' => $signature];
    }

    public static function createTrascation($url, $body, $sign, $date)
    {

        $headers = array(
            'X-TIMESTAMP:' . $date,
            'X-SIGNATURE:' . $sign,
            'X-PARTNER-ID:' . $body['merchantId'],
            'X-REQUEST-ID:' . $body['requestId'],
            'Content-Type:application/json;charset=utf-8'
        );

        $response = self::remoteCall($url, $headers, $body);

        return $response;
    }

    public static function remoteCall($url, $headers, $body)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => $headers,
        ));

        $result = curl_exec($curl);
        curl_close($curl);

        if ($result === FALSE) {
            throw new Exception('CURL Error: ' . curl_error($curl), curl_errno($curl));
        } else {
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $response = json_decode($result);
            if ($httpcode != 200) {
                $message = 'Paylabs Error (' . $result . '): ';
                throw new Exception($message, $httpcode);
            } else {
                return $response;
            }
        }
    }
}
