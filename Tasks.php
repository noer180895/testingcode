<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Tasks extends MY_Controller
{
    
    public function index()
    {
        echo "Powered by DO";
    }
    
    
    /* mengirim notifikasi memakai layanan pushy.
    saat ini pushy sudah tidak dipakai di tukang.id (deprecated). jadi fungsi ini saat ini tidak dipakai
    tapi fungsi ini dipakai oleh para pengguna aplikasi tukang.id dibawah versi 4.2 jadi tidak boleh dihapus */
    function send_pushy()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $userId = $this->input->post('userId');
        $token  = $this->input->post('token');
        
        $userType = 0;
        if (isset($_POST['userType']))
            $userType = $this->input->post('userType');
        
        if (!isset($_POST['web_broadcast']))
            $this->token_validate($userId, $token, $userType); //internal function
        
        $msg    = $this->input->post('msg');
        $target = $this->input->post('target');
        
        if (isset($_POST['isPendingOrder'])) {
            if ($this->input->post('isPendingOrder') == '1') {
                $identifier = (!$this->input->post('identifier')) ? '' : strval($this->input->post('identifier'));
                $this->load->model('tpending_order_model');
                $this->tpending_order_model->insert(array(
                    $msg,
                    $target,
                    $identifier
                ));
                return;
            }
        }
        
        $is_reroute_to_saber = 0;
        $should_push         = 1;
        
        // Payload data you want to send to Android device(s)
        // (it will be accessible via intent extras)
        $data = array(
            'msg' => $msg
        );
        
        if (isset($_POST['addition'])) {
            $additionJO = json_decode($this->input->post('addition'));
            if ($additionJO->do == "log_worker_go") {
                $this->log_go($additionJO->data->docId, $additionJO->data->workerId); //internal function
            }
            
            if ($additionJO->do == "log_worker_start") {
                $this->log_start($additionJO->data->docId, $additionJO->data->workerId); //internal function
            }
            
            if ($additionJO->do == "log_worker_finish") {
                $this->log_finish($additionJO->data->docId, $additionJO->data->workerId); //internal function
            }
            
            if ($additionJO->do == "reroute_job_to_saber") {
                $this->reroute_job_to_saber($additionJO->data->docId);
                $is_reroute_to_saber = 1;
            }
            
            if ($additionJO->do == "reroute_job_to_saber2") {
                $this->reroute_job_to_saber2($additionJO->data->docId);
                $is_reroute_to_saber = 1;
            }
            
            if ($additionJO->do == "reroute_job_from_saber") {
                $this->reroute_job_from_saber($additionJO->data->docId);
            }
        }
        
        $ids = array();
        
        if ($target == PARSE_CHANNEL || $target == PARSE_CHANNEL_TEST) {
            
            $this->load->model('muserlogin_model');
            $custEmail = (!$this->input->post('identifier')) ? '' : strval($this->input->post('identifier'));
            
            $is_targeting_timeout_and_cancel = isset($_POST['is_targeting_timeout_and_cancel']);
            
            $timeout_and_cancel_user_list = "";
            
            if ($is_targeting_timeout_and_cancel) {
                $this->load->model('torderdoc_model');
                $timeout_and_cancel_user_list = $this->torderdoc_model->get_timeout_and_cancel_user();
            }
            
            $ids    = $this->muserlogin_model->get_cust_pushy_reg_ids($custEmail, $timeout_and_cancel_user_list);
            $apiKey = 'ab8b0894acadfaf8c8421084c078720b95f351ca2ba7aa526c03dd13a9fb5ce0';
            
            if ($ids != array()) {
                
                if (isset($_POST['is_broadcast'])) {
                    if (strval($_POST['is_broadcast']) == "1") {
                        
                        if (!$is_targeting_timeout_and_cancel) {
                            $this->load->model('musertrack_model');
                            $result = $this->musertrack_model->get_all();
                            if ($result != array()) {
                                foreach ($result as $row) {
                                    $row_arr = explode(",", $row->prid);
                                    foreach ($row_arr as $an_id) {
                                        if ($an_id != "") {
                                            if (!in_array($an_id, $ids)) {
                                                array_push($ids, $an_id);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        
                        //upload broadcast image begin
                        
                        $is_success = 0;
                        
                        $target_dir     = FCPATH . '/uploads/promo/';
                        $uploadOk       = 1;
                        $image_url      = "";
                        $icon_image_url = "";
                        
                        if (isset($_FILES["image"])) {
                            $imageName     = basename($_FILES["image"]["name"]);
                            $target_file   = $target_dir . $imageName;
                            $imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);
                            
                            $check = getimagesize($_FILES["image"]["tmp_name"]);
                            if ($check !== false) {
                                // echo "File is an image - " . $check["mime"] . ".";
                                $uploadOk = 1;
                            } else {
                                $result->msg = "The file is not a picture";
                                $uploadOk    = 0;
                            }
                            
                            if ($_FILES["image"]["size"] > 10 * 1024 * 1024 && $uploadOk) {
                                $result->msg = "Sorry, image size is too big";
                                $uploadOk    = 0;
                            }
                            
                            if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $uploadOk) {
                                $result->msg = "Sorry, image must be in format JPG, JPEG, PNG or GIF";
                                $uploadOk    = 0;
                            }
                            
                            if ($uploadOk) {
                                if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                                    $uploadOk  = 1;
                                    $image_url = $target_file;
                                } else {
                                    $result->msg = "Sorry, image failed to be uploaded";
                                    $uploadOk    = 0;
                                }
                            }
                        }
                        
                        if ($uploadOk) {
                            if (isset($_FILES["iconImage"])) {
                                $iconName = basename($_FILES["iconImage"]["name"]);
                                
                                $target_file   = $target_dir . $iconName;
                                $imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);
                                
                                $check = getimagesize($_FILES["iconImage"]["tmp_name"]);
                                if ($check !== false && $uploadOk) {
                                    // echo "File is an image - " . $check["mime"] . ".";
                                    $uploadOk = 1;
                                } else {
                                    $result->msg = "Icon file is not an image";
                                    $uploadOk    = 0;
                                }
                                
                                if ($_FILES["iconImage"]["size"] > 10 * 1024 * 1024 && $uploadOk) {
                                    $result->msg = "Sorry, icon image size is too big";
                                    $uploadOk    = 0;
                                }
                                
                                if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $uploadOk) {
                                    $result->msg = "Sorry, icon image must be in format JPG, JPEG, PNG or GIF";
                                    $uploadOk    = 0;
                                }
                                
                                if (move_uploaded_file($_FILES["iconImage"]["tmp_name"], $target_file)) {
                                    $uploadOk       = 1;
                                    $icon_image_url = $target_file;
                                } else {
                                    $uploadOk    = 0;
                                    $result->msg = "Sorry, icon image failed to be uploaded";
                                }
                            } else {
                                $iconName = "";
                                $uploadOk = 1;
                            }
                        }
                        
                        if ($image_url != "" || $icon_image_url != "") {
                            
                            $image_url      = str_replace("/usr/share/nginx/html/", "tukang-backend.com/", $image_url);
                            $icon_image_url = str_replace("/usr/share/nginx/html/", "tukang-backend.com/", $icon_image_url);
                            
                            $image_url      = "http://" . str_replace("//", "/", $image_url);
                            $icon_image_url = "http://" . str_replace("//", "/", $icon_image_url);
                            
                            $msg_json = json_decode($msg);
                            $msg_new  = new stdClass();
                            
                            $msg_new = $msg_json;
                            
                            if ($image_url != "") {
                                $msg_new->imgUrl = $image_url;
                            } else {
                                $msg_new->imgUrl = "";
                            }
                            
                            if ($icon_image_url != "") {
                                $msg_new->imgUrlIcon = $icon_image_url;
                            } else {
                                $msg_new->imgUrlIcon = "";
                            }
                            
                            $data = array(
                                'msg' => $msg_new
                            );
                        }
                        //upload broadcast image end
                    }
                }
                
                sendPushyMessage($data, $ids, $apiKey);
            }
        }
        
        if ($target == PARSE_CHANNEL_MASTER || $target == PARSE_CHANNEL_MASTER_TEST) {
            
            $this->load->model('muserlogin_vendor_model');
            $masterUname = (!$this->input->post('identifier')) ? '' : strval($this->input->post('identifier'));
            
            $msg_json = json_decode($msg);
            
            $this->load->model('torderdoc_model');
            $this->torderdoc_model->update_isSentFromPushy($msg_json->docId);
            
            $exceptSaber = 0;
            
            $testnote = '';
            
            if (isset($msg_json->pushKind)) {
                if ($msg_json->pushKind == "00") {
                    $useTeam = $msg_json->useTeam;
                    
                    if ($useTeam == TEAM_VENDOR)
                        $exceptSaber = 1;
                    
                    if ($masterUname == '') {
                        if ($msg_json->notes != '' && $msg_json->notes != 'No notes' && $msg_json->notes != 'Tidak ada pesan lain') {
                            if ($this->is_testnote_simple($msg_json->docId)) {
                                $this->load->model('ttestnotes_model');
                                $masterUname = $this->ttestnotes_model->find($msg_json->notes);
                            }
                        }
                    }
                    
                    if ($masterUname == '') {
                        $this->load->model('muserlogin_model');
                        $masterUname = $this->muserlogin_model->find_preferred_vendor($msg_json->docId);
                    }
                    
                    if ($masterUname == '') {
                        if (isset($msg_json->voucherCode)) {
                            $masterUname = $this->muserlogin_vendor_model->find_referral_username($msg_json->voucherCode);
                        }
                    }
                }
                
                if ($msg_json->pushKind == "12") {
                    $should_push = 0;
                }
            } else {
                if (isset($msg_json->msg)) {
                    if (isset($msg_json->msg->pushKind)) {
                        if ($msg_json->msg->pushKind == "14") {
                            if ($is_reroute_to_saber) {
                                $msg_json->msg->msg    = "New order with no vendor response";
                                $msg_json->msg->status = -2;
                                $data                  = array(
                                    'msg' => $msg_json
                                );
                            }
                        }
                    }
                }
            }
            
            if ($masterUname == '') {
                if (isset($_POST['candidateVendors'])) {
                    $masterUname = $this->getVendorsByRanking($_POST['candidateVendors'], $masterUname);
                }
            }
            
            
            
            //send to master (masterplus) begin
            $ids = $this->muserlogin_vendor_model->get_vendor_pushy_reg_ids($masterUname, 1, $exceptSaber);
            
            $apiKey = '7aab83b1e8610c1b3db7ca6993a5373c2a7c3df79b3546ecdcf56f876827382b';
            
            if ($ids != array() && $should_push) {
                sendPushyMessage($data, $ids, $apiKey);
            }
            //send to master (masterplus) end
        }
        
        if ($target == PARSE_CHANNEL_WORKER || $target == PARSE_CHANNEL_WORKER_TEST) {
            $this->load->model('muserlogin_worker_model');
            $workerId = (!$this->input->post('identifier')) ? 0 : intval($this->input->post('identifier'));
            $ids      = $this->muserlogin_worker_model->get_worker_pushy_reg_ids($workerId);
            $apiKey   = 'd927810dc35595361852a28a72671896aad617663920eb899e67f2a0e28f9bc8';
            
            if ($ids != array())
                sendPushyMessage($data, $ids, $apiKey);
        }
    }
    
    //fungsi ini untuk mengirim broadcast terakhir memakai pushy
    function send_pushy_final()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $msg    = $this->input->post('msg');
        $target = $this->input->post('target');
        
        $is_reroute_to_saber = 0;
        $should_push         = 1;
        
        // Payload data you want to send to Android device(s)
        // (it will be accessible via intent extras)
        $data = array(
            'msg' => $msg
        );
        
        $ids = array();
        
        if ($target == PARSE_CHANNEL || $target == PARSE_CHANNEL_TEST) {
            
            echo "--1.";
            
            $this->load->model('muserlogin_model');
            $broadcast_targets_str = (!$this->input->post('identifiers')) ? '' : strval($this->input->post('identifiers'));
            
            $apiKey = 'ab8b0894acadfaf8c8421084c078720b95f351ca2ba7aa526c03dd13a9fb5ce0';
            
            $ids = json_decode($broadcast_targets_str);
            
            if ($ids != array()) {
                echo sendPushyMessage($data, $ids, $apiKey);
            }
        } else {
            echo "--2.";
        }
    }
    
    /*fungsi ini dipakai untuk mengirim broadcast secara langsung ke aplikasi customer.
    fungsi ini menggunakan layanan pushy untuk mengirim broadcast
    saat ini pushy sudah tidak dipakai di tukang.id (deprecated). jadi fungsi ini saat ini tidak dipakai.
    tapi fungsi ini dipakai oleh para pengguna aplikasi tukang.id dibawah versi 4.2 jadi tidak boleh dihapus*/
    function send_broadcast_pushy_final()
    {
        
        $this->load->model('muserlogin_model');
        $broadcast_targets = $this->muserlogin_model->get_broadcast_target3();
        
        
        $broadcast_target_arr = array();
        
        foreach ($broadcast_targets as $broadcast_target) {
            array_push($broadcast_target_arr, $broadcast_target->prid);
        }
        
        if ($broadcast_target_arr != array()) {
            
            $url = base_url() . '/tasks/send_pushy_final';
            
            
            $msg_arr = array(
                'title' => '[Penting] Perbarui aplikasi Anda',
                'msg' => 'Dear Pelanggan, mohon update supaya tetap bisa memakai Tukang.id, versi ini tidak akan bs dipakai lg, mulai jam 12 siang hari ini. Segera perbarui aplikasi anda di Playstore (tukang.id air & gas)',
                'channelDest' => PARSE_CHANNEL,
                'pushKind' => '100',
                'imgUrl' => '',
                'imgUrlIcon' => 'http://tukang-backend.com/icon_notif_broadcast_tukangid.png',
                'priorityLevel' => 0,
                'expiredOn' => (intval(microtime(true) * 1000) + 90000000)
            );
            
            $msg_str = json_encode($msg_arr);
            
            // Set post variables
            $post = array(
                'validator' => 'afe13Rg78#*Agy',
                'msg' => $msg_str,
                'target' => PARSE_CHANNEL,
                'identifiers' => json_encode($broadcast_target_arr) //$broadcast_target->prid
                    ,
                'web_broadcast' => '1'
                // , 'is_broadcast' => 1
            );
            
            // Initialize curl handle
            $ch = curl_init();
            
            // Set URL to Pushy endpoint
            curl_setopt($ch, CURLOPT_URL, $url);
            
            // Set request method to POST
            curl_setopt($ch, CURLOPT_POST, true);
            
            // Get the response back as string instead of printing it
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            // Set post data as JSON
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            
            // Actually send the push
            $result = curl_exec($ch);
            
            // Display errors
            if (curl_errno($ch)) {
                echo curl_error($ch);
            }
            
            echo json_encode($result) . "*" . json_encode($broadcast_target) . "*" . json_encode($post);
            
            // Close curl handle
            curl_close($ch);
            // }
        }
    }
    
    function send_broadcast()
    {
        
        $this->load->model('muserlogin_model');
        $broadcast_targets = $this->muserlogin_model->get_broadcast_target3();
        
        if ($broadcast_targets != array()) {
            foreach ($broadcast_targets as $broadcast_target) {
                $url = base_url() . '/tasks3/send_notif';
                
                
                
                $msg_arr = array(
                    'title' => 'Kehabisan Air dan Gas?',
                    'msg' => 'segera pesan di tukang.id paling praktis dan ekonomis',
                    'channelDest' => PARSE_CHANNEL,
                    'pushKind' => '100',
                    'imgUrl' => '',
                    'imgUrlIcon' => 'http://tukang-backend.com/icon_notif_broadcast_tukangid.png',
                    'priorityLevel' => 0,
                    'expiredOn' => (intval(microtime(true) * 1000) + 90000000)
                );
                
                $msg_str = json_encode($msg_arr);
                
                // Set post variables
                $post = array(
                    'validator' => 'afe13Rg78#*Agy',
                    'msg' => $msg_str,
                    'target' => PARSE_CHANNEL,
                    'identifier' => $broadcast_target->userId,
                    'web_broadcast' => '1'
                    // , 'is_broadcast' => 1
                );
                
                // Initialize curl handle
                $ch = curl_init();
                
                // Set URL to Pushy endpoint
                curl_setopt($ch, CURLOPT_URL, $url);
                
                // Set request method to POST
                curl_setopt($ch, CURLOPT_POST, true);
                
                // Get the response back as string instead of printing it
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                
                // Set post data as JSON
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
                
                // Actually send the push
                $result = curl_exec($ch);
                
                // Display errors
                if (curl_errno($ch)) {
                    echo curl_error($ch);
                }
                
                echo json_encode($result) . "#" . $broadcast_target->userId . "#" . json_encode($post);
                
                // Close curl handle
                curl_close($ch);
            }
        }
    }
    
    public function page_download()
    {
        redirect('market://details?id=id.tukang');
    }
    
    /*fungsi untuk mengecek versi aplikasi tukang.id yg disarankan oleh programmer.
    jika versi yg disarankan berakhiran "s", maka user diwajibkan update ke versi tersebut. contoh: 4.7s */
    public function get_init()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $this->load->model('tversion_model');
        $result = $this->tversion_model->get(); //mendapatkan versi yg disarankan.
        echo json_encode($result);
    }
    
    public function get_init_use_param()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $which = $this->input->post('which');
        
        $this->load->model('tversion_model');
        $result = $this->tversion_model->get($which);
        echo json_encode($result);
    }
    
    //fungsi untuk mengubah password user. dipakai oleh aplikasi customer, master+, dan worker.
    public function change_pass()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $userId = $this->input->post('userId');
        $token  = $this->input->post('token');
        
        $userType = 0;
        if (isset($_POST['userType']))
            $userType = $this->input->post('userType');
        
        $this->token_validate($userId, $token, $userType); //jika parameter userType dikirim dari aplikasi, maka validasi token.
        
        $uname    = $this->input->post('uname');
        $old_pass = $this->input->post('old_pass');
        $new_pass = $this->input->post('new_pass');
        $who      = $this->input->post('actor'); //actor ada 3 macam: master, customer & worker
        
        $salt = get_salt(); //mendapatkan salt dari fungsi get_salt di mine_helper.
        
        if ($old_pass != "") {
            $old_pass = $salt->prefix . $old_pass . $salt->suffix;
        }
        
        if ($new_pass != "") {
            $salt     = get_salt();
            $new_pass = $salt->prefix . $new_pass . $salt->suffix;
        }
        
        $param           = new stdClass();
        $param->uname    = $uname;
        $param->old_pass = $old_pass;
        $param->new_pass = $new_pass;
        
        if ($who == 'master') {
            $this->load->model('muserlogin_vendor_model');
            $response = $this->muserlogin_vendor_model->do_change_pass($param);
        } else if ($who == 'customer') {
            $this->load->model('muserlogin_model');
            $response = $this->muserlogin_model->do_change_pass($param);
        } else if ($who == 'worker') {
            $this->load->model('muserlogin_worker_model');
            $response = $this->muserlogin_worker_model->do_change_pass($param);
        }
        
        $result = new stdClass();
        
        if ($response->stat == 1) {
            $result->stat = 1; //success
        } else if ($response->stat == 0) {
            $result->stat = 2; //affected_rows = 0;
        } else if ($response->stat == 2) {
            $result->stat = 3; //old password is wrong
        }
        
        echo json_encode($result);
    }
    
    //fungsi untuk login ke aplikasi tukang.id customer
    public function login()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $param = new stdClass();
        
        $pass = $this->input->post('pass');
        
        if ($pass != "") {
            $salt = get_salt(); //mendapatkan salt dari fungsi get_salt di mine_helper.
            $pass = $salt->prefix . $pass . $salt->suffix;
        }
        
        if ($this->input->post('pass') == "") {
            $pass = "";
        }
        
        list($usec, $sec) = explode(" ", microtime());
        $vcode = substr(strval(intval($usec * 1000000)), -4); //membuat kode verifikasi 4 angka
        
        $param->uname     = $this->input->post('uname');
        $param->email     = trim($this->input->post('email'));
        $param->pass      = $pass;
        $param->firstName = $this->input->post('firstName');
        $param->lastName  = $this->input->post('lastName');
        $param->phone     = $this->input->post('phone');
        $param->isFB      = $this->input->post('isFB');
        $param->vcode     = $vcode;
        
        $callFrom = $this->input->post('callFrom');
        
        $this->load->model('muserlogin_model');
        
        $response = $this->muserlogin_model->do_login($param->email, $param->pass, $param->isFB);
        
        $result = new stdClass();
        
        $result->stat = $response->stat;
        
        $trace = "0";
        
        if ($response->stat == "query_err") {
        } else if ($response->stat == "exists") {
            $result = $response;
            
            if (isset($_POST['oneSignalUserId'])) {
                $params                  = new stdClass();
                $params->userId          = $response->result5;
                $params->oneSignalUserId = $_POST['oneSignalUserId'];
                $params->userKind        = $this->get_onesignal_user(ONESIGNAL_USER_CUST_TEST, ONESIGNAL_USER_CUST);
                
                $this->register_one_signal($params); //fungsi ini ada di MY_Controller.php. gunanya untuk menyimpan id onesignal milik user ke database
            }
            
            if (isset($_POST['firebaseToken']) && trim($_POST['firebaseToken']) != "") {
                $trace .= "c";
                $params                = new stdClass();
                $params->userId        = $result->result5;
                $params->firebaseToken = $_POST['firebaseToken'];
                $params->userKind      = $this->get_firebase_user(FIREBASE_USER_CUST_TEST, FIREBASE_USER_CUST);
                $params->isAndroid     = isset($_POST['isAndroid']) ? $_POST['isAndroid'] : 0;
                
                write_file('./uploads/logs/register_firebase_log.txt', "1-- " . date("Y-m-d H:i:s a") . " fields: " . json_encode($params) . PHP_EOL . PHP_EOL, 'a+');
                
                $this->register_firebase($params); //fungsi ini ada di MY_Controller.php. gunanya untuk menyimpan token firebase milik user ke database
            } else {
                $trace .= "d";
            }
            
            if ($response->result6 == 0) {
                $param->phone = $this->prepare_phone($response->result4);
                $param->vcode = $response->result7;
                
                //mengirim sms ke user jika ini adalah pertama kalinya user login
                $result->vresult = $this->send_vsms($this->prepare_phone($param->phone), 'Kode TukangID Anda adalah ' . $param->vcode, false);
            }
            
            $this->save_device_detail('login_signup', $response->result5);
            
        } else if ($response->stat == "should_register") {
            
            if ($param->phone != FALSE)
                $param->phone = $this->prepare_phone($param->phone); //memformat nomor hape
            else
                $param->phone = "0";
            
            if ($this->muserlogin_model->find_by_phone($param->phone) == array() //jika nomor hape user tidak terdaftar di database
                ) {
                $trace .= "1";
                
                //jika API ini dipanggil dari halaman login di menu depan
                if ($callFrom == CALL_FROM_FRONTPAGE) {
                    $trace .= "2";
                    if ($param->isFB == 1) { //jika login pakai FB
                        $trace .= "3";
                        
                        $insert_id = $this->muserlogin_model->register($param);
                        if ($insert_id < 1) {
                            $trace .= "4";
                            $result->stat = "register_failed";
                        } else {
                            $trace .= "5";
                            $result       = $this->muserlogin_model->do_login($param->email, $param->pass, $param->isFB);
                            $result->stat = "register_success";
                            
                            if (isset($_POST['oneSignalUserId'])) {
                                $params                  = new stdClass();
                                $params->userId          = $result->result5;
                                $params->oneSignalUserId = $this->input->post('oneSignalUserId');
                                $params->userKind        = $this->get_onesignal_user(ONESIGNAL_USER_CUST_TEST, ONESIGNAL_USER_CUST);
                                // $params->check_exists = 1; //penjelasan: http://tukang-backend.com/v2/dokumentasi/rule_user_tukang.id.pdf
                                
                                $is_success = $this->register_one_signal($params);
                                if (!$is_success) {
                                    $result->stat = "register_failed";
                                }
                            }
                            
                            if (isset($_POST['firebaseToken']) && trim($_POST['firebaseToken']) != "") {
                                $params                = new stdClass();
                                $params->userId        = $result->result5;
                                $params->firebaseToken = $this->input->post('firebaseToken');
                                $params->userKind      = $this->get_firebase_user(FIREBASE_USER_CUST_TEST, FIREBASE_USER_CUST);
                                // $params->check_exists = 1;
                                $params->isAndroid     = isset($_POST['isAndroid']) ? $_POST['isAndroid'] : 0;
                                
                                write_file('./uploads/logs/register_firebase_log.txt', "2-- " . date("Y-m-d H:i:s a") . " fields: " . json_encode($params) . PHP_EOL . PHP_EOL, 'a+');
                                
                                $is_success = $this->register_firebase($params);
                                if (!$is_success) {
                                    $result->stat = "register_failed";
                                }
                            }
                            
                            $this->save_device_detail('login_signup', $result->result5);
                        }
                    } else {
                        $trace .= "6";
                        $result->stat = "not_exists";
                    }
                } else { //jika API ini dipanggil dari halaman login yg tampil setelah halaman utama / halaman pilih peta
                    $trace .= "7";
                    $insert_id = $this->muserlogin_model->register($param); //daftarkan user ke database
                    if ($insert_id < 1) {
                        $trace .= "8";
                        $result->stat = "register_failed";
                    } else { //jika user berhasil didaftarkan ke database
                        $trace .= "9";
                        
                        //user akan login secara otomatis setelah didaftarkan
                        $result       = $this->muserlogin_model->do_login($param->email, $param->pass, $param->isFB);
                        $result->stat = "register_success";
                        
                        if (isset($_POST['oneSignalUserId'])) {
                            $params                  = new stdClass();
                            $params->userId          = $result->result5;
                            $params->oneSignalUserId = $this->input->post('oneSignalUserId');
                            $params->userKind        = $this->get_onesignal_user(ONESIGNAL_USER_CUST_TEST, ONESIGNAL_USER_CUST);
                            // $params->check_exists = 1; //penjelasan: http://tukang-backend.com/v2/dokumentasi/rule_user_tukang.id.pdf
                            
                            $is_success = $this->register_one_signal($params);
                            if (!$is_success) {
                                $result->stat = "register_failed";
                                // $this->muserlogin_model->delete_user($result->result5);
                            }
                        }
                        
                        if (isset($_POST['firebaseToken']) && trim($_POST['firebaseToken']) != "") {
                            $params                = new stdClass();
                            $params->userId        = $result->result5;
                            $params->firebaseToken = $this->input->post('firebaseToken');
                            $params->userKind      = $this->get_firebase_user(FIREBASE_USER_CUST_TEST, FIREBASE_USER_CUST);
                            // $params->check_exists = 1;
                            $params->isAndroid     = isset($_POST['isAndroid']) ? $_POST['isAndroid'] : 0;
                            
                            write_file('./uploads/logs/register_firebase_log.txt', "3-- " . date("Y-m-d H:i:s a") . " fields: " . json_encode($params) . PHP_EOL . PHP_EOL, 'a+');
                            
                            $is_success = $this->register_firebase($params);
                            if (!$is_success) {
                                $result->stat = "register_failed";
                                // $this->muserlogin_model->delete_user($result->result5);
                            }
                        }
                        
                        if ($param->phone != "0" && $result->stat == "register_success") { //jika registrasi sukses, sms verifikasi akan dikirimkan
                            $result->vresult = $this->send_vsms($this->prepare_phone($param->phone), 'Kode TukangID Anda adalah ' . $vcode, false);
                            /*
                            $indentifir = $param->email;
                            $title = "Username dan Password Tukang.id";
                            $message = "
                            Hi Pelanggan Tukang.id,
                            Terima kasih sudah menggunakan aplikasi Tukang.id Air & Gas, berikut adalah username dan password anda :
                            username : ". $param->firstName . "
                            password : ". $pass . "
                            Harap simpan email ini, sebagai pengingat apabila anda lupa username atau password
                            Salam,
                            Team Tukang.id";
                            sendMailPHPMailer($indentifir,$title,$message);
                            */
                        }
                        
                        $this->save_device_detail('login_signup', $result->result5);
                    }
                }
            } else { //jika nomor hape user sudah terdaftar tapi email & password user salah, tampilkan pesan error
                $trace .= "a";
                $result->stat = "register_failed";
                if (isset($_POST['version_code'])) {
                    $trace .= "b." . $param->phone;
                    $result->stat = "phone_number_registered";
                }
            }
        }
        
        $result->trace = $trace; //trace ini untuk tujuan debugging / mencari error saja
        
        echo json_encode($result);
    }
    
    //fungsi untuk login ke web backend (http://tukang-backend.com/v2/tasks/login_admin)
    public function login_admin()
    {
        $this->load->view('admin_login_view');
    }
    
    //fungsi untuk login ke aplikasi master+
    public function login_masterplus()
    {
        
        $pass_ori = $this->input->post('pass');
        
        $initiator = "android";
        
        if (!isset($_POST['initiator'])) {
            if (!isset($_POST['validator'])) {
                exit("No direct script access allowed");
            }
        }
        
        if (isset($_POST['initiator'])) {
            if ($_POST['passval'] !== '1237#$#$&*(aQwe')
                exit("No direct script access allowed");
            
            $pass_ori  = "*^#@@" . substr($pass_ori, 5, 6) . "*()(";
            $initiator = $_POST['initiator'];
        }
        
        $param = new stdClass();
        
        $salt = get_salt();
        $pass = $salt->prefix . $pass_ori . $salt->suffix;
        
        if ($this->input->post('pass') == "") {
            $pass = "";
        }
        
        $param->identifier = $this->input->post('identifier');
        $param->pass       = $pass;
        // $param->pushyRegId = $this->input->post('pushyRegId');
        
        $this->load->model('muserlogin_vendor_model');
        
        $response = $this->muserlogin_vendor_model->do_login_masterplus($param->identifier, $param->pass, "" //$param->pushyRegId
            , 1, $initiator);
        
        $result       = new stdClass();
        $result->stat = $response->stat;
        
        if ($response->stat == "query_err") {
        } else if ($response->stat == "exists") {
            $this->session->set_userdata('user_sess', $param->identifier);
            $result = $response;
            
            if (isset($_POST['oneSignalUserId'])) {
                $params                  = new stdClass();
                $params->userId          = $response->result5;
                $params->oneSignalUserId = $this->input->post('oneSignalUserId');
                $params->userKind        = $this->get_onesignal_user(ONESIGNAL_USER_MASTERPLUS_TEST, ONESIGNAL_USER_MASTERPLUS);
                
                $this->register_one_signal($params);
            }
            
            if (isset($_POST['firebaseToken']) && trim($_POST['firebaseToken']) != "") {
                $params                = new stdClass();
                $params->userId        = $result->result5;
                $params->firebaseToken = $_POST['firebaseToken'];
                $params->userKind      = $this->get_firebase_user(FIREBASE_USER_MASTERPLUS_TEST, FIREBASE_USER_MASTERPLUS);
                $params->isAndroid     = isset($_POST['isAndroid']) ? $_POST['isAndroid'] : 0;
                
                // write_file('./uploads/logs/register_firebase_log.txt', "4-- ".date("Y-m-d H:i:s a")." fields: ". json_encode($params) . PHP_EOL . PHP_EOL ,'a+');
                
                $this->register_firebase($params); //fungsi ini ada di MY_Controller.php. gunanya untuk menyimpan token firebase milik user ke database
            }
        } else if ($response->stat == "should_register") {
            $result->stat = "should_register";
        }
        
        echo json_encode($result);
    }
    
    
    
    //fungsi untuk membatalkan orderan
    public function cancel_booking()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $userId = $this->input->post('userId');
        $token  = $this->input->post('token');
        
        $this->token_validate($userId, $token);
        
        $docId = $this->input->post('docId');
        
        $result        = new stdClass();
        $result->stat  = 0;
        $result->doc   = array();
        $result->docId = $docId;
        
        $this->load->model('torderdoc_model');
        
        //dapatkan status orderan.
        $status = $this->torderdoc_model->get_status(array(
            $docId
        ));
        
        $result->stat_before = $status;
        
        //kalau orderan belum diantar, maka bisa dicancel
        if (intval($status) < 3) {
            $r = $this->torderdoc_model->update_status(array(
                -1,
                $docId
            ));
            
            if ($r) {
                //hapus (nonaktifkan) data diskon yg terkait orderan
                $this->load->model('tcustdiscount_model');
                $this->tcustdiscount_model->delete($docId);
                
                //hapus (nonaktifkan) data deposit customer yg terkait orderan
                $this->load->model('tcustdeposit_model');
                $this->tcustdeposit_model->delete($docId);
                
                //hapus (nonaktifkan) data deposit vendor yg terkait orderan
                $this->load->model('tvendordeposit_model');
                $this->tvendordeposit_model->delete($docId);
                
                $result->stat = 1;
            }
            
            $result->doc = $this->get_one_doc($docId);
        } else {
            $result->stat = 2;
        }
        
        echo json_encode($result);
    }
    
    //fungsi untuk mengecek apakah user diblok. parameter: userId dari user yg dicek.
    public function is_blocked($userId)
    {
        $this->load->model('tblocked_users_model');
        $blocked_users_arr = $this->tblocked_users_model->get_all();
        $found             = false;
        foreach ($blocked_users_arr as $user) {
            if ($user['userId'] == $userId) {
                $found = true;
                break;
            }
        }
        
        return $found;
    }
    
    //fungsi untuk menyimpan orderan ke database tukang.id
    public function save_trans()
    {
        
        // $this->load->helper('file');
        // write_file('./uploads/worker/save_trans_log.php', "post trans: ".json_encode($_POST).PHP_EOL.PHP_EOL,'a+');
        
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $userId = $this->input->post('userId');
        $token  = $this->input->post('token');
        
        
        $this->token_validate($userId, $token);
        
        if (isset($_POST['firebaseToken']) && trim($_POST['firebaseToken']) != "") {
            $params                = new stdClass();
            $params->userId        = $userId;
            $params->firebaseToken = $_POST['firebaseToken'];
            $params->userKind      = $this->get_firebase_user(FIREBASE_USER_CUST_TEST, FIREBASE_USER_CUST);
            $params->isAndroid     = ($_POST['isIos'] == 1) ? 0 : 1;
            
            $this->register_firebase($params); //fungsi ini ada di MY_Controller.php. gunanya untuk menyimpan token firebase milik user ke database
        }
        
        $result                  = new stdClass();
        $result->stat_save_trans = "1";
        $result->docId           = 0;
        
        $this->load->model('torderdoc_model');
        
        $userId = $this->get_user_id_by_email($this->input->post('userEmail'));
        
        $total_item_today = $this->torderdoc_model->get_total_item_today($userId); //mencari tahu, berapa item yg sudah dibeli user hari ini
        
        $this->load->model('muserlogin_model');
        $result->preferredVendorTarget = $this->muserlogin_model->get_preferred_vendor($userId); //mencari username preferred vendor
        
        $is_verified = $this->muserlogin_model->get_is_verified($userId); //mencari apakah user sudah tervalidasi (pakai sms)
        
        $hour_now = date("G");
        
        $timeId = intval($this->input->post('timeId'));
        
        // jika terjadi error di aplikasi (kemungkinan ios) & timeId nya 0, ini bisa mengisi timeId dengan waktu terdekat sekarang.
        // update 23 maret 2017, ada error di android yg menyebabkan timeId = -1. utk quickfix maka diubah kondisinya
        // jadi if($timeId <= 0)
        if ($timeId <= 0) {
            $this->load->model('mtime_model');
            
            $timeStartAndTimeEnd = $this->calculateTimeStartAndTimeEnd(1);
            $timeId              = $this->mtime_model->findIdByValNumber($timeStartAndTimeEnd->timeStart);
        }
        
        $versionCode = 1;
        if (isset($_POST['versionCode'])) {
            $versionCode = $_POST['versionCode'];
        }
        
        $last_order_obj      = $this->torderdoc_model->get_last_order_status($userId);
        $result->last_doc_id = $last_order_obj->orderId;
        
        $forceOrderEvenIfLastOrderStatusIsZero = false;
        if (isset($_POST['forceOrderEvenIfLastOrderStatusIsZero'])) {
            $forceOrderEvenIfLastOrderStatusIsZero = ($_POST['forceOrderEvenIfLastOrderStatusIsZero'] == "1");
        }
        
        if ($total_item_today >= 18 && $userId != 2137 //2137 = userId dari customer service (cstukang@gmail.com). supaya customer service bisa memesankan lebih dari kuota per hari untuk customer
            && $userId != 6449 //6449 = userid pak syaiful (syaiful.ipul@gmail.com). supaya bisa memesankan lebih dari kuota per hari untuk customer
            && $userId != 18 // 18 = userid pak daniel
            && $userId != 137 // 137 = userid meidika
            ) {
            $result->testTransactionTarget = "";
            $result->stat_save_trans       = "Mohon maaf, maksimum orderan 18 item sehari";
        } else if (!$is_verified) {
            $result->testTransactionTarget = "";
            $result->stat_save_trans       = "Akun belum terverifikasi. Mohon logout lalu login lagi";
        } else if ($this->is_blocked($userId) //mengecek apakah user diblok
            ) {
            $result->testTransactionTarget = "";
            $result->stat_save_trans       = "terjadi error pada aplikasi";
        }
        
        else if ($this->torderdoc_model->get_orderdate($this->input->post('orderDateUTS')) == "2016-12-26") {
            $result->testTransactionTarget = "";
            $result->stat_save_trans       = "Mohon maaf kami libur tanggal 26 desember";
        } else if ($this->torderdoc_model->get_orderdate($this->input->post('orderDateUTS')) == "2017-01-01") {
            $result->testTransactionTarget = "";
            $result->stat_save_trans       = "Mohon maaf kami tidak melayani pesanan hari ini. Selamat hari libur Tahun Baru";
        } else if ($this->torderdoc_model->get_orderdate($this->input->post('orderDateUTS')) == "2017-01-02") {
            $result->testTransactionTarget = "";
            $result->stat_save_trans       = "Mohon maaf kami tidak melayani pesanan hari ini. Selamat hari libur";
        } else if ($this->torderdoc_model->get_orderdate($this->input->post('orderDateUTS')) == "2017-02-15") {
            $result->testTransactionTarget = "";
            $result->stat_save_trans       = "Mohon maaf kami tidak melayani pesanan tanggal 15 Februari 2017. Selamat melangsungkan Pilkada";
        } else if ($last_order_obj->status == 0 && intval($versionCode) > 115 && !$forceOrderEvenIfLastOrderStatusIsZero) {
            $result->testTransactionTarget = "";
            $result->stat_save_trans       = "last_order_status_is_0";
        } else {
            
            $notes = $this->input->post('notes');
            
            $vendorsByRanking = "";
            $candidateVendors = "";
            
            if (isset($_POST['candidateVendors'])) {
                // convert balikkan dari apps 
                $candidateVendors = json_decode($_POST['candidateVendors']);
                // $replaceData = str_replace('[\"', "'", $candidateVendors);
                
                foreach ($candidateVendors as $key => $value) {
                    $dataCoolection[] = "'" . $value . "'";
                }
                $toString = implode(",", $dataCoolection);
                
                
                // handle backward compatibility current version and new version custom vendor
                if (isset($_POST['isCustomVendor'])) {
                    $vendorsByRanking = $toString;
                } else {
                    $vendorsByRanking = $this->getVendorsByRanking($_POST['candidateVendors'], '');
                }
                $candidateVendors = $_POST['candidateVendors'];
            }
            
            $preferredVendor = "";
            if (isset($_POST['preferredVendor'])) {
                $preferredVendor = $_POST['preferredVendor'];
            }
            
            
            $isIos = 0; //untuk menandakan apakah pesanan dari aplikasi ios atau android
            
            if (isset($_POST['isIos'])) {
                if ($_POST['isIos'] == 1)
                    $isIos = 1;
            }
            
            $arr = array(
                intval($this->input->post('catId')),
                $this->get_user_id_by_email($this->input->post('userEmail')),
                $this->input->post('firstName'),
                $this->input->post('lastName'),
                $this->input->post('phone'),
                $this->input->post('orderDateUTS'),
                $timeId,
                intval($this->input->post('addressId')),
                floatval($this->input->post('lat')),
                floatval($this->input->post('lng')),
                $this->input->post('streetAddress'),
                $this->input->post('freqId') //saat ini belum dipakai (update 11 nopember 2016)
                    ,
                $this->input->post('totalPayment'),
                $this->input->post('payWith'),
                $this->input->post('creditCardNumber') //saat ini belum dipakai (update 11 nopember 2016)
                    ,
                $this->input->post('kec'),
                $this->input->post('city'),
                $notes,
                str_replace("\"", "'", $vendorsByRanking) //daftar vendor yang rankingnya tertinggi di lokasi user.
                    ,
                $preferredVendor,
                $candidateVendors //daftar vendor yg ada di lokasi user.
                    ,
                $isIos
            );
            
            $firstName = $this->input->post('firstName');
            
            $notTerimaKasih = true;
            
            //get counter value for gas 3kg in a day
            if ($this->input->post('isOrderGas3kg')) {
                // $total_item_gas3kg_today = $this->torderdoc_model->get_total_item_gas3kg_today($userId);
                $total_item_gas3kg_today = $this->torderdoc_model->get_total_item_gas3kg_onDate_unix($userId, $this->input->post('orderDateUTS'));
                
                if ($total_item_gas3kg_today >= 2 && $userId != 2137 //2137 = userId dari customer service (cstukang@gmail.com). supaya customer service bisa memesankan lebih dari kuota per hari untuk customer
                    && $userId != 6449 //6449 = userid pak syaiful (syaiful.ipul@gmail.com). supaya bisa memesankan lebih dari kuota per hari untuk customer
                    ) {
                    $result->testTransactionTarget = "";
                    $result->stat_save_trans       = "Mohon maaf maksimum order untuk LPG 3kg dibatasi 2 tabung dalam sehari";
                    $notTerimaKasih                = false;
                }
            }
            
            if (stripos($firstName, "terima kasih") !== false) {
                $notTerimaKasih                = false;
                $result->testTransactionTarget = "";
                $result->stat_save_trans       = "terjadi error pada aplikasi";
            }
            
            if ($notTerimaKasih) {
                $doc_id = $this->torderdoc_model->insert($arr);
                
                if ($doc_id <= 0) {
                    $result->testTransactionTarget = "";
                    $result->stat_save_trans       = "gagal melakukan order";
                } else {
                    $result->docId = $doc_id;
                    
                    $result->vendorsByRanking = $vendorsByRanking;
                    
                    $this->load->model('tordertrans_model');
                    
                    $allSaved = true;
                    
                    $jsonTransList = json_decode($this->input->post('transList'));
                    
                    foreach ($jsonTransList->trans as $row) {
                        if ($allSaved) {
                            if (isset($_POST['isIos'])) {
                                $trans_id = $this->tordertrans_model->insert_with_productid(array(
                                    $doc_id,
                                    $row->productId,
                                    $row->qty,
                                    $row->price,
                                    $row->subTotal
                                ));
                            } else {
                                $trans_id = $this->tordertrans_model->insert(array(
                                    $doc_id,
                                    $row->catId,
                                    $row->unitId,
                                    $row->brandId,
                                    $row->qty,
                                    $row->price,
                                    $row->subTotal
                                ));
                            }
                            
                            /*
                            $trans_id = $this->tordertrans_model->insert_with_productid(
                            array($doc_id
                            , $row->productId
                            , $row->qty
                            , $row->price
                            , $row->subTotal
                            )
                            );
                            */
                            
                            if ($trans_id == 0)
                                $allSaved = false;
                        }
                    }
                    
                    if (!$allSaved) {
                        $this->torderdoc_model->delete($doc_id);
                        $result->testTransactionTarget = "";
                        $result->stat_save_trans       = "gagal melakukan order. orderan dihapus";
                    }
                    
                    if ($allSaved) {
                        $this->load->model('tcustdeposit_model');
                        if (isset($_POST['discountKind'])) {
                            if ($this->input->post('discountKind') != DISCOUNT_KIND_NONE) { //jika user memakai kode voucher
                                $this->load->model('tcustdiscount_model');
                                if ($this->input->post('discountKind') == DISCOUNT_KIND_VOUCHER) { //jika kode voucher berupa kode voucher discount (ada di tabel tvoucher)
                                    $this->tcustdiscount_model->insert(array(
                                        $this->input->post('discountKind'),
                                        $this->get_user_id_by_email($this->input->post('userEmail')),
                                        $doc_id,
                                        $this->input->post('voucherId'),
                                        0,
                                        $this->input->post('voucherCode'),
                                        $this->input->post('voucherAmount')
                                    ));
                                } else if ($this->input->post('discountKind') == DISCOUNT_KIND_REFERRAL_CUST) { //jika kode voucher berupa kode referral cust
                                    $referral_row = $this->muserlogin_model->find_referral($this->input->post('voucherCode'));
                                    
                                    // tambahkan potongan harga untuk user yang memakai kode referral
                                    $this->tcustdiscount_model->insert(array(
                                        $this->input->post('discountKind'),
                                        $this->get_user_id_by_email($this->input->post('userEmail')),
                                        $doc_id,
                                        $this->input->post('voucherId'),
                                        $referral_row->id,
                                        $this->input->post('voucherCode'),
                                        $this->input->post('voucherAmount')
                                    ));
                                    
                                    // tambahkan deposit untuk user yang menyebarkan kode referral
                                    $this->tcustdeposit_model->insert(array(
                                        $referral_row->id,
                                        $doc_id,
                                        $this->input->post('voucherCode'),
                                        $this->input->post('voucherAmount')
                                    ));
                                } else if ($this->input->post('discountKind') == DISCOUNT_KIND_REFERRAL_VENDOR) { //jika kode voucher berupa kode referral vendor
                                    $this->load->model('muserlogin_vendor_model');
                                    $referral_vendor_row = $this->muserlogin_vendor_model->find_referral($this->input->post('voucherCode'));
                                    
                                    // tambahkan potongan harga untuk user yang memakai kode referral
                                    $this->tcustdiscount_model->insert(array(
                                        $this->input->post('discountKind'),
                                        $userId,
                                        $doc_id,
                                        $this->input->post('voucherId'),
                                        $referral_vendor_row->id,
                                        $this->input->post('voucherCode'),
                                        $this->input->post('voucherAmount')
                                    ));
                                    
                                    //update id preferred vendor untuk user yg memakai kode referral
                                    $this->muserlogin_model->update_preferred_vendor_id($userId, $referral_vendor_row->id);
                                    
                                    //cari besarnya upah untuk vendor yg menyebarkan kode referral
                                    $this->load->model('treferral_vendor_model');
                                    $amountForVendor = $this->treferral_vendor_model->getAmountForVendor()->amountForVendor;
                                    
                                    $this->load->model('tvendordeposit_model');
                                    
                                    // cari apakah vendor yg menyebarkan kode referral sudah mendapat deposit setelah menyebarkan kode referral
                                    if ($this->tvendordeposit_model->find(array(
                                        $referral_vendor_row->id,
                                        $doc_id,
                                        $this->input->post('voucherCode')
                                    )) == 0) {
                                        //kalau vendor blm dpt deposit, tambahkan deposit untuk vendor
                                        $this->tvendordeposit_model->insert(array(
                                            $referral_vendor_row->id,
                                            $doc_id,
                                            $this->input->post('voucherCode'),
                                            $amountForVendor
                                        ));
                                    }
                                }
                            }
                        }
                        
                        $this->load->model('ttestnotes_model');
                        $to_vendor                     = $this->ttestnotes_model->find($notes);
                        $result->testTransactionTarget = $to_vendor;
                        
                        //kalau user memakai kode deposit untuk memesan, maka kurangi depositnya.
                        if (isset($_POST['depositAmount'])) {
                            if (intval($_POST['depositAmount']) > 0) {
                                $this->tcustdeposit_model->insert_minus(array(
                                    $this->get_user_id_by_email($this->input->post('userEmail')),
                                    $doc_id,
                                    -(intval($this->input->post('depositAmount')))
                                ));
                            }
                        }
                        
                        $result->trans = $this->tordertrans_model->get2($doc_id);
                        
                        $this->save_device_detail('save_trans', $userId, $doc_id);
                    }
                }
            }
        }
        
        if (isset($result->stat_save_trans)) {
            $result->stat = $result->stat_save_trans;
        }
        
        write_file('./uploads/logs/save_trans_log.php', "result save trans: " . json_encode($result) . PHP_EOL . PHP_EOL, 'a+');
        
        echo json_encode($result);
    }
    
    // fungsi untuk handling timeout order per 15 menit
    public function autodestroy_order()
    {
        
        // basic validator
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $docId = json_decode($_POST['docId']);
        
        // token validasi
        $userId = $this->input->post('userId');
        $token  = $this->input->post('token');
        $this->token_validate($userId, $token);
        
        // data status
        $data           = array();
        $data['status'] = STATUS_TIMEOUT;
        
        // mengupdate auto destroy status menjadi cancel jika sudah 15 menit.
        $this->load->model('torderdoc_model');
        $updateStatus = $this->torderdoc_model->autodestroy_order($docId, $data);
        
        echo json_encode($updateStatus);
    }
    
    //fungsi untuk mendapatkan id user
    //parameternya adalah email dari user yg mau dicari id nya
    public function get_user_id_by_email($email)
    {
        $this->load->model('muserlogin_model');
        return $this->muserlogin_model->get_userid($email);
    }
    
    //fungsi untuk mendapatkan orderan2 yg sudah selesai (status = 5).
    //dipakai oleh aplikasi tukang.id customer
    public function get_previous_orders()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        //requested_status = 0 => new booking, 1 => accepted, 2 = assigned, 3 = worker go, 4 = worker start, 5 = complete
        
        $userId = $this->input->post('userId');
        $token  = $this->input->post('token');
        
        $this->token_validate($userId, $token);
        
        $this->load->model('torderdoc_model');
        
        $docs = $this->torderdoc_model->getPreviousForCustomer($userId); //query list orderan yg sudah selesai, mencari berdasarkan userid customer
        
        $result = array();
        
        $result = $this->get_orders_detail($docs, true); //mendapatkan detail orderan. fungsi ini ada di core/MY_Controller.php.
        
        echo json_encode($result);
    }
    
    //fungsi untuk mendapatkan orderan2 yg belum selesai (status < 5 & status > -1).
    //dipakai oleh aplikasi tukang.id customer
    public function get_upcoming_orders()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $userId = $this->input->post('userId');
        $token  = $this->input->post('token');
        
        $this->token_validate($userId, $token);
        
        //requested_status = 0 => new booking, 1 => accepted, 2 = assigned, 3 = worker go, 4 = worker start, 5 = complete
        
        $this->load->model('torderdoc_model');
        
        $docs = $this->torderdoc_model->getUpcomingForCustomer($userId); //query list orderan yg belum selesai, mencari berdasarkan userid customer
        
        $result = array();
        
        $result = $this->get_orders_detail($docs, false); //mendapatkan detail orderan. fungsi ini ada di core/MY_Controller.php.
        
        echo json_encode($result);
    }
    
    //fungsi untuk mendapatkan daftar pesanan yg belum selesai
    //saat ini dipakai oleh aplikasi master+ dan aplikasi worker
    public function get_booking()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        //requested_status = 0 => new booking, 1 => accepted, 2 = assigned, 3 = worker go, 4 = worker start, 5 = complete
        $requested_status = $this->uri->segment(3, "-1");
        
        //6 = for booking menu, master+
        
        $master_id = $this->input->post('userId');
        $token     = $this->input->post('token');
        
        // if(!isset($_POST['userId']))
        // $master_id = 16;
        
        $userType = 0;
        if (isset($_POST['userType']))
            $userType = $this->input->post('userType');
        
        $this->token_validate($master_id, $token, $userType);
        
        $this->load->model('torderdoc_model');
        $this->load->model('muserlogin_vendor_model');
        
        $docs = $this->torderdoc_model->get($requested_status, $master_id, $this->muserlogin_vendor_model->get_saber_masterid());
        
        $result = array();
        
        if ($requested_status == 0) {
            $result = $this->get_new_booking($docs, $master_id);
        }
        
        if ($requested_status == 6) { //6 = for booking menu, master+
            $result = $this->get_new_booking_masterplus($docs, $master_id);
        }
        
        echo json_encode($result);
    }
    
    
    
    public function get_booking2()
    {
        
        $master_id = $this->input->post('userId');
        $token     = $this->input->post('token');
        
        $userType = 0;
        if (isset($_POST['userType']))
            $userType = $this->input->post('userType');
        
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $this->token_validate($master_id, $token, $userType);
        
        //requested_status = 0 => new booking, 1 => accepted, 2 = assigned, 3 = worker go, 4 = worker start, 5 = complete
        $requested_status = $this->uri->segment(3, "-1");
        
        $this->load->model('torderdoc_model');
        $this->load->model('muserlogin_vendor_model');
        
        $docs = $this->torderdoc_model->get_new($requested_status, $master_id, $this->muserlogin_vendor_model->get_saber_masterid());
        
        $result = array();
        
        if ($requested_status == 0) {
            $result = $this->get_new_booking($docs, $master_id);
        }
        
        if ($requested_status == 6) { //6 = for booking menu, master+
            $result = $this->get_new_booking_masterplus2($docs, $master_id);
        }
        
        echo json_encode($result);
    }
    
    //fungsi untuk mengecek apakah catatan yg tertera di orderan adalah catatan untuk orderan tes
    //yaitu orderan coba2.
    //mengembalikan $response->should_added. jika true, maka orderan dengan notes ini perlu ditampilkan ke user
    function is_testnote($notes, $vendor_username)
    {
        $this->load->model('ttestnotes_model');
        $to_vendor = $this->ttestnotes_model->find($notes);
        
        $response = new stdClass();
        
        if ($to_vendor == "") {
            $response->should_added = true;
            $response->is_testnote  = 0;
            return $response;
        } else {
            if ($to_vendor == $vendor_username) {
                $response->should_added = true;
                $response->is_testnote  = 1;
                return $response;
            } else {
                $response->should_added = false;
                $response->is_testnote  = 1;
                return $response;
            }
        }
    }
    
    //fungsi untuk mengecek apakah suatu vendor seharusnya bisa melihat suatu orderan
    //berdasarkan kategori orderan & user yg memesan orderan
    //kategori orderan adalah: 1 = AC SERVICE. 2 = CLEANING. 3 = WATER & LPG. 4 = INSTALL AC
    function should_serve($master_id, $catId, $userId = 0)
    {
        
        $this->load->model('muserlogin_vendor_model');
        $cat_arr = $this->muserlogin_vendor_model->get_cat_arr($master_id);
        
        $found = false;
        foreach ($cat_arr as $cat_row) {
            if ($cat_row->catId == $catId && $cat_row->isServe == 1)
                $found = true;
        }
        
        if ($userId == 0) {
            return $found;
        } else if ($userId > 0) {
            if ($found) {
                if ($master_id == 16) {
                    return true;
                } else {
                    $this->load->model('muserlogin_model');
                    return $this->muserlogin_model->is_vendor_can_accept($userId, $master_id);
                }
            } else {
                return $found;
            }
        }
    }
    
    //fungsi untuk mengecek apakah suatu vendor seharusnya bisa melihat suatu orderan
    //berdasarkan productId / id dari product yg dipesan.
    function should_serve2($master_id, $productId)
    {
        $this->load->model('muserlogin_vendor_model');
        $cat_arr = $this->muserlogin_vendor_model->get_cat_arr($master_id);
        
        $found = false;
        
        foreach ($cat_arr as $cat_row) {
            if ($cat_row->catId == CATEGORY_WATER_LPG) {
                $found = in_array($productId, $cat_row->productIds);
            }
        }
        
        return $found;
    }
    
    //fungsi untuk menambahkan data pada data yg mau ditampilkan ke user
    //dipakai oleh aplikasi master+
    function get_new_booking_masterplus($docs, $master_id)
    {
        
        $result = array();
        
        $this->load->model('tordertrans_model');
        $this->load->model('torder_rejected_model');
        $this->load->model('torder_accepted_model');
        $this->load->model('tcustomorder_model');
        $this->load->model('tpending_order_model');
        $this->load->model('tcustdiscount_model');
        $this->load->model('tcustdeposit_model');
        $this->load->model('muserlogin_vendor_model');
        
        $a_vendor            = $this->muserlogin_vendor_model->get_username_canseevendorlist($master_id);
        $vendor_username     = $a_vendor->username;
        $can_see_vendor_list = $a_vendor->canSeeVendorList;
        
        $rejected_arr          = $this->torder_rejected_model->get($master_id);
        $accepted_by_other_arr = $this->torder_accepted_model->get_not($master_id);
        
        $pending_orders = $this->tpending_order_model->get_all();
        
        $pending_table = array();
        
        if ($pending_orders != array()) {
            foreach ($pending_orders as $row) {
                $msg = json_decode($row->msg);
                if (isset($msg->docId))
                    array_push($pending_table, $msg->docId);
            }
        }
        
        foreach ($docs as $doc) {
            $cont = true;
            if (in_array($doc->docId, $rejected_arr)) {
                $cont = false;
            }
            
            if (in_array($doc->docId, $accepted_by_other_arr)) {
                $cont = false;
            }
            
            $testnote_check = $this->is_testnote($doc->notes, $vendor_username);
            if (!$testnote_check->should_added) {
                $cont = false;
                
                // if(!$this->should_serve($master_id,$doc->catId,$doc->userId)){
                // $cont = false;
                // }				
            }
            
            if ($testnote_check->is_testnote == 0) {
                if (!$this->should_serve($master_id, $doc->catId, $doc->userId)) {
                    $cont = false;
                }
            }
            
            if ($master_id != 16)
                if ($doc->notes != TEST_PHRASE)
                    if (trim($doc->preferredVendor) == "")
                        if ($testnote_check->is_testnote == 0)
                            if (trim($doc->vendorsByRanking) != "")
                                if (strpos($doc->vendorsByRanking, $vendor_username) === FALSE)
                                    $cont = false;
            
            
            if ($cont) {
                
                if (trim($doc->candidateVendors) == '' || !$can_see_vendor_list)
                    $doc->candidateVendors = "[]";
                
                $docObject                = new stdClass();
                $docObject->docId         = intval($doc->docId);
                $docObject->catId         = intval($doc->catId);
                $docObject->date          = strval($doc->date);
                $docObject->timeId        = intval($doc->timeId);
                $docObject->city          = $doc->city;
                $docObject->kecamatan     = $doc->kecamatan;
                $docObject->streetAddress = $doc->streetAddress;
                $docObject->custName      = $doc->custName;
                $docObject->phone         = $doc->phone;
                $docObject->custEmail     = $doc->custEmail;
                
                $transes = $this->tordertrans_model->get($doc->docId);
                
                $trans_arr = array();
                
                foreach ($transes as $trans) {
                    if ($doc->catId == CATEGORY_WATER_LPG)
                        if (!$this->should_serve2($master_id, $trans->productId) && $trans->qty > 0)
                            $cont = false;
                    
                    if ($can_see_vendor_list) {
                        if ($doc->candidateVendors != "[]") {
                            $candidateVendors_ja = json_decode($doc->candidateVendors);
                            
                            foreach ($candidateVendors_ja as $a_candidate) {
                                
                                if ($this->muserlogin_vendor_model->get_masterid($a_candidate) != 0) {
                                    $should_serve = $this->should_serve2($this->muserlogin_vendor_model->get_masterid($a_candidate), $trans->productId);
                                } else {
                                    $should_serve = false;
                                }
                                if (!$should_serve) {
                                    $doc->candidateVendors = json_encode(array_reject_value($candidateVendors_ja, $a_candidate));
                                }
                            }
                        }
                    }
                    
                    $productDataIdDua       = $trans->productId;
                    $transObject            = new stdClass();
                    $transObject->catId     = intval($trans->catId);
                    $transObject->unitId    = intval($trans->unitId);
                    $transObject->brandId   = intval($trans->brandId);
                    $transObject->productId = (string) $productDataIdDua; // di update untuk handle custom product vendor
                    $transObject->qty       = intval($trans->qty);
                    $transObject->price     = intval($trans->price);
                    $transObject->subTotal  = intval($trans->subTotal);
                    
                    array_push($trans_arr, $transObject);
                }
                
                if ($cont) {
                    
                    $docObject->transList = $trans_arr;
                    $docObject->status    = $doc->status;
                    
                    $custOrderTotal   = 0;
                    $custom_order_arr = array();
                    if (intval($doc->customOrderStatus) > 2) {
                        $custom_orders = $this->tcustomorder_model->get($doc->docId);
                        foreach ($custom_orders as $custom_order) {
                            
                            // docId,description,qty,unit,price,total from tcustom_order
                            
                            $coObject              = new stdClass();
                            $coObject->docId       = intval($custom_order->docId);
                            $coObject->description = $custom_order->description;
                            $coObject->qty         = intval($custom_order->qty);
                            $coObject->unit        = $custom_order->unit;
                            $coObject->price       = intval($custom_order->price);
                            $coObject->total       = intval($custom_order->total);
                            
                            $custOrderTotal += $coObject->total;
                            
                            array_push($custom_order_arr, $coObject);
                        }
                    }
                    $docObject->custOrderTotal  = $custOrderTotal;
                    $docObject->customOrderList = $custom_order_arr;
                    
                    $worker = new stdClass();
                    
                    if ($doc->status > 0)
                        $worker = $this->torder_accepted_model->getWorker($master_id, $doc->docId);
                    else {
                        $worker->name = "";
                        $worker->id   = 0;
                    }
                    
                    $docObject->workerId   = $worker->id;
                    $docObject->workerName = $worker->name;
                    $docObject->notes      = $doc->notes;
                    $docObject->details    = $doc->details;
                    $docObject->lat        = $doc->lat;
                    $docObject->lng        = $doc->lng;
                    $docObject->isTestNote = $testnote_check->is_testnote;
                    
                    $docObject->isPendingOrder = in_array($doc->docId, $pending_table) ? 1 : 0;
                    
                    $discount_arr = $this->tcustdiscount_model->find_by_doc_id($doc->docId);
                    
                    if ($discount_arr != array()) {
                        $docObject->discountKind  = $discount_arr->discountKind;
                        $docObject->voucherAmount = $discount_arr->amount;
                        $docObject->voucherCode   = $discount_arr->voucherCode;
                    } else {
                        $docObject->discountKind  = DISCOUNT_KIND_NONE;
                        $docObject->voucherAmount = 0;
                        $docObject->voucherCode   = '';
                    }
                    
                    $deposit_arr = $this->tcustdeposit_model->find_by_doc_id($doc->docId, $doc->userId);
                    if ($deposit_arr != array()) {
                        $docObject->depositAmount = $deposit_arr->amount;
                    } else {
                        $docObject->depositAmount = 0;
                    }
                    
                    $docObject->vendorsByRanking = $doc->vendorsByRanking;
                    $docObject->preferredVendor  = $doc->preferredVendor;
                    
                    $docObject->candidateVendors = $doc->candidateVendors;
                    
                    array_push($result, $docObject);
                }
            }
        }
        
        return $result;
    }
    
    /*fungsi untuk menambahkan data pada data yg mau ditampilkan ke user
    dipakai oleh aplikasi master+
    fungsi ini untuk menangani fitur custom vendor */
    function get_new_booking_masterplus2($docs, $master_id)
    {
        
        $result = array();
        
        $this->load->model('tordertrans_model');
        $this->load->model('torder_rejected_model');
        $this->load->model('torder_accepted_model');
        $this->load->model('tcustomorder_model');
        $this->load->model('tpending_order_model');
        $this->load->model('tcustdiscount_model');
        $this->load->model('tcustdeposit_model');
        $this->load->model('muserlogin_vendor_model');
        $this->load->model('tnew_notif_schedule_model');
        
        $a_vendor            = $this->muserlogin_vendor_model->get_username_canseevendorlist($master_id);
        $vendor_username     = $a_vendor->username;
        $can_see_vendor_list = $a_vendor->canSeeVendorList;
        
        $rejected_arr          = $this->torder_rejected_model->get($master_id);
        $accepted_by_other_arr = $this->torder_accepted_model->get_not($master_id);
        
        $pending_orders = $this->tpending_order_model->get_all();
        
        $pending_table = array();
        
        if ($pending_orders != array()) {
            foreach ($pending_orders as $row) {
                $msg = json_decode($row->msg);
                
                if (isset($msg->docId))
                    array_push($pending_table, $msg->docId);
            }
        }
        
        write_file('./uploads/logs/get_new_booking_masterplus_log3.txt', "2-- " . date("Y-m-d H:i:s a") . " docs: " . json_encode($docs) . PHP_EOL . PHP_EOL, 'a+');
        
        foreach ($docs as $doc) {
            $cont = true;
            if (in_array($doc->docId, $rejected_arr)) {
                $cont = false;
            }
            
            if (in_array($doc->docId, $accepted_by_other_arr)) {
                $cont = false;
            }
            
            $testnote_check = $this->is_testnote($doc->notes, $vendor_username);
            if (!$testnote_check->should_added) {
                $cont = false;
                
                // if(!$this->should_serve($master_id,$doc->catId,$doc->userId)){
                // $cont = false;
                // }
            }
            
            if ($testnote_check->is_testnote == 0) {
                if (!$this->should_serve($master_id, $doc->catId, $doc->userId)) {
                    $cont = false;
                }
            }
            
            $version_code = 0;
            if (isset($_POST['version_code'])) {
                $version_code = $_POST['version_code'];
            }
            
            /* sejak adanya fitur custom vendor, vendorsByRanking tidak digunakan untuk filter menampilkan orderan ke vendor
            yg digunakan adalah kolom targetVendor di tabel torderdoc
            jadi pengecekan diawali dengan "apakah targetVendor = ''?"
            karena pemakai apps cust dengan fitur custom vendor targetVendornya selalu != "" */
            if ($doc->targetVendor == "") {
                if ($master_id != 16)
                    if ($doc->notes != TEST_PHRASE)
                        if (trim($doc->preferredVendor) == "")
                            if ($testnote_check->is_testnote == 0)
                                if (trim($doc->vendorsByRanking) != "")
                                    if (strpos($doc->vendorsByRanking, $vendor_username) === FALSE)
                                        $cont = false;
            } else {
                if ($doc->targetVendor != $vendor_username) {
                    $cont = false;
                }
            }
            
            
            if ($cont) {
                
                if (trim($doc->candidateVendors) == '' || !$can_see_vendor_list)
                    $doc->candidateVendors = "[]";
                
                $docObject                = new stdClass();
                $docObject->docId         = intval($doc->docId);
                $docObject->catId         = intval($doc->catId);
                $docObject->date          = strval($doc->date);
                $docObject->timeId        = intval($doc->timeId);
                $docObject->city          = $doc->city;
                $docObject->kecamatan     = $doc->kecamatan;
                $docObject->streetAddress = $doc->streetAddress;
                $docObject->custName      = $doc->custName;
                $docObject->phone         = $doc->phone;
                $docObject->custEmail     = $doc->custEmail;
                
                $transes = $this->tordertrans_model->get($doc->docId);
                
                $trans_arr = array();
                
                foreach ($transes as $trans) {
                    if ($doc->catId == CATEGORY_WATER_LPG)
                        if (!$this->should_serve2($master_id, $trans->productId) && $trans->qty > 0)
                            $cont = false;
                    
                    if ($can_see_vendor_list) {
                        if ($doc->candidateVendors != "[]") {
                            $candidateVendors_ja = json_decode($doc->candidateVendors);
                            
                            foreach ($candidateVendors_ja as $a_candidate) {
                                
                                if ($this->muserlogin_vendor_model->get_masterid($a_candidate) != 0) {
                                    $should_serve = $this->should_serve2($this->muserlogin_vendor_model->get_masterid($a_candidate), $trans->productId);
                                } else {
                                    $should_serve = false;
                                }
                                if (!$should_serve) {
                                    $doc->candidateVendors = json_encode(array_reject_value($candidateVendors_ja, $a_candidate));
                                }
                            }
                        }
                    }
                    
                    $productIdData          = $trans->productId;
                    $transObject            = new stdClass();
                    $transObject->catId     = intval($trans->catId);
                    $transObject->unitId    = intval($trans->unitId);
                    $transObject->brandId   = intval($trans->brandId);
                    $transObject->productId = (string) $productIdData; // di update untuk handle custom product vendor
                    $transObject->qty       = intval($trans->qty);
                    $transObject->price     = intval($trans->price);
                    $transObject->subTotal  = intval($trans->subTotal);
                    
                    array_push($trans_arr, $transObject);
                }
                
                if ($cont) {
                    
                    $docObject->transList = $trans_arr;
                    $docObject->status    = $doc->status;
                    
                    $custOrderTotal   = 0;
                    $custom_order_arr = array();
                    if (intval($doc->customOrderStatus) > 2) {
                        $custom_orders = $this->tcustomorder_model->get($doc->docId);
                        foreach ($custom_orders as $custom_order) {
                            
                            // docId,description,qty,unit,price,total from tcustom_order
                            
                            $coObject              = new stdClass();
                            $coObject->docId       = intval($custom_order->docId);
                            $coObject->description = $custom_order->description;
                            $coObject->qty         = intval($custom_order->qty);
                            $coObject->unit        = $custom_order->unit;
                            $coObject->price       = intval($custom_order->price);
                            $coObject->total       = intval($custom_order->total);
                            
                            $custOrderTotal += $coObject->total;
                            
                            array_push($custom_order_arr, $coObject);
                        }
                    }
                    $docObject->custOrderTotal  = $custOrderTotal;
                    $docObject->customOrderList = $custom_order_arr;
                    
                    $worker = new stdClass();
                    
                    if ($doc->status > 0)
                        $worker = $this->torder_accepted_model->getWorker($master_id, $doc->docId);
                    else {
                        $worker->name = "";
                        $worker->id   = 0;
                    }
                    
                    $docObject->workerId   = $worker->id;
                    $docObject->workerName = $worker->name;
                    $docObject->notes      = $doc->notes;
                    $docObject->details    = $doc->details;
                    $docObject->lat        = $doc->lat;
                    $docObject->lng        = $doc->lng;
                    $docObject->isTestNote = $testnote_check->is_testnote;
                    
                    $docObject->isPendingOrder = in_array($doc->docId, $pending_table) ? 1 : 0;
                    
                    $discount_arr = $this->tcustdiscount_model->find_by_doc_id($doc->docId);
                    
                    if ($discount_arr != array()) {
                        $docObject->discountKind  = $discount_arr->discountKind;
                        $docObject->voucherAmount = $discount_arr->amount;
                        $docObject->voucherCode   = $discount_arr->voucherCode;
                    } else {
                        $docObject->discountKind  = DISCOUNT_KIND_NONE;
                        $docObject->voucherAmount = 0;
                        $docObject->voucherCode   = '';
                    }
                    
                    $deposit_arr = $this->tcustdeposit_model->find_by_doc_id($doc->docId, $doc->userId);
                    if ($deposit_arr != array()) {
                        $docObject->depositAmount = $deposit_arr->amount;
                    } else {
                        $docObject->depositAmount = 0;
                    }
                    
                    $docObject->vendorsByRanking = $doc->vendorsByRanking;
                    $docObject->preferredVendor  = $doc->preferredVendor;
                    
                    $docObject->candidateVendors = $doc->candidateVendors;
                    
                    $docObject->expiredAtUTSMillis = $this->tnew_notif_schedule_model->get_expiredAtUTS($doc->docId, $doc->targetVendorOrder) * 1000;
                    
                    $docObject->expiryTime = $docObject->expiredAtUTSMillis - get_milliseconds();
                    
                    $docObject->targetVendor = $doc->targetVendor;
                    
                    array_push($result, $docObject);
                }
            }
        }
        
        write_file('./uploads/logs/get_new_booking_masterplus_log2.txt', "1-- " . date("Y-m-d H:i:s a") . "#" . json_encode(error_get_last()) . PHP_EOL . PHP_EOL, 'a+');
        
        write_file('./uploads/logs/get_new_booking_masterplus_log2.txt', "2-- " . date("Y-m-d H:i:s a") . " result: " . json_encode($result) . PHP_EOL . PHP_EOL, 'a+');
        
        return $result;
    }
    
    //fungsi untuk menolak orderan. dipakai oleh aplikasi master+
    public function master_reject()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $orderId = $this->input->post('docId');
        
        $masterId = $this->input->post('userId');
        $token    = $this->input->post('token');
        
        $userType = $this->input->post('userType');
        
        $this->token_validate($masterId, $token, $userType);
        
        $this->load->model('torder_rejected_model');
        $insert_id = $this->torder_rejected_model->insert(array(
            $masterId,
            $orderId
        )); //memasukkan data vendor yg menolak pesanan ke tabel torder_rejected
        
        $result       = new stdClass();
        $result->stat = 0;
        $result->msg  = "";
        if (intval($insert_id) > 0) {
            $result->stat = 1;
        } else {
            $result->msg = "failed to reject";
        }
        
        echo json_encode($result);
    }
    
    /*menghapus data vendor yg menolak mengirim orderan,
    supaya di aplikasi vendor tsb bs muncul lagi jika vendor tsb berubah pikiran
    supaya bisa diaccept oleh vendor tsb */
    public function delete_rejected_order()
    {
        $this->load->model('torderdoc_model');
        
        $response = new stdClass();
        
        $response->status = $this->torderdoc_model->delete_rejected_order_model(array(
            $this->input->post('orderid'),
            $this->input->post('vendorId')
        ));
        
        echo json_encode($response);
    }
    
    //fungsi untuk menerima orderan. dipakai oleh aplikasi master+
    public function master_accept()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $orderId = $this->input->post('docId');
        
        $masterId = $this->input->post('userId');
        $token    = $this->input->post('token');
        
        $userType = $this->input->post('userType');
        
        $this->token_validate($masterId, $token, $userType);
        
        $workerId = 0;
        
        $result       = new stdClass();
        $result->stat = 0;
        $result->msg  = "";
        
        $this->load->model('muserlogin_vendor_model');
        $workerCount = $this->muserlogin_vendor_model->count_worker($masterId);
        
        if ($workerCount == 0) {
            $result->stat = 4;
            $result->msg  = "Failed to accept. Please add worker";
        } else {
            $this->load->model('torderdoc_model');
            
            $status = $this->torderdoc_model->get_status(array(
                $orderId
            ));
            
            if (intval($status) <= -1) {
                $result->stat = 2;
                $result->msg  = "failed to accept. booking canceled /timed out";
            } else if (intval($status) >= 1) {
                $result->stat = 3;
                $result->msg  = "failed to accept. booking already accepted by other vendor";
            } else {
                $this->load->model('torder_accepted_model');
                $insert_id = $this->torder_accepted_model->insert(array(
                    $masterId,
                    $orderId,
                    $workerId
                ));
                
                if (intval($insert_id) > 0) {
                    $this->torderdoc_model->update_status(array(
                        1,
                        $orderId
                    ));
                    
                    $result->msg = $this->torderdoc_model->getById($orderId);
                    
                    $result->stat = 1;
                } else {
                    $result->msg = "failed to accept";
                }
            }
        }
        
        echo json_encode($result);
    }
    
    //fungsi untuk menugaskan kurir. dipakai oleh aplikasi master+
    public function assign_worker()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $orderId  = $this->input->post('docId');
        $workerId = $this->input->post('workerId');
        
        $masterId = $this->input->post('userId');
        $token    = $this->input->post('token');
        
        $userType = $this->input->post('userType');
        
        $this->token_validate($masterId, $token, $userType); //validasi memakai token.
        
        $result       = new stdClass();
        $result->stat = 0;
        $result->msg  = "";
        
        $this->load->model('torderdoc_model');
        
        $status = $this->torderdoc_model->get_status(array(
            $orderId
        )); //mendapatkan status orderan. misalnya: 3 == kurir sudah pergi untuk mengantar orderan.
        
        if ($status < 3) { //jika kurir belum pergi
            $this->load->model('torder_accepted_model');
            
            $affected = $this->torder_accepted_model->update(array(
                $workerId,
                $masterId,
                $orderId
            )); //mengupdate: kurir dengan $workerId mengantar untuk orderan $orderId
            
            if (intval($affected) > 0) {
                $this->torderdoc_model->update_status(array(
                    2,
                    $orderId
                )); //mengupdate status orderan jadi = 2 (orderan sudah di-assign ke kurir / ditugaskan ke seorang kurir)
                
                $result->stat = 1;
            } else {
                $result->msg = "failed to assign";
            }
        } else { //jika kurir sudah pergi (status == 3)
            $result->stat = 2;
            $result->msg  = "failed to assign. worker already went for job";
        }
        
        echo json_encode($result);
    }
    
    //fungsi untuk mendapatkan riwayat pesanan. dipakai oleh aplikasi master+
    public function get_previous_orders_master()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $master_id = $this->input->post('userId');
        $token     = $this->input->post('token');
        
        $userType = 0;
        if (isset($_POST['userType']))
            $userType = $this->input->post('userType');
        
        $this->token_validate($master_id, $token, $userType);
        
        
        $this->load->model('torderdoc_model');
        
        if (!isset($_POST['offsetId'])) {
            $docs = $this->torderdoc_model->getFinishedJobsMaster($master_id);
        } else {
            $offset_id = $this->input->post('offsetId');
            $docs      = $this->torderdoc_model->getFinishedJobsMasterMore($master_id, $offset_id);
        }
        
        $result = array();
        
        $this->load->model('tordertrans_model');
        $this->load->model('treview_model');
        $this->load->model('tcustomorder_model');
        $this->load->model('tworker_timeline_model');
        $this->load->model('tpending_order_model');
        $this->load->model('tcustdiscount_model');
        $this->load->model('tcustdeposit_model');
        $this->load->model('mvendor_worker_model');
        $this->load->model('muserlogin_vendor_model');
        $this->load->model('torderlog_model');
        
        $vendor_username = $this->muserlogin_vendor_model->get_username($master_id);
        
        $result = array();
        
        foreach ($docs as $doc) {
            if ($doc->masterId == $master_id) {
                $docObject        = new stdClass();
                $docObject->docId = intval($doc->docId);
                
                $review = $this->treview_model->get($doc->docId);
                
                if ($review == array()) {
                    $docObject->rate   = -1;
                    $docObject->review = "";
                } else {
                    $docObject->rate   = $review->rate;
                    $docObject->review = $review->review;
                }
                
                $docObject->workerId  = $doc->workerId;
                $docObject->workerImg = $this->mvendor_worker_model->getProfilePictureByOrderId($doc->docId);
                $docObject->worker    = $this->mvendor_worker_model->getFirstNameByOrderId($doc->docId);
                $docObject->month     = $doc->month;
                $docObject->day       = intval($doc->day);
                
                $docObject->catId         = intval($doc->catId);
                $docObject->date          = strval($doc->date);
                $docObject->timeId        = intval($doc->timeId);
                $docObject->city          = $doc->city;
                $docObject->kecamatan     = $doc->kecamatan;
                $docObject->streetAddress = $doc->streetAddress;
                $docObject->custName      = $doc->custName;
                $docObject->phone         = $doc->phone;
                
                $transes   = $this->tordertrans_model->get($doc->docId);
                $trans_arr = array();
                
                foreach ($transes as $trans) {
                    $transObject            = new stdClass();
                    $transObject->catId     = intval($trans->catId);
                    $transObject->unitId    = intval($trans->unitId);
                    $transObject->brandId   = intval($trans->brandId);
                    $transObject->productId = intval($trans->productId);
                    $transObject->qty       = intval($trans->qty);
                    $transObject->price     = intval($trans->price);
                    $transObject->subTotal  = intval($trans->subTotal);
                    
                    array_push($trans_arr, $transObject);
                }
                
                $docObject->transList = $trans_arr;
                
                $custOrderTotal   = 0;
                $custom_order_arr = array();
                if (intval($doc->customOrderStatus) > 2) {
                    $custom_orders = $this->tcustomorder_model->get($doc->docId);
                    foreach ($custom_orders as $custom_order) {
                        
                        
                        $coObject              = new stdClass();
                        $coObject->docId       = intval($custom_order->docId);
                        $coObject->description = $custom_order->description;
                        $coObject->qty         = intval($custom_order->qty);
                        $coObject->unit        = $custom_order->unit;
                        $coObject->price       = intval($custom_order->price);
                        $coObject->total       = intval($custom_order->total);
                        
                        $custOrderTotal += $coObject->total;
                        
                        array_push($custom_order_arr, $coObject);
                    }
                }
                $docObject->custOrderTotal  = $custOrderTotal;
                $docObject->customOrderList = $custom_order_arr;
                
                $times    = $this->tworker_timeline_model->get($doc->docId, $doc->workerId);
                $time_arr = array();
                
                foreach ($times as $time) {
                    $timeObject             = new stdClass();
                    $timeObject->go_date    = strval($time->go_date);
                    $timeObject->start_date = strval($time->start_date);
                    $timeObject->done_date  = strval($time->done_date);
                    
                    array_push($time_arr, $timeObject);
                }
                
                $docObject->timeList = $time_arr;
                $docObject->details  = $doc->details;
                
                $docObject->isTestNote = $this->is_testnote($doc->notes, $vendor_username)->is_testnote;
                
                $docObject->isPendingOrder = 0;
                $docObject->notes          = $doc->notes;
                
                //discount etc. begin
                $discount_arr = $this->tcustdiscount_model->find_by_doc_id($doc->docId);
                
                if ($discount_arr != array()) {
                    $docObject->discountKind  = $discount_arr->discountKind;
                    $docObject->voucherAmount = $discount_arr->amount;
                    $docObject->voucherCode   = $discount_arr->voucherCode;
                } else {
                    $docObject->discountKind  = DISCOUNT_KIND_NONE;
                    $docObject->voucherAmount = 0;
                    $docObject->voucherCode   = '';
                }
                
                $deposit_arr = $this->tcustdeposit_model->find_by_doc_id($doc->docId, $doc->userId);
                if ($deposit_arr != array()) {
                    $docObject->depositAmount = $deposit_arr->amount;
                } else {
                    $docObject->depositAmount = 0;
                }
                //discount etc. end
                
                $docObject->finishTime = $this->torderlog_model->get_by_id($doc->docId);
                
                array_push($result, $docObject);
            }
        }
        
        echo json_encode($result);
    }
    
    //fungsi untuk memverifikasi nomor hape user. dipakai oleh aplikasi customer.
    function verify()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $param        = new stdClass();
        $param->code  = $this->input->post('code');
        $param->email = $this->input->post('email');
        $param->phone = $this->input->post('phone');
        
        $this->load->model('muserlogin_model');
        
        $response        = $this->muserlogin_model->do_verify($param); //mencari user yg melakukan verifikasi. kalau ketemu, update kolom verified = 1 di tabel muserlogin
        $response->email = $param->email;
        $response->phone = $param->phone;
        
        echo json_encode($response);
    }
    
    //fungsi ini dipakai oleh web backend (http://tukang-backend.com/v2/tasks/get_new_order_locations) untuk mengirim sms langsung ke user
    function cs_send_sms()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $message = $this->input->post('message');
        
        $result = $this->send_vsms($this->input->post('phone'), $message, true);
        echo json_encode($result);
    }
    
    //fungsi ini dipakai oleh web backend (http://tukang-backend.com/v2/tasks/get_new_order_locations) untuk mencari nomor hape user
    function cs_findno()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $phone = $this->input->post('phone');
        
        $this->load->model('tsmslog_model');
        $result = $this->tsmslog_model->find($phone);
        
        echo json_encode($result);
    }
    
    //fungsi ini untuk mengirim ulang kode verifikasi ke user. dipakai oleh aplikasi customer.
    function resendSMS()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $param = new stdClass();
        
        $param->phone = $this->input->post('phone');
        $param->email = $this->input->post('email');
        
        $this->load->model('muserlogin_model');
        
        $vcode = $this->muserlogin_model->get_vcode($param);
        
        $result = $this->send_vsms($this->prepare_phone($this->input->post('phone')), 'Kode TukangID Anda adalah ' . $vcode, false);
        
        echo $result;
    }
    
    //fungsi ini untuk mendapatkan data kurir. dipakai oleh aplikasi customer.
    function get_worker_data()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $userId = $this->input->post('userId');
        $token  = $this->input->post('token');
        
        $this->token_validate($userId, $token);
        
        $orderId = $this->input->post('orderId');
        
        $result = new stdClass();
        
        $this->load->model('torder_accepted_model');
        $workerId = $this->torder_accepted_model->getWorkerId($orderId);
        
        $this->load->model('muserlogin_worker_model');
        $result->workerData = $this->muserlogin_worker_model->get_worker($workerId);
        
        $this->load->model('torderdoc_model');
        $result->status = $this->torderdoc_model->get_status(array(
            $orderId
        ));
        
        echo json_encode($result);
    }
    
    //fungsi ini untuk update token master+ ketika vendor logout. dipakai oleh aplikasi master+ & broadcaster
    function logout_vendor()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed 1");
        }
        
        $param = new stdClass();
        if (isset($_POST['email']))
            $param->email = $this->input->post('email');
        
        $param->user_identifier = $this->input->post('user_identifier');
        $param->token           = $this->input->post('token');
        
        $userType = 0;
        if (isset($_POST['userType']))
            $userType = $this->input->post('userType');
        
        $this->token_validate($param->user_identifier, $param->token, $userType);
        
        $this->load->model('muserlogin_vendor_model');
        
        $response = $this->muserlogin_vendor_model->do_logout($param);
        
        echo json_encode($response);
    }
    
    //fungsi ini untuk update token worker ketika kurir logout. dipakai oleh aplikasi worker
    function logout_worker()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $param = new stdClass();
        
        $param->user_identifier = $this->input->post('user_identifier');
        $param->token           = $this->input->post('token');
        
        $userType = 0;
        if (isset($_POST['userType']))
            $userType = $this->input->post('userType');
        
        echo "trace_logout_worker.#." . json_encode($param) . "#" . $userType;
        
        $this->token_validate($param->user_identifier, $param->token, $userType);
        
        $this->load->model('muserlogin_worker_model');
        
        $response = $this->muserlogin_worker_model->do_logout($param);
        
        echo json_encode($response);
    }
    
    //fungsi ini untuk update token customer ketika user logout. dipakai oleh aplikasi customer
    function logout_user()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $param                  = new stdClass();
        $param->user_identifier = $this->input->post('user_identifier');
        $param->token           = $this->input->post('token');
        
        $this->token_validate($param->user_identifier, $param->token);
        
        $this->load->model('muserlogin_model');
        
        $response = $this->muserlogin_model->do_logout($param);
        
        echo json_encode($response);
    }
    
    //fungsi ini dipakai untuk logout dari website backend (http://tukang-backend.com/v2/tasks/login_admin)
    function logout_general()
    {
        $this->session->unset_userdata('user_sess');
        $this->login_admin();
    }
    
    //mengirim notifikasi orderan pending (orderan yg diorder sebelum jam 8 pagi atau setelah jam 4.30 sore) - update 1 nopember 2016
    function send_pending_orders()
    {
        // Define URL to Pushy endpoint
        
        if ($this->uri->segment(3) == "param145") {
            $url = base_url() . '/tasks/send_pushy';
            
            $this->load->model('torderdoc_model');
            
            $this->load->model('tpending_order_model');
            $pending_orders = $this->tpending_order_model->get();
            
            if ($pending_orders != array()) {
                foreach ($pending_orders as $row) {
                    
                    $pendingId = $row->id;
                    
                    $msg_json = json_decode($row->msg);
                    $docId    = $msg_json->docId;
                    
                    $status = $this->torderdoc_model->get_status(array(
                        intval($docId)
                    ));
                    
                    $identifier = "";
                    if (intval($msg_json->useTeam) == TEAM_SABER) //TEAM_SABER
                        $identifier = $row->identifier;
                    
                    if (isset($msg_json->testTransactionTarget)) {
                        if ($msg_json->testTransactionTarget != "")
                            $identifier = $msg_json->testTransactionTarget;
                    }
                    
                    if (intval($status) > -1 && intval($status) < 1) {
                        // Set post variables
                        $post = array(
                            'validator' => 'afe13Rg78#*Agy',
                            'msg' => $row->msg,
                            'target' => $row->target,
                            'identifier' => $row->identifier,
                            'web_broadcast' => '1'
                        );
                        
                        // Initialize curl handle
                        $ch = curl_init();
                        
                        // Set URL to Pushy endpoint
                        curl_setopt($ch, CURLOPT_URL, $url);
                        
                        // Set request method to POST
                        curl_setopt($ch, CURLOPT_POST, true);
                        
                        // Get the response back as string instead of printing it
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        
                        // Set post data as JSON
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
                        
                        // Actually send the push
                        $result = curl_exec($ch);
                        
                        // Display errors
                        if (curl_errno($ch)) {
                            echo curl_error($ch);
                        }
                        
                        // Close curl handle
                        curl_close($ch);
                        
                        // echo "#".$result."#<br>";
                        
                        if ($result == "") {
                            $this->tpending_order_model->update_issent($pendingId);
                        }
                    } else {
                        $this->tpending_order_model->update_issent($pendingId);
                    }
                }
            } else {
                echo "no pending orders";
            }
        }
    }
    
    //fungsi ini untuk mengupdate status order jadi 0 (new) lagi. supaya bisa diterima oleh vendor.
    //fungsi ini dipanggil oleh aplikasi customer ketika customer menekan tombol "Coba Lagi"
    //tombol "Coba Lagi" muncul setelah customer menunggu 15 menit tapi tidak ada vendor yg menerima orderan.
    public function renew_booking()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $userId = $this->input->post('userId');
        $token  = $this->input->post('token');
        
        $this->token_validate($userId, $token);
        
        $docId = $this->input->post('docId');
        
        $result        = new stdClass();
        $result->job   = "renew_booking";
        $result->stat  = 0;
        $result->doc   = array();
        $result->docId = $docId;
        
        $this->load->model('torderdoc_model');
        $status = $this->torderdoc_model->get_status(array(
            $docId
        ));
        if (intval($status) <= 0) {
            $r = $this->torderdoc_model->update_status(array(
                0,
                $docId
            ));
            
            if ($r)
                $result->stat = 1;
        } else {
            $result->stat = 1;
        }
        
        $result->doc = $this->get_one_doc($docId);
        
        echo json_encode($result);
    }
    
    //untuk mendapatkan data lokasi, username vendor, kategori produk yg dijual (ac service, cleaning, air atau install ac). dipakai oleh aplikasi customer
    public function get_vendor_locations()
    {
        $dataPv = null;
        if (isset($_POST['userId'])) {
            $this->load->model('muserlogin_model');
            $dataPv = $this->muserlogin_model->get_detail_preferred_vendor($_POST['userId']);
            
            if ($dataPv != null) {
                $total          = $dataPv->radius + RADIUS_TOLERANSI;
                $dataPv->radius = strval($total);
            }
        }
        
        $this->load->model('muserlogin_vendor_model');
        $result = $this->muserlogin_vendor_model->get_vendor_locations();
        
        if ($dataPv != null) {
            $result[] = $dataPv;
        }
        
        echo json_encode($result);
    }
    
    //untuk mendapatkan data lokasi, username vendor, kategori produk yg dijual (ac service, cleaning, air atau install ac). dipakai oleh aplikasi customer
    public function get_vendor_locations2()
    {
        
        // validation if preferend vendor
        $dataPv = null;
        if (isset($_POST['userId'])) {
            $this->load->model('muserlogin_model');
            $dataPv = $this->muserlogin_model->get_detail_preferred_vendor($_POST['userId']);
            
            if ($dataPv != null) {
                $total          = $dataPv->radius + RADIUS_TOLERANSI;
                $dataPv->radius = strval($total);
            }
        }
        
        $this->load->model('muserlogin_vendor_model');
        $result = $this->muserlogin_vendor_model->get_vendor_locations();
        
        if ($dataPv != null) {
            $result[] = $dataPv;
        }
        
        echo json_encode($result);
    }
    
    /*untuk mereset password user. dipakai oleh aplikasi customer & master+
    fungsi ini akan mengirim sms & email berisi password baru ke user yg lupa passwordnya.*/
    public function forgot_password()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $new_pass_obj = $this->generatePass(); //fungsi untuk membuat password baru secara otomatis
        
        $identifier = $this->input->post('identifier'); //identifier adalah email user
        $new_pass   = $new_pass_obj->encrypted;
        $who        = $this->input->post('actor'); //actor adalah penanda dari aplikasi yg memanggil fungsi ini.
        
        $salt = get_salt(); //mendapatkan salt dari fungsi get_salt di mine_helper.
        
        if ($new_pass != "") {
            $salt     = get_salt();
            $new_pass = $salt->prefix . $new_pass . $salt->suffix;
        }
        
        $param             = new stdClass();
        $param->identifier = $identifier;
        $param->new_pass   = $new_pass;
        
        if ($who == 'master') {
            $this->load->model('muserlogin_vendor_model');
            $response = $this->muserlogin_vendor_model->reset_pass($param);
        } else if ($who == 'customer') {
            
            $response       = new stdClass();
            $response->stat = 2;
            
            $this->load->model('muserlogin_model');
            
            if ($this->muserlogin_model->get_userid($identifier) > 0) { //jika user ditemukan di database
                $response = $this->muserlogin_model->reset_pass($param); //me-reset password user
            }
        }
        
        $result = new stdClass();
        
        if ($response->stat == 1) {
            $result->stat = 1;
            
            if ($who == 'customer') {
                $message = '<!DOCTYPE html>' . '<html>' . '	<head>' . '		<meta charset="UTF-8">' . '		<title>Your new Tukang.id password</title>' . '	</head>' . '	<body>' . '		Password Tukang.id Anda yang baru adalah: ' . $new_pass_obj->ori . '	</body>' . '</html>';
                
                //mengirim email ke user
                sendMailPHPMailer($identifier, "Password Tukang.id Anda telah berubah", 'Password Tukang.id Anda yang baru adalah: ' . $new_pass_obj->ori);
                
                //mengirim sms ke user
                $phone_row = $this->muserlogin_model->get_phone($identifier);
                if ($phone_row != array()) {
                    $this->send_vsms($this->prepare_phone($phone_row->phone), $phone_row->firstName . ', password TukangID Anda yg baru adalah ' . $new_pass_obj->ori . ' . Anda bisa mengganti password setelah login.', false);
                }
            }
            
            if ($who == 'master') {
                $masterPhone = $this->muserlogin_vendor_model->get_master_phone($identifier);
                if ($masterPhone != "") {
                    //mengirim sms ke user
                    $this->send_vsms($this->prepare_phone($masterPhone), 'Password TukangID Anda yg baru adalah ' . $new_pass_obj->ori, false);
                } else {
                    $result->stat = 0; //phone number not found
                }
            }
            
        } else if ($response->stat == 0) {
            $result->stat = 0; //affected_rows = 0;
        } else if ($response->stat == 2) {
            $result->stat = 2; //no record found;
        }
        
        echo json_encode($result);
    }
    
    //deprecated. fungsi untuk mendapatkan data air & gas. dipakai oleh aplikasi customer sebelum versi 4.x.
    //fungsi ini tidak boleh dihapus karena dipakai oleh aplikasi customer versi lama.
    public function get_water_prices()
    {
        $this->load->model('mproduct_model');
        $water_prices = $this->mproduct_model->get_water_prices();
        
        echo json_encode($water_prices);
    }
    
    //deprecated. fungsi untuk mendapatkan data air & gas. dipakai oleh aplikasi customer sebelum versi 4.x.
    //fungsi ini tidak boleh dihapus karena dipakai oleh aplikasi customer versi lama.
    public function get_water_prices2()
    {
        
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $tsSource = 0;
        if (!isset($_POST['timeStamp'])) {
            $date = date("Y-m-d");
        } else {
            $tsSource = 1;
            $date     = $_POST['timeStamp'];
        }
        
        $this->load->model('mproduct_model');
        $water_prices = $this->mproduct_model->get_water_prices();
        
        $this->load->model('mminimum_order_model');
        
        $this->load->model('mtime_model');
        $today_quota = $this->mtime_model->get_today_quota($date);
        
        $response                = new stdClass();
        $response->water_prices  = $water_prices;
        $response->minimum_order = $this->mminimum_order_model->get();
        $response->today_quota   = $today_quota;
        
        echo json_encode($response);
    }
    
    //fungsi ini untuk mendapatkan data air & gas. juga untuk mendapatkan order minimum. dipakai oleh aplikasi customer
    //untuk halaman quick order.
    public function get_water_prices_and_minimum_order()
    {
        
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $this->load->model('mproduct_model');
        $this->load->model('mminimum_order_model');
        $this->load->model('mlimit_model');
        
        $response                      = new stdClass();
        $response->water_prices        = $this->mproduct_model->get_water_prices();
        $response->limit_order_per_day = $this->mlimit_model->get_limit();
        $response->minimum_order       = $this->mminimum_order_model->get();
        
        echo json_encode($response);
    }
    
    //show order on specific date
    public function get_new_order_locations_onDate()
    {
        $orderOnDate = $this->uri->segment(3, "-1");
        $user_sess   = $this->session->userdata('user_sess');
        
        if (!isset($user_sess)) {
            redirect('tasks/login_admin');
        }
        
        if (!$this->uri->segment(3)) {
            exit('no direct access allowed, pick the date first');
        }
        
        $this->load->model('muserlogin_model');
        $this->load->model('muserlogin_vendor_model');
        $this->load->model('muserlogin_worker_model');
        $this->load->model('mtime_model');
        $this->load->model('torderdoc_model');
        
        $vendor_locations = $this->muserlogin_vendor_model->get_vendor_locations2();
        
        $saber_list = $this->muserlogin_worker_model->get_worker_list(16);
        $brand_list = $this->muserlogin_vendor_model->get_water_and_gas_array2();
        $time_list  = $this->mtime_model->get_time_list();
        
        $order_locations = $this->torderdoc_model->get_order_locationsOnDate($orderOnDate);
        
        $vendor_products = $this->muserlogin_vendor_model->get_vendor_products();
        
        $this->load->model('tordertrans_model');
        $this->load->model('torder_rejected_model');
        $this->load->model('torder_accepted_model');
        $this->load->model('tcustdiscount_model');
        $this->load->model('tcustdeposit_model');
        $this->load->model('tpending_order_model');
        
        $pending_orders = $this->tpending_order_model->get_all();
        
        $pending_table = array();
        if ($pending_orders != array()) {
            foreach ($pending_orders as $row) {
                $msg = json_decode($row->msg); //value for row 'msg' as json format, decode into array
                array_push($pending_table, $msg->docId); //push values array into $pending_table
            }
        }
        
        $orders = array();
        foreach ($order_locations as $order_location) {
            $order        = new stdClass();
            $discount_arr = $this->tcustdiscount_model->find_by_doc_id($order_location->orderId);
            
            if ($discount_arr != array()) {
                if ($discount_arr->discountKind == DISCOUNT_KIND_VOUCHER) {
                    $order->discountKind = "Diskon voucher";
                }
                if ($discount_arr->discountKind == DISCOUNT_KIND_REFERRAL_CUST) {
                    $order->discountKind = "Diskon ref. cust";
                }
                if ($discount_arr->discountKind == DISCOUNT_KIND_REFERRAL_VENDOR) {
                    $order->discountKind = "Diskon ref. vendor";
                }
                $order->voucherCode   = $discount_arr->voucherCode;
                $order->voucherAmount = $discount_arr->amount;
            } else {
                $order->discountKind  = "tidak ada diskon";
                $order->voucherCode   = "";
                $order->voucherAmount = 0;
            }
            
            $deposit_arr = $this->tcustdeposit_model->find_by_doc_id($order_location->orderId, $order_location->userId);
            if ($deposit_arr != array()) {
                $order->depositAmount = $deposit_arr->amount;
            } else {
                $order->depositAmount = 0;
            }
            
            $order->totalPayment       = number_format(($order_location->totalPaymentOri - $order->voucherAmount - $order->depositAmount), 0, ".", ",");
            $order->voucherAmount      = number_format($order->voucherAmount, 0, ".", ",");
            $order->preferred_vendor   = $this->muserlogin_model->get_preferred_vendor($order_location->userId);
            $order->vendors_by_ranking = $this->torderdoc_model->get_vendors_by_ranking(array(
                $order_location->orderId
            ));
            $order->status             = $this->torderdoc_model->get_status(array(
                $order_location->orderId
            ));
            $order->summary            = $order_location;
            $order->detail             = $this->tordertrans_model->get2($order_location->orderId);
            $order->rejected           = $this->torder_rejected_model->find_who_rejected($order_location->orderId);
            $order->accepted           = $this->torder_accepted_model->find_who_accepted($order_location->orderId);
            $order->is_pending_order   = in_array($order_location->orderId, $pending_table) ? 1 : 0;
            $orders[]                  = $order;
            // echo json_encode($order);
        }
        
        $data = array(
            'vendor_locations' => $vendor_locations,
            'order_locations' => $orders,
            'vendor_products' => $vendor_products,
            'saber_list' => $saber_list,
            'brand_list' => $brand_list,
            'time_list' => $time_list
        );
        
        $this->load->view('order_maps_view', $data);
    }
    
    //fungsi ini untuk menampilkan peta orderan. untuk customer service.
    //link: http://tukang-backend.com/v2/tasks/get_new_order_locations
    //orderan yang tampil di peta hanya orderan untuk hari dan seterusnya
    //untuk orderan kemarin bisa dicari dengan fitur Find di halaman ini
    public function get_new_order_locations()
    {
        
        
        $user_sess = $this->session->userdata('user_sess');
        
        if (!isset($user_sess)) {
            redirect('tasks/login_admin');
        }
        
        $this->load->model('muserlogin_model');
        $this->load->model('muserlogin_vendor_model');
        $this->load->model('muserlogin_worker_model');
        $this->load->model('mtime_model');
        $this->load->model('torderdoc_model');
        $this->load->model('torderlog_model');
        
        $vendor_locations = $this->muserlogin_vendor_model->get_vendor_locations2();
        
        $saber_list = $this->muserlogin_worker_model->get_worker_list(16); //team saber
        $brand_list = $this->muserlogin_vendor_model->get_water_and_gas_array2();
        $time_list  = $this->mtime_model->get_time_list();
        
        $order_locations = $this->torderdoc_model->get_order_locations2();
        
        $vendor_products = $this->muserlogin_vendor_model->get_vendor_products();
        
        $this->load->model('tordertrans_model');
        $this->load->model('torder_rejected_model');
        $this->load->model('torder_accepted_model');
        $this->load->model('tcustdiscount_model');
        $this->load->model('tcustdeposit_model');
        $this->load->model('tpending_order_model');
        $this->load->model('msuspicious_transactions_model');
        $this->load->model('mcustomer_investor_model');
        
        
        
        $pending_orders = $this->tpending_order_model->get_all();
        
        $pending_table = array();
        
        if ($pending_orders != array()) {
            foreach ($pending_orders as $row) {
                $msg = json_decode($row->msg);
                array_push($pending_table, $row->docId);
            }
        }
        
        $orders = array();
        foreach ($order_locations as $order_location) {
            $order = new stdClass();
            
            $discount_arr = $this->tcustdiscount_model->find_by_doc_id($order_location->orderId);
            
            if ($discount_arr != array()) {
                if ($discount_arr->discountKind == DISCOUNT_KIND_VOUCHER) {
                    $order->discountKind = "Diskon voucher";
                }
                if ($discount_arr->discountKind == DISCOUNT_KIND_REFERRAL_CUST) {
                    $order->discountKind = "Diskon ref. cust";
                }
                if ($discount_arr->discountKind == DISCOUNT_KIND_REFERRAL_VENDOR) {
                    $order->discountKind = "Diskon ref. vendor";
                }
                $order->voucherCode   = $discount_arr->voucherCode;
                $order->voucherAmount = $discount_arr->amount;
            } else {
                $order->discountKind  = "tidak ada diskon";
                $order->voucherCode   = "";
                $order->voucherAmount = 0;
            }
            
            $deposit_arr = $this->tcustdeposit_model->find_by_doc_id($order_location->orderId, $order_location->userId);
            if ($deposit_arr != array()) {
                $order->depositAmount = $deposit_arr->amount;
            } else {
                $order->depositAmount = 0;
            }
            
            
            
            
            
            $order->totalPayment       = number_format(($order_location->totalPaymentOri - $order->voucherAmount - $order->depositAmount), 0, ".", ",");
            $order->voucherAmount      = number_format($order->voucherAmount, 0, ".", ",");
            $order->preferred_vendor   = $this->muserlogin_model->get_preferred_vendor($order_location->userId);
            $order->vendors_by_ranking = $this->torderdoc_model->get_vendors_by_ranking(array(
                $order_location->orderId
            ));
            $order->status             = $this->torderdoc_model->get_status(array(
                $order_location->orderId
            ));
            $order->summary            = $order_location;
            $order->detail             = $this->tordertrans_model->get2($order_location->orderId);
            $order->rejected           = $this->torder_rejected_model->find_who_rejected($order_location->orderId);
            $order->accepted           = $this->torder_accepted_model->find_who_accepted($order_location->orderId);
            $order->is_pending_order   = in_array($order_location->orderId, $pending_table) ? 1 : 0;
            
            $order->order_log = $this->torderlog_model->get_order_log($order_location->orderId);
            
            // set status time log
            $dataTimeLog = $this->torderlog_model->get_order_log_status($order_location->orderId);
            
            
            if (count($dataTimeLog) > 1) {
                $timeLogAcc    = strtotime($dataTimeLog[0]['time']);
                $timeLogDone   = strtotime($dataTimeLog[1]['time']);
                $minutes       = round(abs($timeLogDone - $timeLogAcc) / 60, 2);
                $setStatusTime = "";
                
                // set penerima email
                $this->load->model('msuspicious_transactions_model');
                $receivers      = $this->msuspicious_transactions_model->get_suspicious_transactions();
                $emailReceivers = $receivers[0]->email_suspicious_transactions;
                
                
                $countData = count($order->detail);
                
                
                $getDataTransLog = $this->tordertrans_model->get_detail_translog($order_location->orderId);
                
                $dataListProduct = '';
                
                foreach ($getDataTransLog as $key => $value) {
                    $dataListProduct = $value->qty . ' ' . $value->productName;
                }
                
                // get data suspicious
                $dataSuspicious = $this->msuspicious_transactions_model->get_suspicious_transactions();
                
                // get data total order
                $totalOrder = $this->tordertrans_model->getTotalOrder($order_location->orderId);
                
                
                $limitTime       = $dataSuspicious[0]->time_accepted_to_done;
                $limitTotalOrder = $dataSuspicious[0]->limit_items_order;
                $triggerByApps   = $dataTimeLog[0]['triggered_by'];
                
                if ($minutes < $limitTime && $totalOrder <= $limitTotalOrder && $triggerByApps == 0) {
                    $setStatusTime = "MENCURIGAKAN";
                    
                    // sending email transaksi mencurigakan
                    sendMailPHPMailer($emailReceivers, 'Tukang.id - Transaksi Mencurigakan', 'Harap Cek Transaksi nomor #' . $order_location->orderId . ' karena ada indikasi mencurigakan dengan detail sebagai berikut : <br />
						jumlah orderan : ' . $dataListProduct . '<br />
						timeaccepted : ' . $dataTimeLog[0]['time'] . '<br />
						timedone : ' . $dataTimeLog[1]['time'] . '<br />
						timestatus : MENCURIGAKAN', '2');
                    
                } else {
                    $setStatusTime = "normal";
                }
                $order->order_log_status_time = $setStatusTime;
            } else {
                $order->order_log_status_time = "tidak ada data";
            }
            $getListInvestorCustomer = $this->mcustomer_investor_model->get_list();
            $investorCustomer        = $getListInvestorCustomer[0]['user_id'];
            $customerInvestor        = explode(',', $investorCustomer);
            $userIdInvestor          = $order_location->userId;
            
            if (in_array($userIdInvestor, $customerInvestor)) {
                $order->isInvestorCustomer = 'INVESTOR!!!!!';
            } else {
                $order->isInvestorCustomer = "";
            }
            
            $orders[] = $order;
        }
        
        $data = array(
            'vendor_locations' => $vendor_locations,
            'order_locations' => $orders,
            'vendor_products' => $vendor_products,
            'saber_list' => $saber_list,
            'brand_list' => $brand_list,
            'time_list' => $time_list
        );
        
        $this->load->view('order_maps_view', $data);
    }
    
    //mendapatkan data 1 orderan.
    //dipakai di link http://tukang-backend.com/v2/tasks/get_new_order_locations
    //untuk mencari orderan yg tidak muncul di peta karena peta hanya memunculkan orderan untuk hari ini dan seterusnya
    public function get_one_order()
    {
        
        $orderId = $this->input->post('orderId');
        
        $user_sess = $this->session->userdata('user_sess');
        
        if (!isset($user_sess)) {
            redirect('tasks/login_admin');
        }
        
        $this->load->model('muserlogin_model');
        
        $this->load->model('torderdoc_model');
        $order_locations = $this->torderdoc_model->get_order_location($orderId);
        
        $this->load->model('tordertrans_model');
        $this->load->model('torder_rejected_model');
        $this->load->model('torder_accepted_model');
        $this->load->model('tcustdiscount_model');
        $this->load->model('tcustdeposit_model');
        
        $orders = array();
        foreach ($order_locations as $order_location) {
            
            $order = new stdClass();
            
            $discount_arr = $this->tcustdiscount_model->find_by_doc_id($order_location->orderId);
            
            if ($discount_arr != array()) {
                if ($discount_arr->discountKind == DISCOUNT_KIND_VOUCHER) {
                    $order->discountKind = "Diskon voucher";
                }
                if ($discount_arr->discountKind == DISCOUNT_KIND_REFERRAL_CUST) {
                    $order->discountKind = "Diskon ref. cust";
                }
                if ($discount_arr->discountKind == DISCOUNT_KIND_REFERRAL_VENDOR) {
                    $order->discountKind = "Diskon ref. vendor";
                }
                $order->voucherCode   = $discount_arr->voucherCode;
                $order->voucherAmount = $discount_arr->amount;
            } else {
                $order->discountKind  = "tidak ada diskon";
                $order->voucherCode   = "";
                $order->voucherAmount = 0;
            }
            
            $deposit_arr = $this->tcustdeposit_model->find_by_doc_id($order_location->orderId, $order_location->userId);
            if ($deposit_arr != array()) {
                $order->depositAmount = $deposit_arr->amount;
            } else {
                $order->depositAmount = 0;
            }
            
            $order->totalPayment     = number_format(($order_location->totalPaymentOri - $order->voucherAmount - $order->depositAmount), 0, ".", ",");
            $order->voucherAmount    = number_format($order->voucherAmount, 0, ".", ",");
            $order->preferred_vendor = $this->muserlogin_model->get_preferred_vendor($order_location->userId);
            
            $order->status   = $this->torderdoc_model->get_status(array(
                $order_location->orderId
            ));
            $order->summary  = $order_location;
            $order->detail   = $this->tordertrans_model->get2($order_location->orderId);
            $order->rejected = $this->torder_rejected_model->find_who_rejected($order_location->orderId);
            $order->accepted = $this->torder_accepted_model->find_who_accepted($order_location->orderId);
            $orders[]        = $order;
        }
        
        $i = 0;
        
        $json_str = "{";
        $result   = array();
        
        foreach ($orders as $row) {
            
            $summary = $row->summary;
            
            if ($i > 0)
                $json_str .= ",";
            
            $json_str .= '{
				"center": {"lat":' . $summary->lat . ',"lng":' . $summary->lng . '},
				"rad":50,
				"lbl": "order_' . $summary->orderId . '"
			}';
            
            $center      = new stdClass();
            $center->lat = $summary->lat;
            $center->lng = $summary->lng;
            
            $result = array(
                "center" => $center,
                "rad" => 50,
                "lbl" => "order_" . $summary->orderId,
                "label" => str_replace("\n", "<br/>", json_encode($row))
            );
            
            $i++;
        }
        
        $json_str .= "}";
        echo json_encode($result);
    }
    
    //untuk mendapatkan data up-to-date untuk ditampilkan di peta
    //fungsi ini saat ini (1 nopember 2016) belum dipakai.
    //karena fungsi ini membuat halaman peta menjadi lambat. seharusnya tidak memakai fungsi ini untuk refresh data peta
    //lebih baik memakai metode socket / push notif untuk refresh data.
    public function get_new_orders() //unused
    {
        $user_sess = $this->session->userdata('user_sess');
        
        if (!isset($user_sess)) {
            redirect('tasks/login_admin');
        }
        
        $this->load->model('torderdoc_model');
        $order_locations = $this->torderdoc_model->get_order_locations2();
        
        $this->load->model('tordertrans_model');
        $this->load->model('torder_rejected_model');
        $this->load->model('torder_accepted_model');
        
        $orders = array();
        foreach ($order_locations as $order_location) {
            $order           = new stdClass();
            $order->status   = $this->torderdoc_model->get_status(array(
                $order_location->orderId
            ));
            $order->summary  = $order_location;
            $order->detail   = $this->tordertrans_model->get2($order_location->orderId);
            $order->rejected = $this->torder_rejected_model->find_who_rejected($order_location->orderId);
            $order->accepted = $this->torder_accepted_model->find_who_accepted($order_location->orderId);
            $orders[]        = $order;
        }
        
        $i = 0;
        
        $json_str = "{";
        
        foreach ($orders as $row) {
            
            $summary = $row->summary;
            
            if ($i > 0)
                $json_str .= ",";
            
            $json_str .= '"order_' . $summary->orderId . '":' . '{
				"center": {"lat":' . $summary->lat . ',"lng":' . $summary->lng . '},
				"rad":50,
				"lbl": "order_' . $summary->orderId . '",
				"label": "' . str_replace("'", "\'", str_replace("\"", "\\\"", str_replace("\n", "<br/>", json_encode($row)))) . '"
			}';
            $i++;
        }
        
        $json_str .= "}";
        echo $json_str;
    }
    
    //untuk menampilkan halaman http://tukang-backend.com/v2/tasks/get_transaction_summary_report
    public function get_transaction_summary_report()
    {
        
        $user_sess = $this->session->userdata('user_sess');
        
        if (!isset($user_sess)) {
            redirect('tasks/login_admin');
        }
        
        $data = $this->get_transaction_summary_data('7-days');
        
        $this->load->view('transaction_summary_report', $data);
    }
    
    //untuk menampilkan data sesuai periode yg diminta. dipanggil di halaman http://tukang-backend.com/v2/tasks/get_transaction_summary_report
    public function get_transactions()
    {
        $data = $this->get_transaction_summary_data($this->input->post('period'));
        echo json_encode($data);
    }
    
    //untuk download csv. dipakai di halaman http://tukang-backend.com/v2/tasks/get_transaction_summary_report
    public function get_csv()
    {
        $forwhat  = $this->uri->segment(3); //example: 'omzet'
        $period   = $this->uri->segment(4); //example: '60-days'
        $filename = $this->uri->segment(5);
        
        if (isset($forwhat) && isset($period) && isset($filename)) {
            $this->load->helper('download');
            force_download($filename . "." . $period . ".csv", $this->get_transaction_summary_data($period, $forwhat));
        } else {
            echo "access denied";
        }
    }
    
    //untuk mendapatkan data mentah untuk ditampilkan di halaman http://tukang-backend.com/v2/tasks/get_transaction_summary_report
    public function get_transaction_summary_data($period, $csv_forwhat = '')
    {
        $this->load->model('torderdoc_model');
        $orders = $this->torderdoc_model->get_order_per_day(0, 0, $period);
        
        $date_list = $this->torderdoc_model->get_date_list(1, $period);
        
        //==================================================================
        $orders_water_orderdate_obj = $this->torderdoc_model->get_order_per_day(CATEGORY_WATER_LPG, 1, $period);
        $orders_water_orderdate     = $orders_water_orderdate_obj->result;
        $query1                     = $orders_water_orderdate_obj->query;
        
        $timeout_orders_water_orderdate_obj = $this->torderdoc_model->get_timeout_order_per_day(CATEGORY_WATER_LPG, 1, $period);
        $timeout_orders_water_orderdate     = $timeout_orders_water_orderdate_obj->result;
        $query2                             = $timeout_orders_water_orderdate_obj->query;
        
        $canceled_orders_water_orderdate_obj = $this->torderdoc_model->get_canceled_order_per_day(CATEGORY_WATER_LPG, 1, $period);
        $canceled_orders_water_orderdate     = $canceled_orders_water_orderdate_obj->result;
        $query3                              = $canceled_orders_water_orderdate_obj->query;
        
        $done_orders_water_orderdate_obj = $this->torderdoc_model->get_done_order_per_day(CATEGORY_WATER_LPG, 1, $period);
        $done_orders_water_orderdate     = $done_orders_water_orderdate_obj->result;
        $query4                          = $done_orders_water_orderdate_obj->query;
        
        // undone status order
        $undone_orders_water_orderdate_obj = $this->torderdoc_model->get_undone_order_per_day(CATEGORY_WATER_LPG, 1, $period);
        $undone_orders_water_orderdate     = $undone_orders_water_orderdate_obj->result;
        $query5                            = $undone_orders_water_orderdate_obj->query;
        
        //==================================================================
        
        //==================================================================
        $omzet_per_day_obj = $this->torderdoc_model->get_omzet_per_day(0, 1, $period);
        $omzet_per_day     = $omzet_per_day_obj->result;
        $query5            = $omzet_per_day_obj->query;
        
        $omzet_per_day_water_obj = $this->torderdoc_model->get_omzet_per_day(CATEGORY_WATER_LPG, 1, $period);
        $omzet_per_day_water     = $omzet_per_day_water_obj->result;
        $query6                  = $omzet_per_day_water_obj->query;
        
        $omzet_per_day_ac_service_obj = $this->torderdoc_model->get_omzet_per_day(CATEGORY_AC_SERVICE, 1, $period);
        $omzet_per_day_ac_service     = $omzet_per_day_ac_service_obj->result;
        $query7                       = $omzet_per_day_ac_service_obj->query;
        //==================================================================
        
        //==================================================================
        $get_order_distrib_water_all_obj = $this->torderdoc_model->get_order_distrib_water('all', 1, $period);
        $get_order_distrib_water_all     = $get_order_distrib_water_all_obj->result;
        $query8                          = $get_order_distrib_water_all_obj->query;
        
        $get_order_distrib_water_saber_obj = $this->torderdoc_model->get_order_distrib_water('saber', 1, $period);
        $get_order_distrib_water_saber     = $get_order_distrib_water_saber_obj->result;
        $query9                            = $get_order_distrib_water_saber_obj->query;
        
        $get_order_distrib_water_vendors_obj = $this->torderdoc_model->get_order_distrib_water('vendors', 1, $period);
        $get_order_distrib_water_vendors     = $get_order_distrib_water_vendors_obj->result;
        $query10                             = $get_order_distrib_water_vendors_obj->query;
        //==================================================================
        
        $orders_water_orderdate_data          = array();
        $timeout_orders_water_orderdate_data  = array();
        $canceled_orders_water_orderdate_data = array();
        $done_orders_water_orderdate_data     = array();
        $undone_orders_water_orderdate_data   = array();
        
        $omzet_per_day_data            = array();
        $omzet_per_day_water_data      = array();
        $omzet_per_day_ac_service_data = array();
        
        $get_order_distrib_water_all_data     = array();
        $get_order_distrib_water_saber_data   = array();
        $get_order_distrib_water_vendors_data = array();
        
        if ($csv_forwhat == '') {
            foreach ($date_list as $order_date) {
                $orders_water_orderdate_data          = $this->fill_data($order_date, $orders_water_orderdate, $orders_water_orderdate_data, false, 0);
                $timeout_orders_water_orderdate_data  = $this->fill_data($order_date, $timeout_orders_water_orderdate, $timeout_orders_water_orderdate_data, true, 0);
                $canceled_orders_water_orderdate_data = $this->fill_data($order_date, $canceled_orders_water_orderdate, $canceled_orders_water_orderdate_data, true, 0);
                $done_orders_water_orderdate_data     = $this->fill_data($order_date, $done_orders_water_orderdate, $done_orders_water_orderdate_data, false, 0);
                $undone_orders_water_orderdate_data   = $this->fill_data($order_date, $undone_orders_water_orderdate, $undone_orders_water_orderdate_data, true, 0);
                
                $omzet_per_day_data            = $this->fill_data($order_date, $omzet_per_day, $omzet_per_day_data, false, 1);
                $omzet_per_day_water_data      = $this->fill_data($order_date, $omzet_per_day_water, $omzet_per_day_water_data, false, 1);
                $omzet_per_day_ac_service_data = $this->fill_data($order_date, $omzet_per_day_ac_service, $omzet_per_day_ac_service_data, true, 1);
                
                $get_order_distrib_water_all_data     = $this->fill_data($order_date, $get_order_distrib_water_all, $get_order_distrib_water_all_data, false, 0);
                $get_order_distrib_water_saber_data   = $this->fill_data($order_date, $get_order_distrib_water_saber, $get_order_distrib_water_saber_data, true, 0);
                $get_order_distrib_water_vendors_data = $this->fill_data($order_date, $get_order_distrib_water_vendors, $get_order_distrib_water_vendors_data, false, 0);
            }
            
            return array(
                'date_list' => $date_list,
                'orders_water_orderdate_data' => $orders_water_orderdate_data,
                'timeout_orders_water_orderdate_data' => $timeout_orders_water_orderdate_data,
                'canceled_orders_water_orderdate_data' => $canceled_orders_water_orderdate_data,
                'done_orders_water_orderdate_data' => $done_orders_water_orderdate_data,
                'undone_orders_water_orderdate_data' => $undone_orders_water_orderdate_data,
                'omzet_per_day_data' => $omzet_per_day_data,
                'omzet_per_day_water_data' => $omzet_per_day_water_data,
                'omzet_per_day_ac_service_data' => $omzet_per_day_ac_service_data,
                'get_order_distrib_water_all_data' => $get_order_distrib_water_all_data,
                'get_order_distrib_water_saber_data' => $get_order_distrib_water_saber_data,
                'get_order_distrib_water_vendors_data' => $get_order_distrib_water_vendors_data
            );
        } else {
            if ($csv_forwhat == 'orders') {
                $data = "Date;Total;Timed Out;Canceled;Done;Undone" . PHP_EOL;
                foreach ($date_list as $order_date) {
                    
                    $date_new_format       = new DateTime($order_date->dt);
                    $order_date_new_format = $date_new_format->format('D, j M');
                    
                    $data = $this->fill_data_csv1($order_date->dt, $orders_water_orderdate, $timeout_orders_water_orderdate, $canceled_orders_water_orderdate, $done_orders_water_orderdate, $undone_orders_water_orderdate, false, $order_date_new_format, $data, 0);
                }
                
                return $data;
            }
            if ($csv_forwhat == 'distrib') {
                $data = "Date;Total;Tim Saber;Tim Para Vendor" . PHP_EOL;
                foreach ($date_list as $order_date) {
                    
                    $date_new_format       = new DateTime($order_date->dt);
                    $order_date_new_format = $date_new_format->format('D, j M');
                    
                    $data = $this->fill_data_csv2($order_date->dt, $get_order_distrib_water_all, $get_order_distrib_water_saber, $get_order_distrib_water_vendors, false, $order_date_new_format, $data, 0);
                }
                
                return $data;
            }
            if ($csv_forwhat == 'omzet') {
                $data = "Date;Total;Water & Gas;AC Service" . PHP_EOL;
                foreach ($date_list as $order_date) {
                    
                    $date_new_format       = new DateTime($order_date->dt);
                    $order_date_new_format = $date_new_format->format('D, j M');
                    
                    $data = $this->fill_data_csv2($order_date->dt, $omzet_per_day, $omzet_per_day_water, $omzet_per_day_ac_service, false, $order_date_new_format, $data, 1);
                }
                
                return $data;
            }
        }
    }
    
    //untuk mengisi data ke json yg lebih siap ditampilkan di grafik di halaman http://tukang-backend.com/v2/tasks/get_transaction_summary_report
    public function fill_data($order_date, $dates, $data, $allow_count_only_one, $is_for_omzet)
    {
        
        $order_count = 0;
        foreach ($dates as $order) {
            if ($order_date->dt == $order->date) {
                $order_count = $order->count;
            }
        }
        
        $month = intval(substr($order_date->dt, 5, 2));
        $date  = intval(substr($order_date->dt, -2));
        $year  = intval(substr($order_date->dt, 0, 4));
        
        $dt = mktime(0, 0, 0, $month, $date, $year) + 25200;
        
        $minimum_limit = 4;
        if ($dt > 1482537600) { // 1482537600 = 2016-12-24 07:00:00
            // $minimum_limit = 20; //warning! ini utk di server staging saja!
            if ($is_for_omzet)
                $minimum_limit = 900000;
        }
        
        if ($allow_count_only_one) {
            array_push($data, array(
                $dt * 1000,
                intval($order_count)
            ));
        } else {
            if ($order_count > $minimum_limit) {
                array_push($data, array(
                    $dt * 1000,
                    intval($order_count)
                ));
            }
        }
        
        return $data;
    }
    
    //mengisi data file csv untuk grafik 'orders'
    public function fill_data_csv1($order_date, $data1, $data2, $data3, $data4, $data5, $allow_count_only_one, $order_date_new_format, $data, $is_for_omzet)
    {
        
        $order_count_total = 0;
        foreach ($data1 as $order) {
            if ($order_date == $order->date) {
                $order_count_total = $order->count;
            }
        }
        
        $order_count_timeout = 0;
        foreach ($data2 as $order) {
            if ($order_date == $order->date) {
                $order_count_timeout = $order->count;
            }
        }
        
        $order_count_canceled = 0;
        foreach ($data3 as $order) {
            if ($order_date == $order->date) {
                $order_count_canceled = $order->count;
            }
        }
        
        $order_count_done = 0;
        foreach ($data4 as $order) {
            if ($order_date == $order->date) {
                $order_count_done = $order->count;
            }
        }
        
        $order_count_undone = 0;
        foreach ($data5 as $order) {
            if ($order_date == $order->date) {
                $order_count_undone = $order->count;
            }
        }
        
        $month = intval(substr($order_date, 5, 2));
        $date  = intval(substr($order_date, -2));
        $year  = intval(substr($order_date, 0, 4));
        
        $dt = mktime(0, 0, 0, $month, $date, $year) + 25200;
        
        $minimum_limit = 4;
        if ($dt > 1482537600) { // 1482537600 = 2016-12-24 07:00:00
            $minimum_limit = 20;
            if ($is_for_omzet)
                $minimum_limit = 900000;
        }
        
        if ($allow_count_only_one) {
            $data .= $order_date_new_format . ";" . $order_count_total . ";" . $order_count_timeout . ";" . $order_count_canceled . ";" . $order_count_done . ";" . $order_count_undone . PHP_EOL;
        } else {
            if ($order_count_total > $minimum_limit) {
                $data .= $order_date_new_format . ";" . $order_count_total . ";" . $order_count_timeout . ";" . $order_count_canceled . ";" . $order_count_done . ";" . $order_count_undone . PHP_EOL;
            }
        }
        
        return $data;
    }
    
    //mengisi data file csv untuk grafik 'distrib' & 'omzet'
    public function fill_data_csv2($order_date, $data1, $data2, $data3, $allow_count_only_one, $order_date_new_format, $data, $is_for_omzet)
    {
        
        $order_count_total = 0;
        foreach ($data1 as $order) {
            if ($order_date == $order->date) {
                $order_count_total = $order->count;
            }
        }
        
        $order_count_1 = 0;
        foreach ($data2 as $order) {
            if ($order_date == $order->date) {
                $order_count_1 = $order->count;
            }
        }
        
        $order_count_2 = 0;
        foreach ($data3 as $order) {
            if ($order_date == $order->date) {
                $order_count_2 = $order->count;
            }
        }
        
        $month = intval(substr($order_date, 5, 2));
        $date  = intval(substr($order_date, -2));
        $year  = intval(substr($order_date, 0, 4));
        
        $dt = mktime(0, 0, 0, $month, $date, $year) + 25200;
        
        $minimum_limit = 4;
        if ($dt > 1482537600) { // 1482537600 = 2016-12-24 07:00:00
            $minimum_limit = 20;
            if ($is_for_omzet)
                $minimum_limit = 900000;
        }
        
        if ($allow_count_only_one) {
            $data .= $order_date_new_format . ";" . $order_count_total . ";" . $order_count_1 . ";" . $order_count_2 . PHP_EOL;
        } else {
            if ($order_count_total > $minimum_limit) {
                $data .= $order_date_new_format . ";" . $order_count_total . ";" . $order_count_1 . ";" . $order_count_2 . PHP_EOL;
            }
        }
        
        return $data;
    }
    
    //fungsi untuk update data 'last_seen', yaitu kapan user terakhir terlihat memakai aplikasi
    //fungsi ini dipakai oleh aplikasi customer
    public function update_last_seen()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $is_android = isset($_POST['is_android']) ? $_POST['is_android'] : 0;
        
        $email = $this->input->post('email');
        
        $this->load->model('muserlogin_model');
        $this->muserlogin_model->update_last_seen(array(
            $email
        ));
        
        $this->load->model('musertrack_model');
        if ($email != "") {
            $found_num_rows = $this->musertrack_model->find($email, "");
            if ($found_num_rows > 0) {
                $this->musertrack_model->delete($email, "");
            }
            $this->musertrack_model->insert($email, "");
        }
        
        if (isset($_POST['one_signal_user_id'])) {
            $one_signal_user_id = $this->input->post('one_signal_user_id');
            
            if (!($email == "" && $one_signal_user_id == "")) {
                $this->load->model('musertrack_onesignal_model');
                if ($email != "" && $one_signal_user_id != "") {
                    $found_num_rows = $this->musertrack_onesignal_model->find($email, $one_signal_user_id);
                    if ($found_num_rows > 0) {
                        $this->musertrack_onesignal_model->delete($email, $one_signal_user_id);
                    }
                    $this->musertrack_onesignal_model->insert($email, $one_signal_user_id);
                } else if ($email != "") {
                    $found_num_rows = $this->musertrack_onesignal_model->find($email, "");
                    if ($found_num_rows > 0) {
                        $this->musertrack_onesignal_model->delete($email, "");
                    }
                    $this->musertrack_onesignal_model->insert($email, "");
                } else if ($one_signal_user_id != "") {
                    $found_num_rows = $this->musertrack_onesignal_model->find("", $one_signal_user_id);
                    if ($found_num_rows > 0) {
                        $this->musertrack_onesignal_model->delete("", $one_signal_user_id);
                    }
                    $this->musertrack_onesignal_model->insert("", $one_signal_user_id);
                }
            }
        }
        
        if (isset($_POST['firebase_token'])) {
            $firebase_token = "";
            if (isset($_POST['firebase_token'])) {
                $firebase_token = $this->input->post('firebase_token');
            }
            
            if (!($email == "" && $firebase_token == "")) {
                $this->load->model('musertrack_firebase_model');
                if ($email != "" && $firebase_token != "") {
                    $found_num_rows = $this->musertrack_firebase_model->find($email, $firebase_token);
                    if ($found_num_rows > 0) {
                        $this->musertrack_firebase_model->delete($email, $firebase_token);
                    }
                    $this->musertrack_firebase_model->insert($email, $firebase_token, $is_android);
                } else if ($email != "") {
                    $found_num_rows = $this->musertrack_firebase_model->find($email, "");
                    if ($found_num_rows > 0) {
                        $this->musertrack_firebase_model->delete($email, "");
                    }
                    $this->musertrack_firebase_model->insert($email, "", $is_android);
                } else if ($firebase_token != "") {
                    $found_num_rows = $this->musertrack_firebase_model->find("", $firebase_token);
                    if ($found_num_rows > 0) {
                        $this->musertrack_firebase_model->delete("", $firebase_token);
                    }
                    $this->musertrack_firebase_model->insert("", $firebase_token, $is_android);
                }
            }
        }
        
        $this->save_device_detail('update_last_seen');
        
        $data['status'] = "fake_return_data";
        echo json_encode($data);
    }
    
    //mendapatkan info deposit customer. dipakai oleh aplikasi customer.
    public function get_deposit()
    {
        
        write_file('./uploads/logs/get_deposit_log.php', date("Y-m-d H:i:s a") . " params " . json_encode($_POST) . PHP_EOL . PHP_EOL, 'a+');
        
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $userId = $this->input->post('userId');
        $token  = $this->input->post('token');
        
        $this->token_validate($userId, $token); //memvalidasi token yg dikirim dari aplikasi customer.
        
        write_file('./uploads/logs/get_deposit_log.php', date("Y-m-d H:i:s a") . " token validated " . PHP_EOL . PHP_EOL, 'a+');
        
        $this->load->model('tcustdeposit_model');
        
        $response                = new stdClass();
        $response->stat          = 1;
        $response->depositAmount = $this->tcustdeposit_model->get_deposit($userId); //mendapatkan besarnya deposit untuk user sesuai id
        
        write_file('./uploads/logs/get_deposit_log.php', date("Y-m-d H:i:s a") . " response " . json_encode($response) . PHP_EOL . PHP_EOL, 'a+');
        
        echo json_encode($response);
    }
    
    //mendapatkan info diskon sesuai kode voucher yg diinput customer. dipakai oleh aplikasi customer.
    public function get_discount()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $userId = $this->input->post('userId');
        $token  = $this->input->post('token');
        
        $this->token_validate($userId, $token);
        
        $response = new stdClass();
        
        if ($userId == 5874 //rica. tidak boleh pakai voucher apapun lagi
            || $userId == 6059 //rica. tidak boleh pakai voucher apapun lagi
            || $userId == 6070 //rica. tidak boleh pakai voucher apapun lagi
            ) {
            $response->stat = -3;
            echo json_encode($response);
            return;
        }
        
        $this->load->model('torderdoc_model');
        $phone = $this->torderdoc_model->get_phone_by_userid($userId);
        
        if ($phone != "0") {
            if (strpos($phone, "83877562997") !== FALSE //nomor hape rica. tidak boleh pakai voucher apapun lagi
                || strpos($phone, "81293924149") !== FALSE //nomor hape rica. tidak boleh pakai voucher apapun lagi
                ) {
                $response->stat = -3;
                echo json_encode($response);
                return;
            }
        }
        
        $voucherCode = strtolower($this->input->post('voucherCode'));
        
        $this->load->model('tvoucher_model');
        $voucher_row = $this->tvoucher_model->get($voucherCode);
        
        $this->load->model('tcustdiscount_model');
        if ($voucher_row != array()) {
            $found = $this->tcustdiscount_model->find($userId, $voucher_row->id); //mencari apakah user sudah pernah memakai kode voucher
            
            if ($found) { //jika ketemu, maka user tidak boleh memakai voucher tsb
                $response->stat = -1;
                echo json_encode($response);
            } else {
                
                $diffInSeconds = strtotime(date("Y-m-d H:i:s")) - strtotime($voucher_row->expiredOn);
                
                if ($diffInSeconds > 0) {
                    if (isset($_POST['version_code'])) {
                        $response->stat = -4;
                        $response->msg  = "kode voucher sudah expired";
                        echo json_encode($response);
                    } else {
                        $response->stat = 0;
                        echo json_encode($response);
                    }
                } else {
                    $response->stat          = 1;
                    $response->discountKind  = DISCOUNT_KIND_VOUCHER;
                    $response->voucherId     = $voucher_row->id;
                    $response->voucherCode   = $voucher_row->code;
                    $response->voucherAmount = $voucher_row->amount;
                    echo json_encode($response);
                }
            }
        } else {
            $this->load->model('muserlogin_model');
            $referral_row = $this->muserlogin_model->find_referral(strtolower($voucherCode));
            
            if ($referral_row != array()) {
                if ($userId == $referral_row->id) {
                    $response->stat = -2;
                    echo json_encode($response);
                } else {
                    $found = $this->tcustdiscount_model->find_referral($userId, $voucherCode, $referral_row->id);
                    
                    if ($found) {
                        $response->stat = -1;
                        echo json_encode($response);
                    } else {
                        $found = $this->tcustdiscount_model->find_referral2($userId, $voucherCode);
                        if ($found) {
                            $response->stat = -3;
                            echo json_encode($response);
                        } else {
                            $this->load->model('treferral_model');
                            $referral = $this->treferral_model->get();
                            
                            $response->stat          = 1;
                            $response->discountKind  = DISCOUNT_KIND_REFERRAL_CUST;
                            $response->voucherId     = 0;
                            $response->voucherCode   = $voucherCode;
                            $response->voucherAmount = $referral->amount;
                            echo json_encode($response);
                        }
                    }
                }
            } else {
                $this->load->model('muserlogin_vendor_model');
                $referral_vendor_row = $this->muserlogin_vendor_model->find_referral(strtolower($voucherCode));
                
                if ($referral_vendor_row == array()) {
                    $response->stat = 0;
                    echo json_encode($response);
                } else {
                    $found = $this->tcustdiscount_model->find_referral($userId, $voucherCode, $referral_vendor_row->id);
                    
                    if ($found) {
                        $response->stat = -1;
                        echo json_encode($response);
                    } else {
                        $this->load->model('treferral_vendor_model');
                        $referral = $this->treferral_vendor_model->get();
                        
                        $response->stat             = 1;
                        $response->discountKind     = DISCOUNT_KIND_REFERRAL_VENDOR;
                        $response->voucherId        = 0;
                        $response->voucherCode      = $voucherCode;
                        $response->voucherAmount    = $referral->amount;
                        $response->vendorIdentifier = $referral_vendor_row->username;
                        echo json_encode($response);
                    }
                }
            }
        }
    }
    
    //mendapatkan info diskon sesuai kode voucher yg diinput customer. dipakai oleh aplikasi customer.
    public function get_discount2()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $userId = $this->input->post('userId');
        $token  = $this->input->post('token');
        
        
        // if list area vendor not null
        
        $latUser     = isset($_POST['latUser']) ? $_POST['latUser'] : '';
        $longUser    = isset($_POST['longUser']) ? $_POST['longUser'] : '';
        $area        = isset($_POST['area']) ? $_POST['area'] : '';
        $listProduct = isset($_POST['listProduct']) ? $_POST['listProduct'] : '';
        
        
        
        $this->token_validate($userId, $token);
        
        $response = new stdClass();
        
        if ($userId == 5874 //rica. tidak boleh pakai voucher apapun lagi
            || $userId == 6059 //rica. tidak boleh pakai voucher apapun lagi
            || $userId == 6070 //rica. tidak boleh pakai voucher apapun lagi
            ) {
            $response->stat = -3;
            echo json_encode($response);
            return;
        }
        
        $this->load->model('torderdoc_model');
        $phone = $this->torderdoc_model->get_phone_by_userid($userId);
        
        if ($phone != "0") {
            if (strpos($phone, "83877562997") !== FALSE //nomor hape rica. tidak boleh pakai voucher apapun lagi
                || strpos($phone, "81293924149") !== FALSE //nomor hape rica. tidak boleh pakai voucher apapun lagi
                ) {
                $response->stat = -3;
                echo json_encode($response);
                return;
            }
        }
        
        $voucherCode = strtolower($this->input->post('voucherCode'));
        
        
        
        $this->load->model('tvoucher_model');
        $voucher_row = $this->tvoucher_model->get($voucherCode);
        
        $this->load->model('tcustdiscount_model');
        if ($voucher_row != array()) {
            $found = $this->tcustdiscount_model->find($userId, $voucher_row->id); //mencari apakah user sudah pernah memakai kode voucher
            
            if ($found) { //jika ketemu, maka user tidak boleh memakai voucher tsb
                $response->stat = -1;
                echo json_encode($response);
            } else {
                
                $diffInSeconds = strtotime(date("Y-m-d H:i:s")) - strtotime($voucher_row->expiredOn);
                
                if ($diffInSeconds > 0) {
                    if (isset($_POST['version_code'])) {
                        $response->stat = -4;
                        $response->msg  = "kode voucher sudah expired";
                        echo json_encode($response);
                    } else {
                        $response->stat = 0;
                        echo json_encode($response);
                    }
                } else {
                    $response->stat          = 1;
                    $response->discountKind  = DISCOUNT_KIND_VOUCHER;
                    $response->voucherId     = $voucher_row->id;
                    $response->voucherCode   = $voucher_row->code;
                    $response->voucherAmount = $voucher_row->amount;
                    echo json_encode($response);
                }
            }
        } else {
            $this->load->model('muserlogin_model');
            $referral_row = $this->muserlogin_model->find_referral(strtolower($voucherCode));
            
            if ($referral_row != array()) {
                if ($userId == $referral_row->id) {
                    $response->stat = -2;
                    echo json_encode($response);
                } else {
                    $found = $this->tcustdiscount_model->find_referral($userId, $voucherCode, $referral_row->id);
                    
                    if ($found) {
                        $response->stat = -1;
                        echo json_encode($response);
                    } else {
                        $found = $this->tcustdiscount_model->find_referral2($userId, $voucherCode);
                        if ($found) {
                            $response->stat = -3;
                            echo json_encode($response);
                        } else {
                            $this->load->model('treferral_model');
                            $referral = $this->treferral_model->get();
                            
                            $response->stat          = 1;
                            $response->discountKind  = DISCOUNT_KIND_REFERRAL_CUST;
                            $response->voucherId     = 0;
                            $response->voucherCode   = $voucherCode;
                            $response->voucherAmount = $referral->amount;
                            echo json_encode($response);
                        }
                    }
                }
            } else {
                $this->load->model('muserlogin_vendor_model');
                
                // mapping radius user and vendor available or not
                $referral_vendor_row         = $this->muserlogin_vendor_model->find_referral_detail(strtolower($voucherCode));
                $referral_vendor_row->radius = $referral_vendor_row->radius + RADIUS_TOLERANSI;
                $radiusUser                  = 0;
                if ($latUser != null and $longUser != null) {
                    $radiusUser = $this->distance($latUser, $longUser, $referral_vendor_row->lat, $referral_vendor_row->lng);
                } else {
                    $radiusUser = 0;
                }
                
                // mapping product available
                $this->load->model('mproduct_model');
                $productAvailable = $this->mproduct_model->product_by_vendor_pv($referral_vendor_row->id, $listProduct);
                
                if ($referral_vendor_row == array()) {
                    $response->stat = 0;
                    echo json_encode($response);
                } else {
                    if ($radiusUser < $referral_vendor_row->radius and $productAvailable != null) {
                        $found = $this->tcustdiscount_model->find_referral($userId, $voucherCode, $referral_vendor_row->id);
                        
                        if ($found) {
                            $response->stat = -1;
                            echo json_encode($response);
                        } else {
                            $this->load->model('treferral_vendor_model');
                            $referral = $this->treferral_vendor_model->get();
                            
                            $response->stat             = 1;
                            $response->discountKind     = DISCOUNT_KIND_REFERRAL_VENDOR;
                            $response->voucherId        = 0;
                            $response->voucherCode      = $voucherCode;
                            $response->voucherAmount    = $referral->amount;
                            $response->vendorIdentifier = $referral_vendor_row->username;
                            echo json_encode($response);
                        }
                    } else {
                        $response->stat = 0;
                        echo json_encode($response);
                    }
                    
                }
            }
        }
    }
    
    // function kalkulasi radius user dengan vendor
    function distance($lat1, $lon1, $lat2, $lon2)
    {
        $theta = $lon1 - $lon2;
        $dist  = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist  = acos($dist);
        $dist  = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        // $unit = strtoupper($unit);
        
        return ($miles * 1.609344);
    }
    
    //untuk mendapatkan besarnya referral customer (kalau customer menyebar kode referralnya ke customer lain)
    public function get_referral_amount()
    {
        if (!isset($_POST['validator'])) {
            exit("No direct script access allowed");
        }
        
        $userId = $this->input->post('userId');
        $token  = $this->input->post('token');
        
        $this->token_validate($userId, $token);
        
        $this->load->model('treferral_model');
        $result = $this->treferral_model->get(); //mendapatkan besarnya nilai kode referral
        echo json_encode($result);
    }
    
    //add preferred vendor
    public function update_preferred_vendor()
    {
        $userId   = $this->input->post('userId');
        $docId    = $this->input->post('doc');
        $vendorId = $this->input->post('vendorid');
        
        $response = new stdClass();
        
        $response->stat = 0;
        
        $this->load->model('muserlogin_model');
        $response->stat = $this->muserlogin_model->update_preferred_vendor_id($userId, $vendorId) ? 1 : 0;
        
        if ($response->stat) {
            //load model
            $this->load->model('torderdoc_model');
            $response->stat = $this->torderdoc_model->update_preferred_vendor($docId, $vendorId);
        }
        
        echo json_encode($response);
    }
    
    //mengupdate status orderan dari halaman peta (http://tukang-backend.com/v2/tasks/get_new_order_locations)
    //klik di salah satu orderan -> di dialog yg muncul, klik link change di baris pertama di kiri atas dialog.
    public function update_an_order()
    {
        
        $docId    = $this->input->post('doc');
        $status   = $this->input->post('status');
        $masterId = $this->input->post('acceptor');
        $workerId = $this->input->post('sender');
        
        $this->load->model('torderdoc_model');
        $this->load->model('torder_accepted_model');
        $this->load->model('tvendordeposit_model');
        
        $response = new stdClass();
        
        $response->stat = 1;
        $response->msg  = "";
        
        $status = intval($status);
        
        if ($status == 0) {
            $r = $this->torderdoc_model->update_status(array(
                0,
                $docId
            ), 1);
            if ($r) {
                $this->torder_accepted_model->remove(array(
                    $docId
                ));
                
                $this->load->model('tcustdiscount_model');
                $this->tcustdiscount_model->activate($docId);
                
                $this->load->model('tcustdeposit_model');
                $this->tcustdeposit_model->activate($docId);
                
                $this->tvendordeposit_model->activate($docId);
            } else {
                $response->stat = 0;
                $response->msg  = "failed to update order to 'new'";
            }
        }
        
        if ($status == -1 || $status == -2 || $status == -3) {
            $r = $this->torderdoc_model->update_status(array(
                $status,
                $docId
            ), 1);
            if (!$r) {
                $response->stat = 0;
                $response->msg  = "failed to update order to 'canceled' or 'timeout'";
            } else {
                $this->load->model('tcustdiscount_model');
                $this->tcustdiscount_model->delete($docId);
                
                $this->load->model('tcustdeposit_model');
                $this->tcustdeposit_model->delete($docId);
                
                $this->tvendordeposit_model->delete($docId);
            }
        }
        
        if ($status == 1) {
            $r = $this->torderdoc_model->update_status(array(
                1,
                $docId
            ), 1);
            if ($r) {
                $this->torder_accepted_model->remove(array(
                    $docId
                ));
                $affected = $this->torder_accepted_model->insert(array(
                    $masterId,
                    $docId,
                    0
                ));
                if ($affected == 0) {
                    $response->stat = 0;
                    $response->msg  = "failed to update order to 'accepted'";
                }
            } else if (!$r) {
                $response->stat = 0;
                $response->msg  = "failed to update order to 'accepted'";
            }
        }
        
        if ($status == 2 || $status == 3 || $status == 4 || $status == 5) {
            $r = $this->torderdoc_model->update_status(array(
                $status,
                $docId
            ), 1);
            if ($r) {
                $oldMasterId = $this->torder_accepted_model->getMasterId($docId);
                $this->torder_accepted_model->remove(array(
                    $docId
                ));
                $affected = $this->torder_accepted_model->insert(array(
                    $masterId,
                    $docId,
                    $workerId
                ));
                
                if ($affected == 0) {
                    $response->stat = 0;
                    $response->msg  = "failed to update order to 'assigned','go','start',or 'done'";
                } else {
                    $this->tvendordeposit_model->update(array(
                        $masterId,
                        $docId,
                        $oldMasterId
                    ));
                    
                    if (strpos(base_url(), "localhost") === FALSE) {
                        $this->send_invoice($docId);
                    }
                }
                
                if ($status == 3) {
                    $this->load->model('muserlogin_worker_model');
                    $this->muserlogin_worker_model->update_worker_status(array(
                        1,
                        $workerId
                    ));
                }
                
                if ($status == 5) {
                    $this->load->model('muserlogin_worker_model');
                    $this->muserlogin_worker_model->update_worker_status(array(
                        0,
                        $workerId
                    ));
                }
                
            } else if (!$r) {
                $response->stat = 0;
                $response->msg  = "failed to update order to 'assigned','go','start',or 'done'";
            }
        }
        
        if ($status == 5) {
            if ($this->input->post('should_give_bonus') == 1 && $masterId != 29 //29 = id rajaair
                ) {
                $this->tvendordeposit_model->deactivate_outbound_bonus_if_exists(array(
                    $docId
                ));
                $this->tvendordeposit_model->insert_outbound_bonus(array(
                    $masterId,
                    $docId
                ));
            }
        }
        
        if ($status > 0) {
            $this->torderdoc_model->clear_vendorsbyranking(array(
                $docId
            ));
        }
        
        echo json_encode($response);
    }
    
    //untuk mengedit detail transaksi sebuah orderan. dipakai di link http://tukang-backend.com/v2/tasks/get_new_order_locations
    //klik di salah satu orderan -> di dialog yg muncul, klik link "Edit Products". ada di kiri bawah dialog.
    //lalu klik "edit" di detail transaksi yg mau diubah
    public function edit_save_trans()
    {
        
        $param = new stdClass();
        
        $param->trans_id         = $this->input->post('trans_id');
        $param->trans_qty        = $this->input->post('trans_qty');
        $param->trans_product_id = $this->input->post('trans_pid');
        $param->trans_price      = $this->input->post('trans_price');
        $param->trans_docid      = $this->input->post('trans_docid');
        
        $this->load->model('tordertrans_model');
        
        $response = $this->tordertrans_model->update($param);
        echo json_encode($response);
    }
    
    //untuk menghapus detail transaksi sebuah orderan. dipakai di link http://tukang-backend.com/v2/tasks/get_new_order_locations
    //klik di salah satu orderan -> di dialog yg muncul, klik link "Edit Products". ada di kiri bawah dialog.
    //lalu klik "remove" di detail transaksi yg mau dihapus
    public function edit_remove_trans()
    {
        
        $param = new stdClass();
        
        $param->trans_id    = $this->input->post('trans_id');
        $param->trans_docid = $this->input->post('trans_docid');
        
        $this->load->model('tordertrans_model');
        
        $response = $this->tordertrans_model->remove($param);
        echo json_encode($response);
    }
    
    //untuk menambahkan detail transaksi sebuah orderan. dipakai di link http://tukang-backend.com/v2/tasks/get_new_order_locations
    public function add_save_trans()
    {
        $param = new stdClass();
        
        $param->trans_qty        = $this->input->post('trans_qty');
        $param->trans_product_id = $this->input->post('trans_pid');
        $param->trans_price      = $this->input->post('trans_price');
        $param->trans_docid      = $this->input->post('trans_docid');
        
        $this->load->model('tordertrans_model');
        
        $response = $this->tordertrans_model->insert_crud($param);
        echo json_encode($response);
    }
    
    //untuk mengedit tanggal orderan. dipakai di link http://tukang-backend.com/v2/tasks/get_new_order_locations
    //klik di salah satu orderan -> di dialog yg muncul, klik link "Edit Products". ada di kiri bawah dialog.
    //lalu klik link "Add Product" yg muncul.
    public function save_date()
    {
        $this->load->model('torderdoc_model');
        
        $response = $this->torderdoc_model->update_time(array(
            $this->input->post('date') . " 00:00:00",
            $this->input->post('time'),
            $this->input->post('docid')
        ));
        echo json_encode($response);
    }
    
    //untuk mengedit notes di orderan. dipakai di link http://tukang-backend.com/v2/tasks/get_new_order_locations
    //klik di salah satu orderan -> di dialog yg muncul, klik link "Edit Notes". ada di kiri bawah dialog.
    public function save_notes()
    {
        $this->load->model('torderdoc_model');
        
        $response = $this->torderdoc_model->update_notes(array(
            $this->input->post('notes'),
            $this->input->post('orderid')
        ));
        echo json_encode($response);
    }
}

?>
