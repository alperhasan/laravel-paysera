<?php

namespace Artme\Paysera;


use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use WebToPay;
use Request;


class Paysera
{
    public static function getRequiredFields()
    {
        return [];
    }

    /**
     * Return available payment methods by country and payment group
     * Method parameters can be set via config
     *
     * @param string [Optional] $country
     * @param array [Optional] $payment_groups_names
     * @return array
     */
    public static function getPaymentMethods($paysera_site_config)
    {
        $payment_methods_info = WebToPay::getPaymentMethodList(intval($paysera_site_config->projectid), $paysera_site_config->currency);
        $country_code = $paysera_site_config->country;
        $payment_methods_info->setDefaultLanguage($paysera_site_config->language);

        $result = [];

        $country_payment_methods_info = $payment_methods_info->getCountry($country_code);
        $result['country_code'] = $country_payment_methods_info->getCode();
        $result['country_title'] = $country_payment_methods_info->getTitle();
        $payment_methods_groups_all = $country_payment_methods_info->getGroups();

        $payment_groups_names = explode(',', $paysera_site_config->payment_groups);

        foreach ($payment_groups_names as $payment_groups_name) {
            $payment_methods_groups[$payment_groups_name] = $payment_methods_groups_all[$payment_groups_name];
            $result['payment_groups'][$payment_groups_name]['title'] = $payment_methods_groups_all[$payment_groups_name]->getTitle($paysera_site_config->language);
            foreach ($payment_methods_groups_all[$payment_groups_name]->getPaymentMethods() as $key => $method) {
                $tmp = [];
                $tmp['title'] = $method->getTitle($paysera_site_config->language);
                $tmp['key'] = $key;
                $tmp['currency'] = $method->getBaseCurrency();
                $tmp['logo_url'] = $method->getLogoUrl();
                $tmp['object'] = $method;

                $result['payment_groups'][$payment_groups_name]['methods'][$key] = $tmp;
            }
        }
        return $result;
    }

    /**
     * Generates full request and redirects with parameters to Paysera
     * Parameter $options can override $order_id and $amount
     *
     * TODO: Handle exceptions. At the moment imagine you're doing everything perfectly
     *
     * @param integer $order_id
     * @param float $amount
     * @param array $options
     */
    public static function makePayment($order_id, $amount, $paysera_site_config, $options = [])
    {

        $payment_data = [
            'projectid' => $paysera_site_config->projectid,
            'sign_password' => $paysera_site_config->sign_password,
            'currency' => $paysera_site_config->currency,
            'country' => $paysera_site_config->country,
            'test' => $paysera_site_config->test_mode ? 1 : 0,
            'version' => '1.6',

            'orderid' => $order_id,
            'amount' => intval($amount * 100)
        ];

        $payment_data['callbackurl'] = self::getCallbackUrl($paysera_site_config);
        $payment_data['accepturl'] = self::getAcceptUrl($paysera_site_config, $order_id);
        $payment_data['cancelurl'] = self::getCancelUrl($paysera_site_config, $order_id);

        $payment_data = array_merge($payment_data, $options);

        $request = WebToPay::redirectToPayment($payment_data, true);
    }

    /**
     * Check if callback response is from Paysera and parse data to array
     *
     * @param Request $request
     * @return array
     */
    public static function verifyPayment($paysera_site_config)
    {

        $response = WebToPay::validateAndParseData(
            Request::all(),
            intval($paysera_site_config->projectid),
            $paysera_site_config->sign_password
        );

        return $response;
    }

/*
    public static function updateOrderStatus(Request $request, $order_namespace = null)
    {
        $request_data = self::verifyPayment($request);
        if (!is_null($order_namespace)) {
            $namespace = $order_namespace;
        } else {
            $namespace = config('paysera.order_model_namespace');
        }

        if (!is_null($namespace)) {
            $order = $namespace::findOrFail($request_data['orderid']);
            if (method_exists($order, 'setStatus')) {
                //todo: set proper status
                $order->setStatus($request_data['status']);
                return true;
            }
        }

        return false;
    }
*/
    public static function getCancelUrl($paysera_site_config, $order_id)
    {
        $parsed_url = parse_url(Request::root() . $paysera_site_config->cancel_path . '/' . $paysera_site_config->site_id);
        if (isset($parsed_url['query'])) {
            $query = parse_str($parsed_url['query']);
        } else {
            $query = [];
        }
        $query['order_id'] = Crypt::encrypt($order_id);
        $parsed_url['query'] = http_build_query($query);

        return self::unparseUrl($parsed_url);
    }

    public static function getAcceptUrl($paysera_site_config, $order_id)
    {
        $parsed_url = parse_url(Request::root() . $paysera_site_config->accept_path . '/' . $paysera_site_config->site_id);
        if (isset($parsed_url['query'])) {
            $query = parse_str($parsed_url['query']);
        } else {
            $query = [];
        }
        $query['order_id'] = Crypt::encrypt($order_id);
        $parsed_url['query'] = http_build_query($query);

        return self::unparseUrl($parsed_url);
    }

    public static function getCallbackUrl($paysera_site_config)
    {
        $parsed_url = parse_url(Request::root() . $paysera_site_config->callback_path . '/' . $paysera_site_config->site_id);
        if (isset($parsed_url['query'])) {
            $query = parse_str($parsed_url['query']);
        } else {
            $query = [];
        }

        $parsed_url['query'] = http_build_query($query);

        return self::unparseUrl($parsed_url);
    }


    private static function unparseUrl($parsed_url)
    {
        $scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $user = isset($parsed_url['user']) ? $parsed_url['user'] : '';
        $pass = isset($parsed_url['pass']) ? ':' . $parsed_url['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $query = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';

        return "$scheme$user$pass$host$port$path$query$fragment";
    }
}
