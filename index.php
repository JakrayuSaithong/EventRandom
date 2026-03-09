<?php
session_start();
include("./config/functions.php");

if (isset($_GET['DataE'])) {

    $JsonText = decryptIt($_GET['DataE']);
	$JSOnArr = json_decode($JsonText, true);
	$now = time();

    $dataTime = (is_array($JSOnArr) && isset($JSOnArr['date_U'])) ? (int)$JSOnArr['date_U'] : 0;
	if (($now - $dataTime) > 3600) {
		session_unset();
		session_destroy();

		echo "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Session Expired</title>
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head>
        <body>
        <script>
            Swal.fire({
                icon: 'warning',
                title: 'หมดเวลาการใช้งาน',
                text: 'Session หมดอายุแล้ว กรุณาเข้าสู่ระบบใหม่',
                confirmButtonText: 'ตกลง',
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then(() => {
                window.close();
                window.location.href = 'about:blank';
            });
        </script>
        </body>
        </html>
        ";
		exit();
	}

    $_SESSION['emp_code'] = $JSOnArr['auth_user_name'];

    // if ($JSOnArr['auth_user_name']) {
    //     $Users_Username = $JSOnArr['auth_user_name'];

    //     $get_emp_detail = "https://innovation.asefa.co.th/applications/ds/emp_list_code";
	// 	$chs = curl_init();
	// 	curl_setopt($chs, CURLOPT_URL, $get_emp_detail);
	// 	curl_setopt($chs, CURLOPT_RETURNTRANSFER, true);
	// 	curl_setopt($chs, CURLOPT_SSL_VERIFYHOST, false);
	// 	curl_setopt($chs, CURLOPT_SSL_VERIFYPEER, false);

	// 	curl_setopt($chs, CURLOPT_POST, 1);
	// 	curl_setopt($chs, CURLOPT_POSTFIELDS, ["emp_code" => $Users_Username]);
	// 	$emp = curl_exec($chs);
	// 	curl_close($chs);

	// 	$empdata   =   json_decode($emp);
    //     $_SESSION['emp_code'] = $empdata[0]->emp_code;
    // }

    // Redirect to admin page after session setup
    header('Location: admin.php');
    exit;
}
else {
    echo "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Session Expired</title>
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head>
        <body>
        <script>
            Swal.fire({
                icon: 'warning',
                title: 'หมดเวลาการใช้งาน',
                text: 'Session หมดอายุแล้ว กรุณาเข้าสู่ระบบใหม่',
                confirmButtonText: 'ตกลง',
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then(() => {
                window.close();
                window.location.href = 'about:blank';
            });
        </script>
        </body>
        </html>
    ";
    exit();
}