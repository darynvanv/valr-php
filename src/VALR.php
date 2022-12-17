<?php
namespace DarynvanV\VALR_PHP;

use Exception;
use Spatie\FlareClient\Http\Exceptions\NotFound;

class VALR
{
    private $key;
    private $secret;
    
    /**
     * Supply a Key and Secret to access [AUTHENTICATED] routes.
     */
    function __construct($key = "", $secret = "")
    {
        $this->key = $key;
        $this->secret = $secret;
    }

    /**
     * [AUTHENTICATED] This will return all balances of the authenticated token
     */
    function get_balances()
    {
        $balances = $this->call_api('/v1/account/balances');
        
        return $balances;
    }


    /**
     * [PUBLIC] Return the price of a currency ($of) in another currency ($to) (See VALR for list of valid pairs.)
     * @param string $of The symbol of the currency as 1
     * @param string $to The symbol of the currency of the price to get
     * @example get_price('btc', 'zar') will return the cost of 1 BTC in ZAR
     */
    function get_price($of, $to)
    {
        $price = $this->call_api('/v1/public/' . $of . $to . '/marketsummary');
        return $price->lastTradedPrice;
    }

    function get_trades($of, $to, $limit = 100, $skip = 0, $before_id = null)
    {
        $trades = $this->call_api('/v1/public/' . $of . $to . '/trades?limit=' . $limit . '&skip=' . $skip . ($before_id ? '&beforeId=' . $before_id : ''));
        return $trades;
    }

    function get_orders($of, $to)
    {
        $orders = $this->call_api('/v1/public/' . $of . $to . '/orderbook');
        return $orders;
    }

    function get_currencies()
    {
        $currencies = $this->call_api('/v1/public/currencies');
        return $currencies;
    }
    function get_pairs()
    {
        $pairs = $this->call_api('/v1/public/pairs');
        return $pairs;
    }

    function sign($method, $path, $body = null, $timestamp = null)
    {
        $raw = $timestamp ?? (now()->timestamp * 1000);
        $raw .= strtoupper($method);
        $raw .= strtolower($path);
        if($body)
        {
          $raw .= json_encode($body);
        }

        $algo = 'sha512';
        
        $signed_payload = hash_hmac(
            $algo,
            $raw,
            $this->secret,
            false,
        );

        return  $signed_payload;
    }

    function call_api($path, $method = "GET", $body = null)
    {
      if($path[0] != '/')
        $path = '/' . $path;

      if(substr($path, -1) == '/')
        $path = substr($path, 0, strlen($path) - 1);

      $curl = curl_init();

      switch($method)
      {
        case "GET":
          curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.valr.com$path",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => array(
              'X-VALR-API-KEY: ' . $this->key,
              'X-VALR-SIGNATURE: ' . $this->sign($method,$path),
              'X-VALR-TIMESTAMP: ' . now()->timestamp * 1000
            ),
          ));
          break;
        case "POST":
          curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.valr.com$path",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => array(
              'X-VALR-API-KEY: ' . $this->key,
              'X-VALR-SIGNATURE: ' . $this->sign($method,$path,$body),
              'X-VALR-TIMESTAMP: ' . now()->timestamp * 1000
            ),
          ));
          break;
      }
      

      $response = curl_exec($curl);
      
      curl_close($curl);

      $data = json_decode($response);

      if(isset($data->code))
      {
        switch($data->code)
        {
          case -21:
            throw new Exception("VALR: Currency pair not found.");
            break;
          case -93:
            throw new Exception("VALR: Unauthorised.");
            break;
        }
      }

      if($data == null)
      {
        throw new Exception("VALR: Empty Response.");
      }

      dd($data);

      return json_decode($response);
    }
}