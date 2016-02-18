<?php
//core functions
include 'functions.php';
\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim();
$app->config('debug', true);

/*
 * Function for auth in api
 */
function authenticate(\Slim\Route $route) {
    global $db;
    $headers = apache_request_headers();
    $response = array();
    $app = \Slim\Slim::getInstance();
    if (isset($headers['Auth'])) {
        global $user_api_id, $user_id, $user;
        $api_key = $headers['Auth'];
        if ($api_key == 'adm') {
            $user_api_id = 1;
            $user_id = 1;
            return;
        }
        $is = $db->getRow("SELECT * FROM fw7uf_users WHERE api_key = ?s", $api_key);
        if (empty($is['api_key'])) {
            // api key is not present in users table
            $response["error"] = true;
            $response["message"] = "Access Denied. Invalid Api key";
            echoResponse(401, $response);
            $app->stop();
        } else {
            $user_api_id = $is['id'];
            $user_id = $is['id'];
            $user = $is;
        }
    } else {
        // api key is missing in header
        $response["error"] = true;
        $response["message"] = "Api key is misssing";
        echoResponse(400, $response);
        $app->stop();
    }
}

/**
 * @api {post} /regUser Register
 * @apiVersion 0.1.1
 * @apiName register
 * @apiGroup User
 *
 * @apiParam {string} [name] временно, возможен краш
 * @apiParam {string} email
 * @apiParam {string} [phone]
 * @apiParam {string} phoneId
 * @apiParam {string} [username] временно, возможен краш
 * @apiParam {string} password
 * @apiParam {string} [params]
 *
 */
$app->post('/regUser', function() use ($app) {
    global $db;
    $toBase['phoneId'] = $app->request->post('phoneId');
    $toBase['email'] = $app->request->post('email');
    $password_hash = PassHash::hash($app->request->post('password'));
    $toBase['password'] = $password_hash;
    //$toBase['registerDate'] = 'NOW()';
    $toBase['api_key'] = md5(uniqid(rand(), true));
    checkArr($toBase);
    $toBase['username'] = $app->request->post('username');
    $toBase['name'] = $app->request->post('name');
    $toBase['params'] = $app->request->post('params');
    $toBase['phone'] = $app->request->post('phone');
    validateEmail($toBase['email']);
    /*
      $noo_active = $db->getRow("SELECT * FROM fw7uf_users WHERE email=?s AND activation = '0'", $toBase['email']);
      if ($noo_active) {
      //$db->query("DELETE FROM fw7uf_users WHERE email=?s OR phone = ?s", $toBase['email'], $toBase['phone']);
      }
     */
    $is_user = $db->getRow("SELECT * FROM fw7uf_users WHERE email=?s", $toBase['email']);
    if (empty($is_user['id'])) {
        $is = $db->query("INSERT INTO fw7uf_users SET ?u,registerDate = NOW()", $toBase);
        if ($is) {
            $response['user_id'] = $db->insertId();
            $response['api_key'] = $toBase['api_key'];
            $response["error"] = false;
            $response["message"] = "You are successfully registered";
        } else {
            $response["error"] = true;
            $response["message"] = "Fail Request";
        }
    } else {
        $response["error"] = true;
        $response["message"] = "Email or Phone already use";
    }
    // echo json response
    echoResponse(201, $response);
});

/**
 * @api {post} /editUser/:id editUser
 * @apiVersion 0.1.1
 * @apiName editUser
 * @apiGroup User
 * @apiHeader {String} Auth Users unique api key
 *
 * @apiParam {string} [name]
 * @apiParam {string} [email]
 * @apiParam {string} [phone]
 * @apiParam {string} [phoneId]
 * @apiParam {string} [username]
 * @apiParam {string} [password]
 * @apiParam {string} [params]
 *
 */
$app->post('/editUser/:id', 'authenticate', function($user_id) use ($app) {
    global $db, $user_api_id, $today;
    //print_r($user_api_id.'='.$user_id);
    if ($user_api_id !== $user_id) {
        wrongUser();
    }
    $toBase['name'] = $app->request->post('name');
    $toBase['phone'] = $app->request->post('phone');
    $toBase['phoneId'] = $app->request->post('phoneId');
    $toBase['username'] = $app->request->post('username');
    $toBase['email'] = $app->request->post('email');
    $password = $app->request->post('password');
    if (!empty($toBase['email'])) {
        validateEmail($toBase['email']);
    }
    if (!empty($password)) {
        $password_hash = PassHash::hash($app->request->post('password'));
        $toBase['password'] = $password_hash;
    } else {
        $toBase['password'] = '';
    }
    //$toBase['api_key'] = md5(uniqid(rand(), true));
    //checkArr($toBase);
    $toBase['params'] = $app->request->post('params');
    $uploaddir = '../images/avatars/';
    $photo_name = md5($today . $user_api_id . 'I RUN AND GO EAT AND SLEEP NOW');
    $uploadfile = $uploaddir . basename($photo_name) . '.jpg';
    //print_r($_FILES);die;
    if (!empty($_FILES['file']['tmp_name'])) {
        if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadfile)) {
            $toBase['photo'] = '' . $photo_name . '.jpg';
        }
    }
    foreach ($toBase as $key => $value) {
        if (!empty($value) and $value != '') {
            $toBaseGo[$key] = $value;
        }
    }
    if (is_array($toBaseGo)) {
        $up = $db->query("UPDATE fw7uf_users SET ?u WHERE id = ?i", $toBaseGo, $user_id);
        if ($up) {
            $response = $db->getRow("SELECT * FROM fw7uf_users WHERE id = ?i", $user_id);
            $response["error"] = false;
            $response["message"] = "OK";
        } else {
            $response["error"] = true;
            $response["message"] = "Some think wrong";
        }
    } else {
        $response["error"] = true;
        $response["message"] = "Not found params";
    }

    // echo json response
    echoResponse(201, $response);
});
/**
 * @api {get} /getUserProfile/:id getUserProfile
 * @apiVersion 0.1.0
 * @apiName getUserProfile
 * @apiGroup User
 * @apiHeader {String} Auth Users unique api key
 *
 * @apiParam {Number} id
 *
 */
$app->get('/getUserProfile/:id', 'authenticate', function($user_id) use ($app) {
    global $db, $user_api_id;
    //print_r($user_api_id.'='.$user_id);
    if ($user_api_id !== $user_id) {
        wrongUser();
    }
    $userProfile = $db->getRow("SELECT name,username,email,phone,city_id,rating_month,rating_week,rating_year,photo FROM fw7uf_users WHERE id = ?i", $user_id);
    $userReviews = $db->getAll("SELECT review_id,product_id,user_id FROM fw7uf_jshopping_products_reviews WHERE user_id = ?i ORDER BY review_id DESC", $user_id);
    $userCity = $db->getRow("SELECT * FROM cities WHERE id = ?i", $userProfile['city_id']);
    if ($userCity) {
        $userProfile['city'] = $userCity;
    } else {
        $userProfile['city']['id'] = null;
        $userProfile['city']['name'] = null;
    }
    $product_count = 0;
    $service_count = 0;
    for ($i = 0; $i < count($userReviews); $i++) {
        $userReviews[$i]['category_id'] = $db->getRow("SELECT * FROM fw7uf_jshopping_products_to_categories WHERE product_id = ?i", $userReviews[$i]['product_id']);
        $userReviews[$i]['category_id'] = $userReviews[$i]['category_id']['category_id'];

        $userReviews[$i]['product_name'] = $db->getRow("SELECT `name_ru-RU` FROM fw7uf_jshopping_products WHERE product_id = ?i", $userReviews[$i]['product_id']);
        $userReviews[$i]['product_name'] = $userReviews[$i]['product_name']['name_ru-RU'];

        $userReviews[$i]['category_type'] = $db->getRow("SELECT category_type FROM fw7uf_jshopping_categories WHERE category_id = ?i", $userReviews[$i]['category_id']);
        $userReviews[$i]['category_type'] = $userReviews[$i]['category_type']['category_type'];
    }
    //print_r($userReviews);die;
    for ($i = 0; $i < count($userReviews); $i++) {
        if ($userReviews[$i]['category_type'] == 'products' AND $product_count < 3) {
            $product_count++;
            $userProfile['last_products'][] = $userReviews[$i]['product_name'];
        }
        if ($userReviews[$i]['category_type'] == 'services' AND $service_count < 3) {
            $service_count++;
            $userProfile['last_services'][] = $userReviews[$i]['product_name'];
        }
    }
    //print_r($userProfile);
    //die;
    if ($service_count == 0) {
        $userProfile['last_services'] = array();
    }
    if ($product_count == 0) {
        $userProfile['last_products'] = array();
    }

    if ($userProfile != NULL) {
        $response = $userProfile;
        $response["error"] = false;
        $response['message'] = null;
    } else {
        // unknown error occurred
        $response['error'] = true;
        $response['message'] = "An error occurred. Please try again";
    }
    echoResponse(200, $response);
});

/**
 * @api {post} /authUser/:type authUser
 * @apiVersion 0.1.0
 * @apiName authUser
 * @apiGroup User
 *
 * @apiParam {string="phone","email"} type
 * 
 * @apiParam {string} phone В зависимости от типа авторизации
 * @apiParam {string} email Отправляем нужный параметр
 * @apiParam {string} password
 * 
 * 
 *
 */
/**
 * @api {post} /authUser/:type authUser
 * @apiVersion 0.1.1
 * @apiName authUser
 * @apiGroup User
 *
 * @apiParam {string="phone","email","social"} type
 * 
 * @apiParam {string} phone В зависимости от типа авторизации
 * @apiParam {string} email Отправляем нужный параметр
 * @apiParam {string} password
 * 
 * @apiParam {string} hash Для входа с соц-сети md5($email.'jhoeOTdVmhL5')
 * 
 *
 */
$app->post('/authUser/:type', function($authType) use ($app) {
    // check for required params
    global $db;

    //verifyRequiredParams(array('email', 'password'));
    // reading post params
    if ($authType == 'phone') {
        $phone = $app->request()->post('phone');
        $password = $app->request()->post('password');
        $response = array();
        $password_hash = PassHash::hash($password);
        $is_user = $db->getRow("SELECT * FROM fw7uf_users WHERE phone = ?s and password = ?s", $phone, $password_hash);
    } elseif ($authType == 'email') {
        $phone = $app->request()->post('email');
        $password = $app->request()->post('password');
        $response = array();
        $password_hash = PassHash::hash($password);
        $is_user = $db->getRow("SELECT * FROM fw7uf_users WHERE email = ?s and password = ?s", $phone, $password_hash);
    } elseif ($authType == 'social') {
        $email = $_POST['email'];
        $hash = $_POST['hash'];
        if ($hash == md5($email . 'jhoeOTdVmhL5')) {
            $is_user = $db->getRow("SELECT * FROM fw7uf_users WHERE email = ?s", $email);
        }
    } else {
        $response["error"] = true;
        $response["message"] = "Auth Type?";
        echoResponse(200, $response);
        $app->stop();
    }

    //echo $user['email'];  
    // check for correct email and password
    if (!empty($is_user['id'])) {
        //get the user by email
        //
        //генерация нового токена
        //$newApi_key = md5(uniqid(rand(), true));
        //$db->query("UPDATE fw7uf_users SET api_key = ?s WHERE id = ?s",$newApi_key,$is_user['id']);
        $user = $db->getRow("SELECT * FROM fw7uf_users WHERE id = ?s", $is_user['id']);
        if ($user != NULL) {
            $response = $user;
            $response["error"] = false;
            $response['message'] = null;
        } else {
            // unknown error occurred
            $response['error'] = true;
            $response['message'] = "An error occurred. Please try again";
        }
    } else {
        // user credentials are wrong
        $response['error'] = true;
        $response['message'] = 'Login failed. Incorrect credentials';
    }

    echoResponse(200, $response);
});


/**
 * @api {post} /user/reqActivate/:type reqActivate
 * @apiVersion 0.1.0
 * @apiName reqActivate
 * @apiGroup User
 * @apiDescription Запрос на активацию(отослать смс или на почту)
 *
 * 
 * @apiParam {string="phone","email"} type
 * 
 * 
 *
 */
$app->post('/user/reqActivate/:type', 'authenticate', function($actType = false) use ($app) {
    global $user_id, $db;
    //phpinfo();die;
    //echo '1';
    if ($actType == 'phone') {
        //echo '2';
        $client = new SoapClient('http://turbosms.in.ua/api/wsdl.html');
        $auth = Array('login' => 'frameapp', 'password' => 'frameapp');
        $result = $client->Auth($auth);

        //$smsText = 'PRIVET';
        $smsText = rand(100000, 999999);
        $phone = $db->getRow("SELECT phone FROM fw7uf_users WHERE id =?i", $user_id);
        $phone = $phone['phone'];
        if (!empty($phone)) {
            $sms = Array('sender' => 'LikeDisLike', 'destination' => $phone, 'text' => $smsText);
            $result = $client->SendSMS($sms);
            $result = $result->SendSMSResult;
            $result = (array) $result;

            if ($result['ResultArray'][0] == 'Сообщения успешно отправлены') {
                $toBase = array('a_type' => 'phone', 'a_dest' => $phone, 'a_text' => $smsText);
                $db->query("DELETE FROM `api_activations` WHERE `a_dest` = ?s", $phone);
                $db->query("INSERT INTO api_activations SET ?u,created_at = NOW()", $toBase);

                $response["error"] = false;
                $response["message"] = "SMS send, status:OK";
                echoResponse(200, $response);
            } else {
                //htmlentities( (string) $result['ResultArray'][0], ENT_QUOTES, 'utf-8', FALSE);
                $result['ResultArray'][0] = mb_convert_encoding($result['ResultArray'][0], 'UTF-8', 'UTF-8');
                $response["error"] = true;
                $response["message"] = $result['ResultArray'][0];
                echoResponse(200, $response);
            }
        } else {
            $response["error"] = true;
            $response["message"] = 'Not found phone';
            echoResponse(200, $response);
        }
    } elseif ($actType == 'email') {
        $user = $db->getRow("SELECT * FROM fw7uf_users WHERE id =?i", $user_id);
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n"; // кодировка письма
        $headers .= "From: Quality app. <info@kach.pp.ua>\r\n"; // от кого письмо expasepr@gmail.com
        //$result = $mailSMTP->send($user['email'], 'Activate', '' . $_SERVER['HTTP_HOST'] . '/user/activateEmail/' . $user['api_key'] . '', $headers); // отправляем письмо
        //
        $result = mail($user['email'], 'Activate', 'Активируйте имейл перейдя по ссылке.<br>' . $_SERVER['HTTP_HOST'] . '/api/user/activateEmail/' . $user['api_key'] . '', $headers);
        //var_dump($result);
        if ($result) {
            $response["error"] = false;
            $response["message"] = "Email send, status:OK";
        } else {
            $response["error"] = true;
            $response["message"] = "Fail send email";
        }

        echoResponse(200, $response);
    } else {
        //echo '4';
        $response["error"] = true;
        $response["message"] = 'What you want? missed actType or incorrected';
        echoResponse(200, $response);
    }
    //echo '5';
    //echoResponse(200, $response);
});

/**
 * @api {post} /user/reqNewPassword reqNewPassword
 * @apiVersion 0.1.2
 * @apiName reqNewPassword
 * @apiGroup User
 * @apiDescription Запрос на новый пароль, api_key слать обязательно
 *
 * 
 * @apiParam email
 * 
 *
 */
$app->post('/user/reqNewPassword', function () use ($app) {
    global $db;
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n"; // кодировка письма
    $headers .= "From: Quality app. <info@kach.pp.ua>\r\n"; // от кого письмо expasepr@gmail.com
    //$result = $mailSMTP->send($user['email'], 'Activate', '' . $_SERVER['HTTP_HOST'] . '/user/activateEmail/' . $user['api_key'] . '', $headers); // отправляем письмо
    //
    $user = $db->getRow("SELECT * FROM fw7uf_users WHERE email = ?s", $_POST['email']);
    if ($user['email']) {
        $result = mail($user['email'], 'Activate', 'Получите новый пароль.<br>' . $_SERVER['HTTP_HOST'] . '/api/user/genNewPassword/' . $user['api_key'] . '', $headers);
    }
    //var_dump($result);
    if ($result) {
        $response["error"] = false;
        $response["message"] = "Email send, status:OK";
    } else {
        $response["error"] = true;
        $response["message"] = "Fail send email";
    }

    echoResponse(200, $response);
});

$app->get('/user/genNewPassword/:hash', function($hash) use ($app) {
    global $db;
    $api_key_from = $hash;
    $is = $db->getRow("SELECT id FROM fw7uf_users WHERE api_key = ?s", $api_key_from);

    if (!empty($is['id'])) {
        $pass = genPass();
        $newPass = PassHash::hash($pass);
        $newApi_key = md5(uniqid(rand(), true));
        ;
        $db->query("UPDATE fw7uf_users SET password = ?s,api_key = ?s WHERE id = ?i", $newPass, $newApi_key, $is['id']);
        echo 'Ваш новый пароль:' . $pass;
        die;
    } else {
        echo 'Ошибка.';
    }
});

$app->get('/user/activateEmail/:hash', function($hash) use ($app) {
    global $db;
    $api_key_from = $hash;
    $is = $db->getRow("SELECT id FROM fw7uf_users WHERE api_key = ?s", $api_key_from);
    if (!empty($is['id'])) {
        $db->query("UPDATE fw7uf_users SET activation_email = '1' WHERE id = ?i ", $is['id']);
        echo 'Спасибо! Ваша электронная почта подтверждена.';
        die;
    } else {
        echo 'Ошибка.';
    }
});

/**
 * @api {post} /user/activatePhone/ activateUserByPhone
 * @apiVersion 0.1.0
 * @apiName activateUserByPhone
 * @apiGroup User
 * @apiDescription активируем юзера с помощью телефона
 *
 * @apiParam {string} keys
 * @apiParam {string} phone
 * 
 * 
 */
$app->post('/user/activatePhone/', 'authenticate', function() use ($app) {
    global $db, $user_api_id;

    $smsText = $app->request->post('keys');
    $a_dest = $app->request->post('phone');
    $is = $db->getRow("SELECT * FROM api_activations WHERE a_dest = ?s and status = '0' ", $a_dest);
    if (!empty($is['id']) and $smsText == $is['a_text']) {
        $db->query("UPDATE api_activations SET status = '1' WHERE id = ?i ", $is['id']);
        $db->query("UPDATE fw7uf_users SET activation = '1' WHERE phone = ?s", $a_dest);
        $response["error"] = false;
        $response["message"] = "All right";
    } else {
        $response["error"] = true;
        $response["message"] = "Wrong key or phone";
    }
    echoResponse(200, $response);
});
/*
 * get all products.
 *  'authenticate',
 */
/**
 * @api {get} /getProduct/:id getProductById
 * @apiVersion 0.1.0
 * @apiName getProductById
 * @apiGroup Products
 *
 * @apiParam {Number} id
 * 
 * 
 */
$app->get('/getProduct/:id', function($product_id) use ($app) {
    global $db;
    if ($product_id >= 1) {
        $products = $db->getAll("SELECT * FROM fw7uf_jshopping_products WHERE product_id = ?i", $product_id);
    } else {
        $products = false;
    }

    if ($products) {
        $response = $products;
        $response["error"] = false;
        $response["message"] = "OK";
    } else {
        $response["error"] = true;
        $response["message"] = "Products not found";
    }
    echoResponse(200, $response);
});
/*
 * карточка товара
 */
/**
 * @api {get} /getProductInfo/:id(/:reviews_page(/:reviews_per_page)) getProductInfo
 * @apiVersion 0.1.0
 * @apiName getProductInfo
 * @apiGroup Products
 * @apiDescription Карточка товара
 * 
 * @apiParam {Number} id
 * @apiParam {Number} reviews_page
 * @apiParam {Number} reviews_per_page
 * 
 */
$app->get('/getProductInfo/:id(/:reviews_page(/:reviews_per_page))', function($product_id, $reviews_page = 1, $reviews_per_page = 4) use ($app) {
    global $db;
    if ($product_id >= 1) {
        //$product = $db->getRow("SELECT `name_ru-RU` as product_name,product_id,`description_ru-RU` as description,image FROM fw7uf_jshopping_products WHERE product_id = ?i", $product_id);
        $product = $db->getRow("SELECT `name_ru-RU` as product_name,fw7uf_jshopping_products.product_id,fw7uf_jshopping_products_to_categories.category_id,`description_ru-RU` as description,image FROM `fw7uf_jshopping_products`,`fw7uf_jshopping_products_to_categories` WHERE fw7uf_jshopping_products.product_id = fw7uf_jshopping_products_to_categories.product_id AND fw7uf_jshopping_products.product_id = ?i ", $product_id);
        if ($product) {
            $positive_count = $db->getAll("SELECT `review_id` FROM `fw7uf_jshopping_products_reviews` WHERE `mark` = '1' AND product_id = ?i AND publish = '1'", $product_id);
            $positive_count = count($positive_count);

            $negative_count = $db->getAll("SELECT `review_id` FROM `fw7uf_jshopping_products_reviews` WHERE `mark` = '0' AND product_id = ?i AND publish = '1'", $product_id);
            $negative_count = count($negative_count);
            $product['positive_count'] = $positive_count;
            $product['negative_count'] = $negative_count;
            $product['bread'] = getBread($product['category_id']);
            $per_page = $reviews_per_page;
            $cur_page = $reviews_page;
            $start = ($cur_page - 1) * $per_page;

            $product['reviews'] = $db->getAll("SELECT `mark`,`user_id`,`time`,`review` FROM `fw7uf_jshopping_products_reviews` WHERE `product_id` = ?i AND publish = '1' ORDER BY review_id DESC LIMIT $start,$per_page ", $product_id);
            $reviews_count = count($db->getAll("SELECT `user_id` FROM `fw7uf_jshopping_products_reviews` WHERE `product_id` = '?i' AND publish = '1'", $product_id));
            $num_pages = ceil($reviews_count / $per_page);
            $product['page'] = $cur_page;
            $product['num_pages'] = $num_pages;
            $product['review_count'] = $reviews_count;
            for ($i = 0; $i < count($product['reviews']); $i++) {
                $user = $db->getRow("SELECT name,username,photo FROM fw7uf_users WHERE id = ?i", $product['reviews'][$i]['user_id']);
                $product['reviews'][$i]['name'] = $user['name'];
                $product['reviews'][$i]['username'] = $user['username'];
                $product['reviews'][$i]['user_photo'] = $user['photo'];
            }
        }
    } else {
        $product = false;
    }

    if ($product) {
        $response = $product;
        $response["error"] = false;
        $response["message"] = "OK";
    } else {
        $response["error"] = true;
        $response["message"] = "Products not found";
    }
    echoResponse(200, $response);
});

/**
 * @api {get} /getProducts/ getProducts
 * @apiVersion 0.1.0
 * @apiName getProducts
 * @apiGroup Products
 * 
 * 
 * 
 */
$app->get('/getProducts/', function() use ($app) {
    global $db;

    $products = $db->getAll("SELECT * FROM fw7uf_jshopping_products");
    // получаю категории
    for ($i = 0; $i < count($products); $i++) {
        //$products[$i]['product_id']
        $products[$i]['category'] = $db->getRow("SELECT * FROM fw7uf_jshopping_products_to_categories WHERE product_id = ?i", $products[$i]['product_id']);
    }


    if ($products) {
        $response = $products;
        $response["error"] = false;
        $response["message"] = "OK";
    } else {
        $response["error"] = true;
        $response["message"] = "Products not found";
    }
    echoResponse(200, $response);
});

/**
 * @api {get} /getProducts/:sort getProductsWithSort
 * @apiVersion 0.1.0
 * @apiName getProductsWithSort
 * @apiGroup Products
 * 
 * @apiParam {string="asc","desc"} sort
 * 
 */
$app->get('/getProducts/:sort', 'authenticate', function($sort) use ($app) {
    global $db;
    if ($sort == 'asc') {
        $products = $db->getAll("SELECT * FROM fw7uf_jshopping_products ORDER BY 'name_ru-RU' ASC");
    } elseif ($sort == 'desc') {
        $products = $db->getAll("SELECT * FROM fw7uf_jshopping_products ORDER BY 'name_ru-RU' DESC");
    } else {
        $products = $db->getAll("SELECT * FROM fw7uf_jshopping_products ORDER BY 'name_ru-RU' DESC");
    }

    // получаю категории
    for ($i = 0; $i < count($products); $i++) {
        //$products[$i]['product_id']
        $products[$i]['category'] = $db->getRow("SELECT * FROM fw7uf_jshopping_products_to_categories WHERE product_id = ?i", $products[$i]['product_id']);
    }


    if ($products) {
        $response = $products;
        $response["error"] = false;
        $response["message"] = "OK";
    } else {
        $response["error"] = true;
        $response["message"] = "Products not found";
    }
    echoResponse(200, $response);
});

/**
 * @api {post} /getProducts/category/:id getProductsInCategory
 * @apiVersion 0.1.0
 * @apiName getProductsInCategory
 * @apiGroup Products
 * 
 * @apiParam {Number} id
 * 
 */
/**
 * @api {post} /getProducts/category/:id/:city_id getProductsInCategory
 * @apiVersion 0.2.0
 * @apiName getProductsInCategory
 * @apiGroup Products
 * 
 * @apiParam {Number} id
 * @apiParam {Number} [city_id]
 */
$app->post('/getProducts/category/:id(/:city_id)', function($category_id, $city_id = false) use ($app) {
    global $db;
    $products = array();
    //fw7uf_jshopping_categories.`button_text`,fw7uf_jshopping_categories.`button_add`,fw7uf_jshopping_categories.`button_select`
    //$products = $db->getAll("SELECT fw7uf_jshopping_products.product_id,fw7uf_jshopping_products.image as `product_image`,fw7uf_jshopping_products.`name_ru-RU` as `product_name` FROM `fw7uf_jshopping_products_to_categories`,`fw7uf_jshopping_products`,`fw7uf_jshopping_categories` WHERE fw7uf_jshopping_products.product_id = fw7uf_jshopping_products_to_categories.product_id  AND fw7uf_jshopping_products_to_categories.category_id = ?i", $category_id);

    if ($city_id) {
        $productsTemp = $db->getAll("SELECT fw7uf_jshopping_products.product_id,fw7uf_jshopping_products.image as `product_image`,fw7uf_jshopping_products.`name_ru-RU` as `product_name` FROM `fw7uf_jshopping_products_to_categories`,`fw7uf_jshopping_products` WHERE fw7uf_jshopping_products.product_id = fw7uf_jshopping_products_to_categories.product_id AND fw7uf_jshopping_products_to_categories.category_id = ?i", $category_id);
        for ($i = 0; $i < count($productsTemp); $i++) {

            $cities = $db->getAll("SELECT * FROM cities_bind WHERE param_type = '" . getCategoryTypeByProductId($productsTemp[$i]['product_id']) . "' AND param_id = ?i", $productsTemp[$i]['product_id']);
            $includeCity = false;
            if (is_array($cities)) {
                foreach ($cities as $city) {
                    if ($city['city_id'] == $city_id) {
                        $includeCity = true;
                    }
                    $productsTemp[$i]['cities'][] = $db->getRow("SELECT * FROM cities WHERE id = ?i", $city['city_id']);
                }
                //Если искомый город есть в списке
                if ($includeCity) {
                    $products[] = $productsTemp[$i];
                }
            }
        }
    } else {
        $products = $db->getAll("SELECT fw7uf_jshopping_products.product_id,fw7uf_jshopping_products.image as `product_image`,fw7uf_jshopping_products.`name_ru-RU` as `product_name` FROM `fw7uf_jshopping_products_to_categories`,`fw7uf_jshopping_products` WHERE fw7uf_jshopping_products.product_id = fw7uf_jshopping_products_to_categories.product_id AND fw7uf_jshopping_products_to_categories.category_id = ?i", $category_id);
    }


    for ($i = 0; $i < count($products); $i++) {
        //positive_counts
        $positive_count = $db->getAll("SELECT `review_id` FROM `fw7uf_jshopping_products_reviews` WHERE `mark` = '1' AND product_id = ?i AND publish = '1'", $products[$i]['product_id']);
        $products[$i]['positive_count'] = count($positive_count);

        $negative_count = $db->getAll("SELECT `review_id` FROM `fw7uf_jshopping_products_reviews` WHERE `mark` = '0' AND product_id = ?i AND publish = '1'", $products[$i]['product_id']);
        $products[$i]['negative_count'] = count($negative_count);
    }
    $buttons = $db->getRow("SELECT fw7uf_jshopping_categories.`article_id`,fw7uf_jshopping_categories.`button_add` FROM fw7uf_jshopping_categories WHERE category_id = ?i", $category_id);
    if($buttons){
        $buttons['button_text'] = $db->getOne("SELECT `fulltext` FROM fw7uf_content WHERE id = ?i",$buttons['article_id']);
        $buttons['button_select'] = $db->getOne("SELECT `title` FROM fw7uf_content WHERE id = ?i",$buttons['article_id']);
        unset($buttons['article_id']);
    }
    //print_r($products_id);
    //for ($i = 0; $i < count($products_id); $i++) {
    /*
      $search = $app->request->post('search');
      if (!empty($search)) {
      $products[] = $db->getRow("SELECT `product_id`,`image` as `product_image`,`name_ru-RU` as `product_name` FROM fw7uf_jshopping_products WHERE product_id = ?i AND `name_ru-RU` LIKE '%" . $search . "%'", $products_id[$i]['product_id']);
      } else {
      $products[] = $db->getRow("SELECT `product_id`,`image` as `product_image`,`name_ru-RU` as `product_name` FROM fw7uf_jshopping_products WHERE product_id = ?i", $products_id[$i]['product_id']);

      }

     */

    //}
    //die;
    if (!empty($products[0]['product_id']) or ! empty($buttons)) {
        $response = $products;
        $response['buttons'] = $buttons;
        $response["error"] = false;
        $response["message"] = "OK";
    } else {
        $response["error"] = true;
        $response["message"] = "Products not found";
    }
    echoResponse(200, $response);
});

/**
 * @api {get} /getCategorys/ getCategorys
 * @apiVersion 0.1.0
 * @apiName getCategorys
 * @apiGroup Categories
 * 
 * 
 * 
 */
$app->get('/getCategorys/', function() use ($app) {
    global $db;

    $categorys = $db->getAll("SELECT * FROM fw7uf_jshopping_categories");
    if ($categorys) {
        for ($i = 0; $i < count($categorys); $i++) {
            //$categorys[$i]['num_entries'] = count($db->getAll("SELECT product_id FROM fw7uf_jshopping_products_to_categories WHERE category_id = ?i", $categorys[$i]['category_id']));
            $categorys[$i]['num_entries'] = getGlobalProducts($categorys[$i]['category_id'], 1);
            $categorys[$i]['num_chiled_categorys'] = count($db->getAll("SELECT category_id,category_parent_id,`name_ru-RU`,category_type,category_img FROM `fw7uf_jshopping_categories` WHERE category_parent_id = ?i", $categorys[$i]['category_id']));
        }
    }
    if (!empty($categorys[0]['category_id'])) {
        $response = $categorys;
        $response["error"] = false;
        $response["message"] = "OK";
    } else {
        $response["error"] = true;
        $response["message"] = "Products not found";
    }
    echoResponse(200, $response);
});


/**
 * @api {post} /createAnswer/ createAnswer
 * @apiVersion 0.1.0
 * @apiName createAnswer
 * @apiGroup Answers
 * 
 * @apiParam {Number} product_id
 * @apiParam {Number} user_id
 * @apiParam {string} review
 * @apiParam {Number="1","0"} mark
 * @apiParam {photo} file
 *  
 * 
 */
$app->post('/createAnswer/', 'authenticate', function() use ($app) {
    global $db, $user_api_id, $today;
    $toBase['product_id'] = $app->request->post('product_id');
    $toBase['user_id'] = $app->request->post('user_id');
    if ($toBase['user_id'] !== $user_api_id) {
        //wrongUser();
    }
    //$toBase['check_date'] = $app->request->post('time');
    $toBase['review'] = $app->request->post('review');
    $toBase['publish'] = 0;
    //$toBase['ip'] = $app->request->post('ip');
    $toBase['mark'] = $app->request->post('mark');
    //$toBase['like'] = $app->request->post('like');
    //$toBase['photo'] = $app->request->post('photo');

    $uploaddir = '../components/com_jshopping/files/img_reviews/';
    $photo_name = md5($today . $user_api_id . 'I RUN AND GO EAT AND SLEEP NOW');
    $uploadfile = $uploaddir . basename($photo_name) . '.jpg';
    //print_r($_FILES);die;
    if (!empty($_FILES['file']['tmp_name'])) {
        if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadfile)) {
            //$toBase['photo'] = 'http://k.dsgn-ku.ru/api/images/' . $photo_name . '.jpg';  
            $toBase['photo'] = '' . $photo_name . '.jpg';
        } else {
            wrongUser('cant moves');
            //die('cant move');
        }
    } else {
        wrongUser('dont have image');
        //die('not have image');
    }
    //print_r($toBase);die;
    /*
      if (!empty($_FILES['file_check']['tmp_name'])) {
      if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadfile)) {
      //$toBase['photo'] = 'http://k.dsgn-ku.ru/api/images/' . $photo_name . '.jpg';
      $toBase['file_check'] = '' . $photo_name . '.jpg';
      }
      }
     */
    checkArr($toBase);
    $toBase['ip'] = $app->request->post('ip');
    $is = $db->query("INSERT INTO fw7uf_jshopping_products_reviews SET ?u,time = NOW()", $toBase);
    if ($is) {
        $response["error"] = false;
        $response["message"] = "OK";
    } else {
        $response["error"] = true;
        $response["message"] = "Some think wrong, say developer, error_num:13";
    }




    echoResponse(200, $response);
});

/**
 * @api {post} /createAnswerWithProduct/ createAnswerWithProduct
 * @apiVersion 0.1.0
 * @apiName createAnswerWithProduct
 * @apiGroup Answers
 * 
 * @apiParam {Number} user_id
 * @apiParam {Number} category_id
 * @apiParam {string} review
 * @apiParam {Number="1","0"} mark
 * @apiParam {photo} file
 * @apiParam {photo} file_check
 * @apiParam {string} time check_date
 * @apiParam {string} [ip] 
 *  
 * 
 */
/**
 * @api {post} /createAnswerWithProduct/ createAnswerWithProduct
 * @apiVersion 0.2.0
 * @apiName createAnswerWithProduct
 * @apiGroup Answers
 * 
 * @apiParam {Number} user_id
 * @apiParam {Number} category_id
 * @apiParam {string} review
 * @apiParam {Number="1","0"} mark
 * @apiParam {photo} file_check
 * @apiParam {string} time check_date
 * @apiParam {string} [ip] 
 *  
 * 
 */
$app->post('/createAnswerWithProduct/', 'authenticate', function() use ($app) {
    global $db, $user_api_id, $today;
    $uploaddir = '../components/com_jshopping/files/img_products/';
    $photo_name = md5($today . $user_api_id . 'I RUN AND GO EAT AND SLEEP NOW');
    $uploadfile = $uploaddir . basename($photo_name) . '.jpg';
    //14.12.2015 20:03 archive upload files
    $toBase['photo'] = 1;
    //die;
    $toProduct = array('parent_id' => 0, 'product_quantity' => 0, 'unlimited' => 0, 'product_publish' => 1, 'currency_id' => 1, 'product_template' => 'default', 'product_manufacturer_id' => 1, 'add_price_unit_id' => 3, 'name_ru-RU' => $app->request->post('product_name'), 'image' => $toBase['photo']);
    $db->query("INSERT INTO `fw7uf_jshopping_products` SET ?u", $toProduct);
    //$toBase['product_id'] = $app->request->post('product_id');
    $toBase['product_id'] = $db->insertId();
    if (!empty($toBase['product_id'])) {
        $category_id = $app->request->post('category_id');
        $is = $db->query("INSERT INTO `fw7uf_jshopping_products_to_categories` SET product_id = ?i,category_id = ?i", $toBase['product_id'], $category_id);
        if (!$is) {
            $response["error"] = true;
            $response["message"] = "Error insert info to DB";
            echoResponse(200, $response);
        }
    }
    $toBase['user_id'] = $app->request->post('user_id');
    if ($toBase['user_id'] !== $user_api_id) {
        //wrongUser();
    }
    $toBase['check_date'] = $app->request->post('time');
    $toBase['review'] = $app->request->post('review');
    //$toBase['publish'] = $app->request->post('publish');
    //$toBase['ip'] = $app->request->post('ip');
    $toBase['mark'] = $app->request->post('mark');
    //$toBase['like'] = $app->request->post('like');
    //$toBase['photo'] = $app->request->post('photo');
    //print_r($_FILES);die;

    $uploaddir = '../components/com_jshopping/files/img_products/';
    $photo_name = md5($today . $user_api_id . 'I RUN AND GO EAT AND SLEEP NOW s');
    $uploadfile = $uploaddir . basename($photo_name) . '.jpg';
    if (!empty($_FILES['file_check']['tmp_name'])) {
        if (move_uploaded_file($_FILES['file_check']['tmp_name'], $uploadfile)) {
            //$toBase['photo'] = 'http://k.dsgn-ku.ru/api/images/' . $photo_name . '.jpg';  
            $toBase['check_photo'] = '' . $photo_name . '.jpg';
        }
    }

    checkArr($toBase);
    unset($toBase['photo']);
    $toBase['ip'] = $app->request->post('ip');
    $is = $db->query("INSERT INTO fw7uf_jshopping_products_reviews SET ?u,time = NOW()", $toBase);
    if ($is) {
        $response['review_id'] = $db->insertId();
        $response["error"] = false;
        $response["message"] = "OK";
    } else {
        $response["error"] = true;
        $response["message"] = "Some think wrong, say developer, error_num:13";
    }
    echoResponse(200, $response);
});

/**
 * @api {post} /setItemsToWin/ setItemsToWin
 * @apiVersion 0.1.0
 * @apiName setItemsToWin
 * @apiGroup Raffles
 * 
 * @apiParam {string} wk item id to win
 * @apiParam {string} mh
 * @apiParam {string} yr
 * @apiParam {string} wk_end datetime to end
 * @apiParam {string} mh_end
 * @apiParam {string} yr_end
 *  
 * 
 */
$app->post('/setItemsToWin/', 'authenticate', function() use ($app) {
    global $db, $user_api_id;
    $toBase['wk'] = $app->request->post('wk');
    $toBase['mh'] = $app->request->post('mh');
    $toBase['yr'] = $app->request->post('yr');
    $toBase['wk_end'] = $app->request->post('wk_end');
    $toBase['mh_end'] = $app->request->post('mh_end');
    $toBase['yr_end'] = $app->request->post('yr_end');
    foreach ($toBase as $key => $value) {
        if (!empty($value) and $value != '') {
            $toBaseGo[$key] = $value;
        }
    }
    if (checkArr($toBaseGo)) {
        $is = $db->query("UPDATE drawing SET ?u WHERE id = '1'", $toBaseGo);
        if ($is) {
            $response["error"] = false;
            $response["message"] = "OK";
        } else {
            $response["error"] = true;
            $response["message"] = "Some wrong, error:14";
        }
    } else {
        $response["error"] = true;
        $response["message"] = "Where data?";
    }


    echoResponse(200, $response);
});

/**
 * @api {get} /getCities/ getCities
 * @apiVersion 0.1.0
 * @apiName getCities
 * @apiGroup Cities
 * 
 *  
 * 
 */
$app->get('/getCities/', function() use ($app) {
    global $db;
    $cities = $db->getAll("SELECT * FROM cities");
    if ($cities) {
        $response = $cities;
        $response["error"] = false;
        $response["message"] = "OK";
    } else {
        $response["error"] = true;
        $response["message"] = "Cities not found";
    }
    echoResponse(200, $response);
});

/**
 * @api {get} /getCity/:id getCity
 * @apiVersion 0.1.0
 * @apiName getCity
 * @apiGroup Cities
 * 
 * @apiParam {Number} id id city from bd
 * 
 */
$app->get('/getCity/:id', function($id = false) use ($app) {
    global $db;
    $cities = $db->getRow("SELECT * FROM cities WHERE id = ?i", $id);
    if ($cities) {
        $response = $cities;
        $response["error"] = false;
        $response["message"] = "OK";
    } else {
        $response["error"] = true;
        $response["message"] = "City not found";
    }
    echoResponse(200, $response);
});

/**
 * @api {get} /getDrawInfo/ getDrawInfo
 * @apiVersion 0.1.1
 * @apiName getDrawInfo
 * @apiGroup Raffles
 * @apiHeader {String} Auth Users unique api key
 * 
 *  
 * 
 */
$app->get('/getDrawInfo/', 'authenticate', function() use ($app) {
    global $db, $user_api_id;
    //print_r($user_api_id);
    $draw = $db->getRow("SELECT * FROM drawing WHERE id = '1'");
    $info['wk_rev'] = $db->getAll("SELECT * FROM fw7uf_jshopping_products_reviews WHERE time >= ?s and time <= ?s", $draw['wk_start'], $draw['wk_end']);
    $info['mh_rev'] = $db->getAll("SELECT * FROM fw7uf_jshopping_products_reviews WHERE time >= ?s and time <= ?s", $draw['mh_start'], $draw['mh_end']);
    $info['yr_rev'] = $db->getAll("SELECT * FROM fw7uf_jshopping_products_reviews WHERE time >= ?s and time <= ?s", $draw['yr_start'], $draw['yr_end']);
    //print_r($info);die;



    $userCounter = [];
    $times = array('wk', 'mh', 'yr');
    $sea = false;
    foreach ($times as $value) {
        // расчёт времени
        $dateNow = new DateTime("now");
        $end_time = $draw['' . $value . '_end'];

        $dateDiff = new DateTime("$end_time");
        //print_r($dateNow); print_r($dateDiff);die;
        if ($dateNow >= $dateDiff) {
            $userCounter[$value]['time_remain'] = 'Розыгрыш окончен';
        } else {
            $intervalDiff = $dateDiff->diff($dateNow);
            //$userCounter[$value]['time_remain'] = $intervalDiff->format("%d дней %i минут %s секунд");
            $userCounter[$value]['time_remain']['days'] = $intervalDiff->format("%d");
            $userCounter[$value]['time_remain']['hours'] = $intervalDiff->format("%h");
            $userCounter[$value]['time_remain']['minits'] = $intervalDiff->format("%i");
            $userCounter[$value]['time_remain']['seconds'] = $intervalDiff->format("%s");
        }
        //приз
        $product = $db->getRow("SELECT `name_ru-RU` as item_name,image FROM fw7uf_jshopping_products WHERE product_id = ?i", $draw[$value]);
        $userCounter[$value]['item_name'] = $product['item_name'];
        $userCounter[$value]['item_image'] = $product['image'];
        /*
         * Последний выйгравший, инфа
         */
        $userCounter[$value]['last_win'] = $draw[$value . '_last_win'];
        $userInfo = $db->getRow("SELECT name,username,photo FROM fw7uf_users WHERE id = ?i", $userCounter[$value]['last_win']);
        if ($userInfo) {
            $userCounter[$value]['last_win_info'] = $userInfo;
        } else {
            $userCounter[$value]['last_win_info']['name'] = null;
            $userCounter[$value]['last_win_info']['username'] = null;
            $userCounter[$value]['last_win_info']['photo'] = null;
        }
        $userCounter[$value]['last_win_info']['count'] = rand(10, 100);

        for ($i = 0; $i < count($info['' . $value . '_rev']); $i++) {
            $user_id = $info['' . $value . '_rev'][$i]['user_id'];
            if ($user_id == $user_api_id) {
                $sea = true;
            }
            if (!isset($userCounter[$value][$user_id])) {
                $userCounter[$value][$user_id] = 1;
            } else {
                $userCounter[$value][$user_id] = $userCounter[$value][$user_id] + 1;
            }
            //$info['wk_users'][$user_id] = $info['wk_users'][''.$info['wk_rev'][$i]['user_id'].''] + 1;
        }
        //arsort($userCounter[$value]);

        $k = 0;
        foreach ($userCounter[$value] as $key => $val) {
            if (is_integer($key)) {
                $userProfile = $db->getRow("SELECT name,username,photo FROM fw7uf_users WHERE id = ?i", $key);
                $userCounter[$value]['users'][$k]['user_id'] = $key;
                $userCounter[$value]['users'][$k]['count'] = $val;
                $userCounter[$value]['users'][$k]['user_name'] = $userProfile['name'];
                $userCounter[$value]['users'][$k]['user_username'] = $userProfile['username'];
                $userCounter[$value]['users'][$k]['user_photo'] = $userProfile['photo'];
                unset($userCounter[$value][$key]);
                $k++;
            } else {
                continue;
            }
        }
        if (empty($userCounter[$value]['users'])) {
            $userCounter[$value]['users'] = array();
        }
    }
    /////////////// FOR TESTS///////////
    //$sea = true;
    //$user_api_id = 641;
    /////////////// FOR TESTS///////////
    $goCount = false;

    foreach ($times as $value) {
        $place = 'Вы не участвуете';
        usort($userCounter[$value]['users'], function($a, $b) {
            if ($a['count'] === $b['count'])
                return 0;
            return $a['count'] < $b['count'] ? 1 : -1;
        });

        //заполняем людей и ищем наше место
        unset($users);
        $users = $userCounter[$value]['users'];
        unset($userCounter[$value]['users']);
        $t = 0;
        $place_count = 0;
        $find = FALSE;
        foreach ($users as $val) {
            if ($t < 3) {
                $userCounter[$value]['users'][$t] = $val;
                $t++;
            }
            if ($val['user_id'] == $user_api_id) {
                $userCounter[$value]['you']['place'] = ($place_count + 1);
                $userCounter[$value]['you']['count'] = $val['count'];
                $find = true;
            } elseif ($val['user_id'] != $user_api_id AND $find != true) {
                $userCounter[$value]['you']['place'] = $place;
                $userCounter[$value]['you']['count'] = 0;
            }
            $place_count++;
        }

        //print_r($users);
        //$userCounter[$value]['you_place'] = $place;
    }
    //print_r($userCounter);die;  
    if ($userCounter) {
        $response = $userCounter;
        $response["error"] = false;
        $response["message"] = "OK";
    } else {
        $response["error"] = true;
        $response["message"] = "Products not found";
    }
    echoResponse(200, $response);
});
/**
 * @api {get} /fastSearch/:text fastSearch
 * @apiVersion 0.1.0
 * @apiName fastSearch
 * @apiGroup Search
 * @apiDescription by product_name
 * 
 * @apiParam {string} text
 *  
 * 
 */
/**
 * @api {get} /fastSearch/:text/:city_id fastSearch
 * @apiVersion 0.2.0
 * @apiName fastSearch
 * @apiGroup Search
 * @apiDescription by product_name
 * 
 * @apiParam {string} text
 * @apiParam {string} [city_id]
 *  
 * 
 */
$app->get('/fastSearch/:text(/:city_id)', function($text, $city_id = false) use ($app) {
    global $db;
    if (!empty($text)) {
        $products = $db->getAll("SELECT `name_ru-RU` as product_name,product_id,city_id FROM `fw7uf_jshopping_products` WHERE `name_ru-RU` LIKE ?s " . ($city_id != false ? "AND city_id = '$city_id'" : "") . " ", '%' . $text . '%');
        for ($i = 0; $i < count($products); $i++) {
            if ($products[$i]['city_id'] != 'NULL' and ! empty($products[$i]['city_id'])) {
                $products[$i]['city'] = $db->getRow("SELECT * FROM cities WHERE id = ?i", $products[$i]['city_id']);
                unset($products[$i]['city_id']);
            }
        }
    } else {
        $products = false;
    }
    if ($products) {
        $response = $products;
        $response["error"] = false;
        $response["message"] = "OK";
    } else {
        $response["error"] = true;
        $response["message"] = "Products not found";
    }
    echoResponse(200, $response);
});

/**
 * @api {get} /fastSearchCategory/:text/:city_id fastSearchCategory
 * @apiVersion 0.1.0
 * @apiName fastSearchCategory
 * @apiGroup Search
 * @apiDescription by category
 * 
 * @apiParam {string} text
 * @apiParam {string} [city_id]
 * 
 */
$app->get('/fastSearchCategory/:text(/:city_id)', function($text, $city_id = false) use ($app) {
    global $db;
    if (!empty($text)) {
        $products = $db->getAll("SELECT * FROM `fw7uf_jshopping_categories` WHERE `name_ru-RU` LIKE ?s " . ($city_id != false ? "AND city_id = '$city_id'" : "") . "", '%' . $text . '%');
        for ($i = 0; $i < count($products); $i++) {
            if ($products[$i]['city_id'] != 'NULL' and ! empty($products[$i]['city_id']) and $products[$i]['city_id'] != null) {
                $products[$i]['city'] = $db->getRow("SELECT * FROM cities WHERE id = ?i", $products[$i]['city_id']);
                unset($products[$i]['city_id']);
            } else {
                $products[$i]['city']['id'] = null;
                $products[$i]['city']['name'] = null;
                unset($products[$i]['city_id']);
            }
        }
    } else {
        $products = false;
    }
    for ($i = 0; $i < count($products); $i++) {
        if ($products[$i]['category_type'] == 'products') {
            $response['products'][] = $products[$i];
        } elseif ($products[$i]['category_type'] == 'services') {
            $response['services'][] = $products[$i];
        }
    }

    if ($response) {
        //$response = $products;
        $response["error"] = false;
        $response["message"] = "OK";
    } else {
        $response["error"] = true;
        $response["message"] = "Products not found";
    }
    echoResponse(200, $response);
});
/**
 * @api {get} /v2/fastSearchCategory/:text/:city_id fastSearchCategory
 * @apiVersion 0.3.1
 * @apiName fastSearchCategory
 * @apiGroup Search
 * @apiDescription by category,допилена множественная привязка
 * 
 * @apiParam {string} text
 * @apiParam {string} [city_id]
 * 
 */
$app->get('/v2/fastSearchCategory/:text(/:city_id)', function($text, $city_id = false) use ($app) {
    global $db;
    if (!empty($text)) {
        if ($city_id) {
            //AND `cities_bind`.`param_id` = '21'
            $products = $db->getAll("SELECT `fw7uf_jshopping_categories`.`category_id`,`fw7uf_jshopping_categories`.`category_type`,`fw7uf_jshopping_categories`.`category_parent_id`,`fw7uf_jshopping_categories`.`name_ru-RU`,`cities_bind`.`city_id` as `city_id` FROM `fw7uf_jshopping_categories` LEFT JOIN `cities_bind` ON `fw7uf_jshopping_categories`.`category_id` = `cities_bind`.`param_id` WHERE `name_ru-RU` LIKE ?s AND `cities_bind`.`param_type` = 'categorie' AND `cities_bind`.`city_id` = ?i", '%' . $text . '%', $city_id);
            for ($i = 0; $i < count($products); $i++) {
                //маразм крепчал
                if ($products[$i]['city_id'] != 'NULL' and ! empty($products[$i]['city_id']) and $products[$i]['city_id'] != null) {
                    $products[$i]['citys'] = $db->getRow("SELECT * FROM cities WHERE id = ?i", $products[$i]['city_id']);
                } else {
                    $products[$i]['citys']['id'] = null;
                    $products[$i]['citys']['name'] = null;
                }
                unset($products[$i]['city_id']);
            }
        } else {
            //$products = $db->getAll("SELECT `fw7uf_jshopping_categories`.`category_id`,`fw7uf_jshopping_categories`.`category_type`,`fw7uf_jshopping_categories`.`category_parent_id`,`fw7uf_jshopping_categories`.`name_ru-RU`,`cities_bind`.`city_id` as `city_id` FROM `fw7uf_jshopping_categories` LEFT JOIN `cities_bind` ON `fw7uf_jshopping_categories`.`category_id` = `cities_bind`.`param_id` WHERE `name_ru-RU` LIKE ?s AND `cities_bind`.`param_type` = 'categorie'", '%' . $text . '%');
            $products = $db->getAll("SELECT `fw7uf_jshopping_categories`.`category_id`,`fw7uf_jshopping_categories`.`category_type`,`fw7uf_jshopping_categories`.`category_parent_id`,`fw7uf_jshopping_categories`.`name_ru-RU` FROM `fw7uf_jshopping_categories` WHERE `name_ru-RU` LIKE ?s ", '%' . $text . '%');
            for ($i = 0; $i < count($products); $i++) {
                $cities = $db->getAll("SELECT * FROM cities_bind WHERE param_type = 'categorie' AND param_id = ?i", $products[$i]['category_id']);
                if (is_array($cities)) {
                    foreach ($cities as $citie) {
                        $products[$i]['citys'][] = $db->getRow("SELECT * FROM cities WHERE id = ?i", $citie['city_id']);
                    }
                } else {
                    $products[$i]['citys']['id'] = null;
                    $products[$i]['citys']['name'] = null;
                }
                unset($products[$i]['city_id']);
            }
        }


        //old
        //$products = $db->getAll("SELECT * FROM `fw7uf_jshopping_categories` WHERE `name_ru-RU` LIKE ?s " . ($city_id != false ? "AND city_id = '$city_id'" : "") . "", '%' . $text . '%');
    } else {
        $products = false;
    }
    for ($i = 0; $i < count($products); $i++) {
        if ($products[$i]['category_type'] == 'products') {
            $response['products'][] = $products[$i];
        } elseif ($products[$i]['category_type'] == 'services') {
            $response['services'][] = $products[$i];
        }
    }
    if ($response) {
        //$response = $products;
        $response["error"] = false;
        $response["message"] = "OK";
    } else {
        $response["error"] = true;
        $response["message"] = "Products not found";
    }
    echoResponse(200, $response);
});
/**
 * @api {get} /search/:text search
 * @apiVersion 0.1.1
 * @apiName search
 * @apiGroup Search
 * @apiDescription Поиск нормальный по продуктам
 * 
 * @apiParam {string} text
 *  
 * 
 */
/**
 * @api {get} /search/:text/:city_id search
 * @apiVersion 0.2.0
 * @apiName search
 * @apiGroup Search
 * @apiDescription Поиск нормальный по продуктам
 * 
 * @apiParam {string} text
 * @apiParam {string} [city_id] 
 * 
 */
$app->get('/search/:text(/:city_id)', function($text, $city_id = false) use ($app) {
    global $db;
    if (!empty($text)) {
        $products = $db->getAll("SELECT * FROM `fw7uf_jshopping_products` WHERE `name_ru-RU` LIKE ?s " . ($city_id != false ? "AND city_id = '$city_id'" : "") . " ", '%' . $text . '%');
    } else {
        $products = false;
    }
    for ($i = 0; $i < count($products); $i++) {
        //positive_counts
        $positive_count = $db->getAll("SELECT `review_id` FROM `fw7uf_jshopping_products_reviews` WHERE `mark` = '1' AND product_id = ?i AND publish = '1'", $products[$i]['product_id']);
        $products[$i]['positive_count'] = count($positive_count);

        $negative_count = $db->getAll("SELECT `review_id` FROM `fw7uf_jshopping_products_reviews` WHERE `mark` = '0' AND product_id = ?i AND publish = '1'", $products[$i]['product_id']);
        $products[$i]['negative_count'] = count($negative_count);
    }
    if ($products) {
        $response = $products;
        $response["error"] = false;
        $response["message"] = "OK";
    } else {
        $response["error"] = true;
        $response["message"] = "Products not found";
    }
    echoResponse(200, $response);
});

/**
 * @api {get} /mainActivity/ mainActivity
 * @apiVersion 0.1.0
 * @apiName mainActivity
 * @apiGroup Activity
 * 
 * 
 *  
 * 
 */
$app->get('/mainActivity/', function() use ($app) {
    global $db;
    $products_categorys = getChiledCategorys4();
    $services_categorys = getChiledCategorys5();

    //print_r(getChiledCategorys(5));
    //die;
    //print_r($categorys);


    $searchProducts = true;
    $searchServices = true;
    $try = 50;
    while ($searchProducts) {
        shuffle($products_categorys);
        $products_category = $products_categorys[0]['category_id'];
        $products = $db->getAll("SELECT fw7uf_jshopping_products.product_id,fw7uf_jshopping_products.average_rating,fw7uf_jshopping_products.image,fw7uf_jshopping_products.`name_ru-RU` as product_name,fw7uf_jshopping_products_to_categories.category_id FROM `fw7uf_jshopping_products`,`fw7uf_jshopping_products_to_categories` WHERE fw7uf_jshopping_products.product_id = fw7uf_jshopping_products_to_categories.product_id AND fw7uf_jshopping_products_to_categories.category_id = " . $products_category . " ORDER BY average_rating DESC LIMIT 0,3");
        if(count($products) >= 3 or $try < 1){
            $searchProducts = false;
        }
        $try--;
        
    }
    while ($searchServices) {
        shuffle($products_categorys);
        $services_category = $services_categorys[0]['category_id'];
        $services = $db->getAll("SELECT fw7uf_jshopping_products.product_id,fw7uf_jshopping_products.average_rating,fw7uf_jshopping_products.image,fw7uf_jshopping_products.`name_ru-RU` as product_name,fw7uf_jshopping_products_to_categories.category_id FROM `fw7uf_jshopping_products`,`fw7uf_jshopping_products_to_categories` WHERE fw7uf_jshopping_products.product_id = fw7uf_jshopping_products_to_categories.product_id AND fw7uf_jshopping_products_to_categories.category_id = " . $services_category . " ORDER BY average_rating DESC LIMIT 0,3");
        if(count($services) >= 3 or $try < 1){
            $searchServices = false;
        }
        $try--;
    }
    //print_r($services_category.':');
    //print_r($products_category);
    //die;
    //$products = $db->getAll("SELECT product_id,image,`name_ru-RU` as product_name FROM fw7uf_jshopping_products ");
    for ($i = 0; $i < count($products); $i++) {
        $products[$i]['positive_count'] = count($db->getAll("SELECT `review_id` FROM `fw7uf_jshopping_products_reviews` WHERE `mark` = '1' AND product_id = ?s AND publish = '1'", $products[$i]['product_id']));
        $products[$i]['negative_count'] = count($db->getAll("SELECT `review_id` FROM `fw7uf_jshopping_products_reviews` WHERE `mark` = '0' AND product_id = ?s AND publish = '1'", $products[$i]['product_id']));
    }
    for ($i = 0; $i < count($services); $i++) {
        $services[$i]['positive_count'] = count($db->getAll("SELECT `review_id` FROM `fw7uf_jshopping_products_reviews` WHERE `mark` = '1' AND product_id = ?s AND publish = '1'", $services[$i]['product_id']));
        $services[$i]['negative_count'] = count($db->getAll("SELECT `review_id` FROM `fw7uf_jshopping_products_reviews` WHERE `mark` = '0' AND product_id = ?s AND publish = '1'", $services[$i]['product_id']));
    }

    $ans['products'] = $products;
    $ans['services'] = $services;
    $ans['bread']['products'] = getBread($products_category, true);

    $ans['bread']['services'] = getBread($services_category, true);

    if ($ans) {
        $response = $ans;
        $response["error"] = false;
        $response["message"] = "OK";
    } else {
        $response["error"] = true;
        $response["message"] = "Products not found";
    }
    echoResponse(200, $response);
});
/**
 * @api {get} /mainCategorys/ mainCategorys
 * @apiVersion 0.1.0
 * @apiName mainCategorys
 * @apiGroup Activity
 * 
 * 
 *  
 * 
 */
$app->get('/mainCategorys/', function() use ($app) {
    global $db;

    $categorys = $db->getAll("SELECT category_id,category_parent_id,`name_ru-RU` as `category_name`,category_type,category_img FROM `fw7uf_jshopping_categories` WHERE category_parent_id = '0'");
    if ($categorys) {
        for ($i = 0; $i < count($categorys); $i++) {
            $categorys[$i]['num_entries'] = getGlobalProducts($categorys[$i]['category_id'], 1);
            //$categorys[$i]['num_entries'] = count($db->getAll("SELECT product_id FROM fw7uf_jshopping_products_to_categories WHERE category_id = ?i", $categorys[$i]['category_id']));
        }
    }
    if ($categorys) {
        $response = $categorys;
        $response["error"] = false;
        $response["message"] = "OK";
    } else {
        $response["error"] = true;
        $response["message"] = "Categorys not found";
    }
    echoResponse(200, $response);
});
/* фоточку грузить
  //all right
  $uploaddir = './images/';
  $photo_name = md5($user_id . 'I RUN AND GO EAT AND SLEEP NOW');
  $uploadfile = $uploaddir . basename($photo_name) . '.jpg';
  //print_r($_FILES);die;
  if (!empty($_FILES['file']['tmp_name'])) {
  if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadfile)) {
  $toBase['photo'] = 'http://k.dsgn-ku.ru/api/images/' . $photo_name . '.jpg';
  ;
  }
  }

 */
/**
 * @api {get} /childCategorys/:parentCategory childCategorys
 * @apiVersion 0.1.0
 * @apiName childCategorys
 * @apiGroup Categories
 * 
 * @apiParam {string} parentCategory
 *  
 * 
 */
/**
 * @api {get} /childCategorys/:parentCategory/:city_id childCategorys
 * @apiVersion 0.2.0
 * @apiName childCategorys
 * @apiGroup Categories
 * 
 * @apiParam {string} parentCategory
 * @apiParam {string} [city_id] ЗАГОЛОВКОМ
 * 
 */
$app->get('/childCategorys/:parentCategory(/:city_id)', function($parentCategory, $city_id = false) use ($app) {
    global $db;

    $categorys = $db->getAll("SELECT category_id,category_parent_id,`name_ru-RU`  as `category_name`,category_type,category_image as category_img FROM `fw7uf_jshopping_categories` WHERE category_parent_id = ?i " . ($city_id != false ? "AND city_id = '$city_id'" : "") . "", $parentCategory);
    if ($categorys) {
        for ($i = 0; $i < count($categorys); $i++) {
            //$categorys[$i]['num_entries'] = count($db->getAll("SELECT product_id FROM fw7uf_jshopping_products_to_categories WHERE category_id = ?i", $categorys[$i]['category_id']));
            $categorys[$i]['num_entries'] = getGlobalProducts($categorys[$i]['category_id'], 1);
            $categorys[$i]['num_chiled_categorys'] = count($db->getAll("SELECT category_id,category_parent_id,`name_ru-RU`,category_type,category_image FROM `fw7uf_jshopping_categories` WHERE category_parent_id = ?i", $categorys[$i]['category_id']));
        }
    }
    if ($categorys) {

        $response = $categorys;
        $response['bread'] = getBread($parentCategory);
        $response["error"] = false;
        $response["message"] = 'OK';
    } else {
        $response["error"] = true;
        $response["message"] = "Categorys not found";
    }
    echoResponse(200, $response);
});
/**
 * @api {get} /v2/childCategorys/:parentCategory/:city_id childCategorys
 * @apiVersion 0.3.1
 * @apiName childCategorys
 * @apiGroup Categories
 * 
 * @apiParam {string} parentCategory
 * @apiParam {string} [city_id]
 * 
 */
$app->get('/v2/childCategorys/:parentCategory(/:city_id)', function($parentCategory, $city_id = false) use ($app) {
    global $db, $global_city_id;
    if (!$city_id) {
        $categorys = $db->getAll("SELECT category_id,category_parent_id,`name_ru-RU`  as `category_name`,category_type,category_image as category_img FROM `fw7uf_jshopping_categories` WHERE category_parent_id = ?i ", $parentCategory);
        for ($i = 0; $i < count($categorys); $i++) {
            $cities = false;
            $cities = $db->getAll("SELECT * FROM cities_bind WHERE param_type = 'categorie' AND param_id = ?i", $categorys[$i]['category_id']);
            $categorys[$i]['citys'][0]['id'] = null;
            $categorys[$i]['citys'][0]['name'] = null;
            if ($cities) {
                unset($categorys[$i]['citys']);
                foreach ($cities as $citie) {
                    $categorys[$i]['citys'][] = $db->getRow("SELECT * FROM cities WHERE id = ?i", $citie['city_id']);
                }
            }
        }
    } else {
        $global_city_id = $city_id;
        //есть
        //$categorys = $db->getAll("SELECT category_id,category_parent_id,`name_ru-RU`  as `category_name`,category_type,category_image as category_img FROM `fw7uf_jshopping_categories` WHERE category_parent_id = ?i ", $parentCategory);
        $categorys = $db->getAll("SELECT `fw7uf_jshopping_categories`.category_id,`fw7uf_jshopping_categories`.category_parent_id,`fw7uf_jshopping_categories`.`name_ru-RU`  as `category_name`,`fw7uf_jshopping_categories`.category_type,`fw7uf_jshopping_categories`.category_image as category_img,cities_bind.city_id FROM `fw7uf_jshopping_categories` INNER JOIN 
                                    cities_bind ON cities_bind.param_id = `fw7uf_jshopping_categories`.category_id
                                    WHERE category_parent_id = ?i AND cities_bind.city_id = ?i", $parentCategory, $city_id);
        if ($categorys) {
            for ($i = 0; $i < count($categorys); $i++) {
                $categorys[$i]['citys'][] = $db->getRow("SELECT * FROM cities WHERE id = ?i", $categorys[$i]['city_id']);
                unset($categorys[$i]['city_id']);
            }
        }
    }
    if ($categorys) {
        for ($i = 0; $i < count($categorys); $i++) {
            //$categorys[$i]['num_entries'] = count($db->getAll("SELECT product_id FROM fw7uf_jshopping_products_to_categories WHERE category_id = ?i", $categorys[$i]['category_id']));
            $categorys[$i]['num_entries'] = getGlobalProducts($categorys[$i]['category_id'], 1);
            $categorys[$i]['num_chiled_categorys'] = count($db->getAll("SELECT category_id,category_parent_id,`name_ru-RU`,category_type,category_image FROM `fw7uf_jshopping_categories` WHERE category_parent_id = ?i", $categorys[$i]['category_id']));
        }
    }
    if ($categorys) {

        $response = $categorys;
        $response['bread'] = getBread($parentCategory);
        $response["error"] = false;
        $response["message"] = 'OK';
    } else {
        $response["error"] = true;
        $response["message"] = "Categorys not found";
    }
    echoResponse(200, $response);
});
/**
 * @api {get} /getGlobalProducts/:category_id getGlobalProducts
 * @apiVersion 0.1.0
 * @apiName getGlobalProducts
 * @apiGroup Products
 * 
 * @apiParam {Number} category_id
 *  
 * 
 */
$app->get('/getGlobalProducts/:category_id', function($category_id = false) use ($app) {
    global $db;
    $items = array();
    $category_id = (integer) $category_id;
    $data = getGlobalProductsItems($category_id, 1);
    //print_r($num);
    $i = 0;
    $products = array();
    foreach ($data as $dataOne) {
        if (is_array($dataOne)) {
            foreach ($dataOne as $one) {
                $products[$i]['product_id'] = $one['product_id'];
                $i++;
            }
        }
    }
    for ($j = 0; $j < count($products); $j++) {
        $products[$j] = $db->getRow("SELECT fw7uf_jshopping_products.product_id,fw7uf_jshopping_products.image as `product_image`,fw7uf_jshopping_products.`name_ru-RU` as `product_name` FROM `fw7uf_jshopping_products` WHERE fw7uf_jshopping_products.product_id =  ?i", $products[$j]['product_id']);
    }
    for ($i = 0; $i < count($products); $i++) {
        //positive_counts
        $positive_count = $db->getAll("SELECT `review_id` FROM `fw7uf_jshopping_products_reviews` WHERE `mark` = '1' AND product_id = ?i AND publish = '1'", $products[$i]['product_id']);
        $products[$i]['positive_count'] = count($positive_count);

        $negative_count = $db->getAll("SELECT `review_id` FROM `fw7uf_jshopping_products_reviews` WHERE `mark` = '0' AND product_id = ?i AND publish = '1'", $products[$i]['product_id']);
        $products[$i]['negative_count'] = count($negative_count);
    }
    /*
      if ($num) {
      $k = 0;
      foreach ($num[0] as $item) {
      $products = $db->getAll("SELECT fw7uf_jshopping_products.product_id,fw7uf_jshopping_products.image as `product_image`,fw7uf_jshopping_products.`name_ru-RU` as `product_name` FROM `fw7uf_jshopping_products` WHERE fw7uf_jshopping_products.product_id =  ?i", $item['product_id']);

      for ($i = 0; $i < count($products); $i++) {
      //positive_counts
      $positive_count = $db->getAll("SELECT `review_id` FROM `fw7uf_jshopping_products_reviews` WHERE `mark` = '1' AND product_id = ?i", $products[$i]['product_id']);
      $products[$i]['positive_count'] = count($positive_count);

      $negative_count = $db->getAll("SELECT `review_id` FROM `fw7uf_jshopping_products_reviews` WHERE `mark` = '0' AND product_id = ?i", $products[$i]['product_id']);
      $products[$i]['negative_count'] = count($negative_count);
      $items[$k] = $products[$i];
      $k++;
      }

      }
      }
     * 
     */
    if ($products) {

        $response = $products;
        $response["error"] = false;
        $response["message"] = 'OK';
    } else {
        $response["error"] = true;
        $response["message"] = "Products not found";
    }
    echoResponse(200, $response);
});

/**
 * @api {get} /getBread/:category_id getBread
 * @apiVersion 0.1.0
 * @apiName getBread
 * @apiGroup Bread
 * 
 * @apiParam {string} category_id
 *  
 * 
 */
$app->get('/getBread/:category_id', function($category_id = false) use ($app) {
    if ($category_id) {
        $response = getBread($category_id);
        $response["error"] = false;
        $response["message"] = "OK";
    } else {
        $response["error"] = true;
        $response["message"] = "Bread not found";
    }
    echoResponse(200, $response);
});
/**
 * @api {post} /setCategory/:id setCategory
 * @apiVersion 0.1.0
 * @apiName setCategory
 * @apiGroup Categories
 * 
 * @apiParam (Url parameter) {string} id Если число, обновить, если new то создать
 * 
 * @apiParam {string} category_parent_id
 * @apiParam {string} category_add_date
 * @apiParam {string} category_name
 * @apiParam {string} category_type
 * @apiParam {photo} file 
 *  
 * 
 */
$app->post('/setCategory/:id', 'authenticate', function($category_id) use ($app) {
    global $db, $user_id, $today;
    $toBase['category_parent_id'] = $app->request()->post('category_parent_id');
    $toBase['category_publish'] = '1';
    $toBase['category_ordertype'] = '1';
    $toBase['ordering'] = '1';
    //2015-04-19 22:31:16
    $toBase['category_add_date'] = $app->request()->post('category_add_date');
    $toBase['products_page'] = '10';
    $toBase['access'] = '1';
    $toBase['products_row'] = '3';
    $toBase['name_ru-RU'] = $app->request()->post('category_name');
    $toBase['category_type'] = $app->request()->post('category_type');
    checkArr($toBase);
    /*
     * фоточка
     */
    $uploaddir = '../components/com_jshopping/files/img_categories/';
    $photo_name = md5($today . $user_id . 'I RUN AND GO EAT AND SLEEP NOW');
    $uploadfile = $uploaddir . basename($photo_name) . '.jpg';
    //print_r($_FILES);die;
    if (!empty($_FILES['file']['tmp_name'])) {
        if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadfile)) {
            $toBase['category_img'] = 'http://k.dsgn-ku.ru/api/images/' . $photo_name . '.jpg';
            ;
        }
    }
    if ($category_id == 'new') {
        $is = $db->query("INSERT INTO fw7uf_jshopping_categories SET ?u", $toBase);
    } elseif ($category_id) {
        $is = $db->query("UPDATE fw7uf_jshopping_categories SET ?u", $toBase);
    }
    if ($is) {
        if ($category_id == 'new') {
            $response['category_id'] = $db->insertId();
        }
        $response["error"] = false;
        $response["message"] = "OK";
    } else {
        $response["error"] = true;
        $response["message"] = "ERROR:75723";
    }
    echoResponse(200, $response);
});


/**
 * @api {get} /getTextInfo/:text_id getTextInfo
 * @apiVersion 0.1.0
 * @apiName getTextInfo
 * @apiGroup TextInfo
 * 
 * @apiParam (Url parameter) {string="about","help","rules"} text_id 
 * 
 *  
 * 
 */
$app->get('/getTextInfo/:text_id', function($text_id) use ($app) {
    switch ($text_id) {
        case 'about':
            $response['text'] = '1 about Tested text.';
            break;
        case 'help':
            $response['text'] = '2 help tested text.';
            break;
        case 'rules':
            $response['text'] = '3 rules tested text.';
            break;
        case 'agreement':
            $response['text'] = 'agreement tested text';
            break;
        default:
            break;
    }
    if (!empty($response['text'])) {
        $response["error"] = false;
        $response["message"] = "OK";
    } else {
        $response["error"] = true;
        $response["message"] = "Wrong text id, i think";
    }
    echoResponse(200, $response);
});
/**
 * @api {get} /getPolls(/:id) getPolls
 * @apiVersion 0.1.0
 * @apiName getPolls
 * @apiGroup Polls
 * 
 * @apiParam (Url parameter) {Number} [id] 
 * 
 *  
 * 
 */
$app->get('/getPolls(/:id)', function($poll_id = 'all') use ($app) {
    global $db, $user_id;
    if ($poll_id == 'all') {
        $polls = $db->getAll("SELECT id,title,published,params,access FROM `fw7uf_mijopolls_polls`");
        for ($i = 0; $i < count($polls); $i++) {
            $polls[$i]['options'] = $db->getAll("SELECT * FROM `fw7uf_mijopolls_options` WHERE `poll_id` = ?i", $polls[$i]['id']);
        }
    } elseif ($poll_id == 'rand') {
        $polls = $db->getRow("SELECT id,title,published,params,access FROM `fw7uf_mijopolls_polls` ORDER BY RAND() LIMIT 1,1");
        $polls['options'] = $db->getAll("SELECT * FROM `fw7uf_mijopolls_options` WHERE `poll_id` = ?i", $polls['id']);
    } elseif ($poll_id > 0) {
        $poll_id = (integer) $poll_id;
        $polls = $db->getRow("SELECT id,title,published,params,access FROM `fw7uf_mijopolls_polls` WHERE id = ?i", $poll_id);
        $polls['options'] = $db->getAll("SELECT * FROM `fw7uf_mijopolls_options` WHERE `poll_id` = ?i", $polls['id']);
    } else {
        $polls = false;
    }

    if ($polls) {
        $response = $polls;
        $response["error"] = false;
        $response["message"] = "OK";
    } else {
        $response["error"] = true;
        $response["message"] = "Cant find polls from bd";
    }
    echoResponse(200, $response);
});

/**
 * @api {post} /setPollVote/ setPollVote
 * @apiVersion 0.1.0
 * @apiName setPollVote
 * @apiGroup Polls
 * 
 * @apiParam {datetime} date="2015-11-17 17:04:54" 
 * @apiParam {Number} option_id
 * @apiParam {Number} poll_id
 * @apiParam {Number} ip Цифровой тип,хз чё за 
 * @apiParam {Number} user_id
 * 
 */
$app->post('/setPollVote/', 'authenticate', function () use ($app) {
    global $db, $user_api_id;
    //print_r($user_api_id);
    foreach ($_POST as $key => $value) {
        $toBase[$key] = $value;
    }
    $toBase['user_id'] = $user_api_id;
    $toBase['ip'] = $_SERVER['REMOTE_ADDR'];
    if (is_array($toBase)) {
        $is = $db->query("INSERT INTO `fw7uf_mijopolls_votes` SET ?u,date = NOW()", $toBase);
    }
    if ($is) {
        $response["error"] = false;
        $response["message"] = "OK";
    } else {
        $response["error"] = true;
        $response["message"] = "some fails";
    }
    echoResponse(200, $response);
});
$app->run();
?>
