<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include_once __DIR__ . '/config.php';
include __DIR__ . '/libs/fb/vendor/facebook/php-sdk-v4/src/Facebook/autoload.php';

if (!session_id()) {
    session_start();
}
//unset($_SESSION['facebook_access_token']);die;

$fb = new Facebook\Facebook(array(
    'app_id' => FB_APP_ID,
    'app_secret' => FB_APP_SECRET,
    'default_graph_version' => 'v2.7'
));

/**Ask user about permissions*/
$helper = $fb->getRedirectLoginHelper();
$permissions = ['email', 'publish_actions', 'publish_pages', 'manage_pages', 'user_posts']; // optional
$loginUrl = $helper->getLoginUrl('http://bdn.local/login-callback.php', $permissions);

if (isset($_SESSION['facebook_access_token'])) {
    echo "<p style='font-size:smaller;'>AccessToken: {$_SESSION['facebook_access_token']}<br></p>";
    $fb->setDefaultAccessToken($_SESSION['facebook_access_token']);
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'post':
                if (isset($_GET['qty']) && $_GET != 1) {
                    $newsList = getLentsNews($_GET['qty']);
                } else {
                    $newsList = getLentsNews();
                }
                $postIds = '';
                foreach ($newsList as $news) {
                    $fbNews = ['message' => (string)$news->description, 'link' => (string)$news->link];
                    $postIds = empty($postIds) ? $postIds . '' . postFb($fb, $fbNews)
                        : $postIds . ', ' . postFb($fb, $fbNews);
                }
                echo "Have been posted: " . $postIds;
                break;
            case 'deletePost':
                if (isset($_GET['postId'])) {
                    $deletedPostIds = deletePosts($fb, [$_GET['postId']]);
                    echo 'Was deleted: ' . implode(', ', $deletedPostIds);
                } else {
                    echo "You should add post ID<a href='index.php?action=deletePost&postId=postId'>DELETE POST</a>";
                }
                break;
            case 'deleteAll':
                $deletedPostIds = deletePosts($fb, getAllPostsId($fb));
                echo 'Were deleted: ' . implode(', ', $deletedPostIds);
                break;
            default:
                getAllPostsId($fb);
                echo "Choose option: <a href='index.php?action=post'>POST</a> | <a href='index.php?action=deletePost&postId=postId'>DELETE POST</a> | <a href='index.php?action=deleteAll'>DELETE ALL</a>";
                break;
        }
        echo '<p><a href="index.php">Go home</a></p>';
    } else {
        echo "Choose option: <a href='index.php?action=post'>POST</a> | <a href='index.php?action=deletePost&postId=postId'>DELETE POST</a> | <a href='index.php?action=deleteAll'>DELETE ALL</a>";
    }
} else {
    echo '<a href="' . $loginUrl . '">Log in with Facebook!</a>';
}

/**
 * Get all user's post IDs
 * @param \Facebook\Facebook $fbConnector
 * @return array
 */
function getAllPostsId(Facebook\Facebook $fbConnector)
{
    try {
        $response = $fbConnector->get('/me/posts');
        $dataArray = $response->getDecodedBody();
        $userPostsId = [];
        foreach ($dataArray['data'] as $item) {
            $userPostsId[] = $item['id'];
        }
        return $userPostsId;
    } catch (Facebook\Exceptions\FacebookResponseException $e) {
        // When Graph returns an error
        echo 'Graph returned an error: ' . $e->getMessage();
        exit;
    } catch (Facebook\Exceptions\FacebookSDKException $e) {
        // When validation fails or other local issues
        echo 'Facebook SDK returned an error: ' . $e->getMessage();
        exit;
    }
}

/**
 * Add post to user wall
 * @param \Facebook\Facebook $fbConnector
 * @param array $msg
 * @return \Facebook\GraphNodes\GraphNode|string
 */
function postFb(Facebook\Facebook $fbConnector, array $msg)
{
    if (!empty($msg)) {
        try {
            $response = $fbConnector->post('/me/feed', $msg, $fbConnector->getDefaultAccessToken());
            $responseBody = $response->getDecodedBody();
            error_log("{$responseBody['id']}\n", 3, FB_LOG_PATH);
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            echo 'Graph returned an error: ' . $e->getMessage();
            exit;
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            echo 'Facebook SDK returned an error: ' . $e->getMessage();
            exit;
        }
        return $responseBody['id'];
    } else {
        return '';
    }
}

/**
 * Delete user post by IDs
 * @param \Facebook\Facebook $fbConnector
 * @param array $postIds
 * @return string
 */
function deletePosts(Facebook\Facebook $fbConnector, array $postIds)
{
    $deletedPostIds = [];
    if (!empty($postIds)) {
        foreach ($postIds as $id) {
            try {
                $response = $fbConnector->delete($id);
                $responseBody = $response->getDecodedBody();
                if ($responseBody['success'] == 1) {
                    $deletedPostIds[] = $id;
                }
                error_log($responseBody['success'] . ':' . $id . "\n", 3, FB_LOG_PATH);
            } catch (Facebook\Exceptions\FacebookResponseException $e) {
                echo 'Graph returned an error: ' . $e->getMessage();
                exit;
            } catch (Facebook\Exceptions\FacebookSDKException $e) {
                echo 'Facebook SDK returned an error: ' . $e->getMessage();
                exit;
            }
        }
    }
    return $deletedPostIds;
}

/**
 * Get news from RSS
 * @param int $qty
 * @return array
 */
function getLentsNews($qty = 1)
{
    $xmlObj = simplexml_load_file(NEWS_URL, null, LIBXML_NOCDATA);
    $newObj = [];
    for ($i = 0; $i < $qty; $i++) {
        $newObj[] = $xmlObj->channel->item[$i];
    }
    return $newObj;
}