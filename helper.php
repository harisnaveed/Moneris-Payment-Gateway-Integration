<?php

     // included
     require_once 'config.php';

    // function get detail
    function get_detail($client_detail_array) {

        global $destination_name;
        global $destination_address;
        global $destination_city;
        global $destination_state;
        global $destination_country;
        global $destination_postal_code;
        global $destination_phone;

        $date   = "";
        $month  = "";
        $year   = "";


        if ($client_detail_array['shipping_method'] == "Faster Shipping") {
            // after 1 day
            $futureDate = strtotime('+1 day');
            $date  = date('j', $futureDate); // 1 to 31
            $month = date('n', $futureDate); // 1 to 12
            $year  = date('Y', $futureDate);
        } else {
            // after 4 days
            $futureDate = strtotime('+4 days');
            $date  = date('j', $futureDate); // 1 to 31
            $month = date('n', $futureDate); // 1 to 12
            $year  = date('Y', $futureDate);
        }
        
        return array(
            "details" => array(
                "origin" => array(
                    "name" => $client_detail_array['full_name'],
                    "address" => array(
                        "address_line_1" => $client_detail_array['address'],
                        "city"          => $client_detail_array['city'],
                        "region"        => $client_detail_array['state'],
                        "country"       => "CA",
                        "postal_code"   => $client_detail_array['zip'] ?? "N2L3G1",
                        // "postal_code"   => "N2L3G1",
                    ),
                    "residential" => true,
                    "contact_name" => $client_detail_array['full_name'],
                    "phone_number" => array(
                        "number" => $client_detail_array['phone']
                    ),
                ),
                "destination" => array(
                    "name" => $destination_name,
                    "address" => array(
                        "address_line_1" => $destination_address,
                        "city"          => $destination_city,
                        "region"        => $destination_state,
                        "country"       => $destination_country,
                        "postal_code"   => $destination_postal_code
                    ),
                    "residential" => true,
                    "contact_name" => $destination_name,
                    "phone_number" => array(
                        "number" => $destination_phone
                    ),
                ),
                "expected_ship_date" => array(
                     "year"  => (int)$year,
                    "month" => (int)$month,
                    "day"   => (int)$date
                ),
                "packaging_type" => "package",
                "packaging_properties" => array(
                    "packages" => array(
                        array(
                            "measurements" => array(
                                "weight" => array(
                                    "unit"  => "lb",
                                    "value" => 5
                                ),
                                "cuboid" => array(
                                    "unit" => "in",
                                    "l"   => 14,
                                    "w"   => 12,
                                    "h"   => 6
                                )
                            ),
                            "description"   => "Test package",
                            "contents_type" => "merchandise",
                            "num_pieces"    => 1
                        )
                    )
                )
            )
        );
    }
     
     // function get response id
    function get_freightcom_rates($client_freightcom_detail_array) {

        global $freightcom_api_key;
        global $freightcom_base_url;

        $curl = curl_init();
        $payload = json_encode($client_freightcom_detail_array);

        curl_setopt_array($curl, array(
            CURLOPT_URL => $freightcom_base_url . "rate",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => array(
                "Authorization: " . $freightcom_api_key,
                "Content-Type: application/json",
                "Accept: application/json",
                "Content-Length: " . strlen($payload)
            ),
        ));
        curl_setopt($curl, CURLOPT_USERAGENT, "PostmanRuntime/7.32.3");
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);

        curl_close($curl);

        if ($error) {
            $errorLog = "cURL Error: " . $error;
            file_put_contents("whole-data-log.txt", $errorLog, FILE_APPEND);
        } else {
            $response = json_decode($response, true);
            $responseArray = [
                'request_id' => $response['request_id'],
                'status' => $httpCode
            ];
            // return $response['request_id'];
            return $responseArray;
        }

    }

    function get_rate_detail($request_id, $service_pickup) {
        global $freightcom_api_key;
        global $freightcom_base_url;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL            => $freightcom_base_url . "rate/" . $request_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => array(
                "Authorization: " . $freightcom_api_key,
                "Content-Type: application/json",
                "Accept: application/json",
            ),
        ));
        curl_setopt($curl, CURLOPT_USERAGENT,      "PostmanRuntime/7.32.3");
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

        $responseGetRateDetail = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error                 = curl_error($curl);
        curl_close($curl);

        if ($error) {
            $errorLog = "cURL Error: " . $error;
            file_put_contents("whole-data-log.txt", $errorLog, FILE_APPEND);
            return null;
        }

        // ✅ Decode JSON string into associative array
        $responseGetRateDetail = json_decode($responseGetRateDetail, true);

        // ✅ Handle invalid or empty JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorJson = "JSON Decode Error: " . json_last_error_msg();
            file_put_contents("whole-data-log.txt", $errorJson, FILE_APPEND);
            return null;
        }

        // ✅ Handle missing 'rates' key
        if (empty($responseGetRateDetail['rates'])) {
            $errorRateResponse = "No rates found in response.";
            file_put_contents("whole-data-log.txt", $errorRateResponse, FILE_APPEND);
            return null;
        }

        if ($service_pickup == "Other") {

            // Sab show karne ke baad minimum cost wala return karo
            $minService = null;
            $minCost    = PHP_INT_MAX;

            foreach ($responseGetRateDetail['rates'] as $rate) {
                $cost = (float) $rate['total']['value'];
                if ($cost < $minCost) {
                    $minCost    = $cost;
                    $minService = $rate['service_id'];
                }
            }
            $responseRateDetailArray = [
                'service_id' => $minService,
                'status'     => $httpCode,
            ];
            // return $minService;

            return $responseRateDetailArray;

        } else {

            // ✅ Sirf FedEx-Courier services filter karo
            if ($service_pickup == "Standard Shipping") {
                $allowedServiceIds = ['fedex-courier.ground'];
            } elseif ($service_pickup == "Faster Shipping") {
                $allowedServiceIds = ['fedex-courier.overnight'];
            } else {
                return null;
            }

            $filteredRates = array_values(array_filter(
                $responseGetRateDetail['rates'],
                function ($rate) use ($allowedServiceIds) {
                    return in_array($rate['service_id'], $allowedServiceIds);
                }
            ));

            if (empty($filteredRates)) {
                return null;
            }

            // ✅ Sirf FedEx-Courier wali services show hongi
            // return $filteredRates[0]['service_id'];

            $responseRateDetailArray = [
                'service_id' => $filteredRates[0]['service_id'],
                'status'     => $httpCode,
            ];
            // return $minService;

            return $responseRateDetailArray;
        }
    }

     // function payment id 
     function payment_method() {
        global $freightcom_api_key;
        global $freightcom_base_url;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $freightcom_base_url . "finance/payment-methods",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                "Authorization: " . $freightcom_api_key,
                "Content-Type: application/json",
                "Accept: application/json",
            ),
        ));
        curl_setopt($curl, CURLOPT_USERAGENT, "PostmanRuntime/7.32.3");
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

        $responsePaymentMethod = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);

        curl_close($curl);

        if ($error) {
            $errorLog = "cURL Error: " . $error;
            file_put_contents("whole-data-log.txt", $errorLog, FILE_APPEND);
        } else {
            $responsePaymentMethod = json_decode($responsePaymentMethod, true);
            $payment_method_id = $responsePaymentMethod[0]['id'];
            return $payment_method_id;
        }
     }

     // function shipment id
     function shipment_detail($service_id, $payment_method_id, $unique_id, $detail) {
        global $freightcom_api_key;
        global $freightcom_base_url;

        // $detail = json_encode($detail);


        $curl = curl_init();

        $payload = array(
            "unique_id" => $unique_id,
            "payment_method_id" => $payment_method_id,
            "service_id" => $service_id,
            "details" => $detail['details'] ,
        );

        $payload = json_encode($payload);

        curl_setopt_array($curl, array(
            CURLOPT_URL => $freightcom_base_url . "shipment",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => array(
                "Authorization: " . $freightcom_api_key,
                "Content-Type: application/json",
                "Accept: application/json",
            ),
        ));
        curl_setopt($curl, CURLOPT_USERAGENT, "PostmanRuntime/7.32.3");
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

        $responseShipment = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);

        curl_close($curl);

        if ($error) {
            $errorLog = "cURL Error: " . $error;
            file_put_contents("whole-data-log.txt", $errorLog, FILE_APPEND);
        } else {
            $responseShipment = json_decode($responseShipment, true);
            $id = trim($responseShipment['id']);
            $responseShipmentArray = [
                'shipment_id' => $id,
                'status'      => $httpCode,
            ];

            // return $id;
            return $responseShipmentArray;
        }
     }

     // function shipment detail
     function get_shipment_detail($id) {
        global $freightcom_api_key;
        global $freightcom_base_url;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $freightcom_base_url . "shipment/" . $id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                "Authorization: " . $freightcom_api_key,
                "Content-Type: application/json",
                "Accept: application/json",
            ),
        ));
        curl_setopt($curl, CURLOPT_USERAGENT, "PostmanRuntime/7.32.3");
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

        $responseShipmentDetail = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);

        curl_close($curl);

        if ($error) {
            $errorLog = "cURL Error: " . $error;
            file_put_contents("whole-data-log.txt", $errorLog, FILE_APPEND);
        } else {
            $responseShipmentDetail = json_decode($responseShipmentDetail, true);
            $responseDetailShipmentArray = [
                'detail' => $responseShipmentDetail,
                'status' => $httpCode,
            ];
            // return $responseShipmentDetail;

            return $responseDetailShipmentArray;
        }
    }


?>