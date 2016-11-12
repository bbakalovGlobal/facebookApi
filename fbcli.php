<?php
/**
 * Post one message to Facebook by terminal
 */

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include_once __DIR__ . '/config.php';
include __DIR__ . "/libs/fb/vendor/facebook/php-sdk-v4/src/Facebook/autoload.php";

$fb = new Facebook\Facebook(array(
    'app_id' => FB_APP_ID,
    'app_secret' => FB_APP_SECRET,
    'default_graph_version' => 'v2.7',
    'cookie' => true
));

if (php_sapi_name() == "cli") {
    if (!empty($argv[0]) && !empty($argv[1])) {
        $request = $fb->request('POST', 'me/feed', array('message' => 'Test message' . rand()), $argv[1]);
        try {
            $response = $fb->getClient()->sendRequest($request);
            $response = $response->getDecodedBody();
            error_log("{$response['id']}\n", 3, FB_LOG_PATH);
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            // When Graph returns an error
            echo 'Graph returned an error: ' . $e->getMessage();
            exit;
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            // When validation fails or other local issues
            echo 'Facebook SDK returned an error: ' . $e->getMessage();
            exit;
        }
    } else {
        error_log(time() . "Asked without Access Token\n", 3, FB_LOG_PATH);
        echo "You should give us Access Token\n";
        die;
    }
}
