
<?php
ini_set('max_execution_time', 0);

if (PHP_SAPI == "cli") {
    $run_mode = "cmd";
    $cwd = dirname(realpath(__FILE__));
    $arr_dirs = explode("/", $cwd);
    $DOCUMENT_ROOT = "/$arr_dirs[1]/$arr_dirs[2]/$arr_dirs[3]";
    $br = "\n";
    
    function lflush()
    {}
} else {
    $run_mode = "web";
    $DOCUMENT_ROOT = $_SERVER['DOCUMENT_ROOT'];
    $br = "<br>";
    
    function lflush()
    {
        ob_flush();
        flush(); 
    }
    header('Content-type: text/html; charset=utf-8');
    echo "<!DOCTYPE html>
		<html>
		<head>
			<title>EFS - Delivery Status Update from the Couriers</title>
			<link rel=\"stylesheet\" href=\"//cdnjs.cloudflare.com/ajax/libs/bootswatch/3.3.6/cerulean/bootstrap.min.css\">
		</head>
		<body>";
}
$lflush = 'lflush';

include_once "$_SERVER[DOCUMENT_ROOT]/common/dbConn_test.php";
include_once "$_SERVER[DOCUMENT_ROOT]/common/fn_common.php";
require_once "$_SERVER[DOCUMENT_ROOT]/function/fn_sts_upd.php";

require_once "$_SERVER[DOCUMENT_ROOT]/function/classHtmlParser.php";
require_once "$_SERVER[DOCUMENT_ROOT]/function/simple_html_dom.php";

$_SESSION["cust_code"] = empty($_SESSION["cust_code"]) ? 0 : $_SESSION["cust_code"]; // 0: admin;

if (PHP_SAPI !== "cli") {
    // ob_flush()揶쏉옙 占쎈짗占쎌삂占쎈릭占쎈뮉 鈺곌퀗援뷂옙占� ob_get_level() 占쎌뵠 1占쎌뵬 占쎈르
    ob_end_flush(); // ob_get_level() -1 揶쏅Ŋ�꺖
    ob_start(); // ob_get_level() +1 筌앹빓占�
}

$min_sleep_msec = 0; // Minimum sleep time in microsecond
$max_sleep_msec = 0; // Maximum sleep time in microsecond, 0 for no sleep

if (PHP_SAPI == "cli") {
    echo "Usage: $argv[0] line_name days{$br}";
    $line_name = isset($argv[1]) ? trim($argv[1]) : ""; // Courier name
    $days = isset($argv[2]) ? trim($argv[2]) : ""; // Days before from today
} else {
    $URL = $_SERVER['SCRIPT_FILENAME'];
    echo "{$br}Usage:";
    echo "{$br}&nbsp;&nbsp;$URL?line_name=<font color='blue'><i>Courier_name</i></font>&amp;days=<font color='blue'><i>Numeric_days_before</i></font>";
    echo "{$br}&nbsp;&nbsp;$URL?line_name=<font color='blue'><i>Courier_name</i></font>&amp;dtfm=<font color='blue'><i>YYYY.MM.DD</i></font>&amp;dtto=<font color='blue'><i>YYYY.MM.DD</i></font>";
    echo "{$br}&nbsp;&nbsp;$URL?tr_no=<font color='blue'><i>tracking_number</i></font>";
    echo "{$br}&nbsp;&nbsp;$URL?hawb_no=<font color='blue'><i>tracking_number</i></font>";
    echo "{$br}&nbsp;&nbsp;- dtfm & dtto: Line Change date";
    echo "{$br}";
    
    $dtfm = isset($_REQUEST["dtfm"]) ? trim($_REQUEST["dtfm"]) : ""; // query date from (YYYY.MM.DD)
    $dtto = isset($_REQUEST["dtto"]) ? trim($_REQUEST["dtto"]) : ""; // query date to (YYYY.MM.DD)
    $tr_no = isset($_REQUEST["tr_no"]) ? trim($_REQUEST["tr_no"]) : (isset($_REQUEST["hawb_no"]) ? trim($_REQUEST["hawb_no"]) : ""); // tracking number either Ecargo or Co-worker
    $days = isset($_REQUEST['days']) ? trim($_REQUEST['days']) : ""; // Days from today
    $line_name = isset($_REQUEST['line_name']) ? strtoupper(trim($_REQUEST['line_name'])) : ""; // Courier name
}

if (empty($tr_no) && empty($line_name)) {
    die('line_name or tr_no(hawb_no) must be given.');
}

$work_date1 = date("Y.m.d", strtotime("-60 days"));
$work_date2 = date("Y.m.d", strtotime("-0 days"));
switch ($line_name) {
    case "TIKI": 
    case "EFSTH":
    case "EFSPH":
    case "EFSVN":
    case "EFSTW":
    case "EFSUS":
    case "SINGPOST":
    case "NINJAVAN":
    case "TWOGO":
    case "YTO":
    case "USPS":
    case "RAF":
        $work_date2 = date("Y.m.d");
        break;
    case "GTS":
    case "TCK":
    case "GTSTH":
        $work_date2 = date("Y.m.d");
        break;
    case "SKYNET":
        $work_date2 = date("Y.m.d", strtotime("-4 days"));
        break;
    case "YMT":
    case "YUPACK":
    case "EMS":
    case "EMSCD":
    case "K-PACKET":
    case "KPOST":
    case "LBC":
    case "LINECLEAR":
    case "DHL":
    case "MYDHL":
    case "CJGLS":
    case "SAGAWA":
    case "SSS":
    case "EFSRU":
    case "RUPOST":
    case "JHSS":
        break;
    default:
        // if (empty($tr_no))
        exit("{$br}The courier \"" . $line_name . "\" is unknown by this script.{$br}");
}

$dtfm = empty($dtfm) ? $work_date1 : $dtfm;
$dtto = empty($dtto) ? $work_date2 : $dtto;
$tr_no = empty($tr_no) ? '' : strtoupper($tr_no);
if (! empty($days)) {
    $dtfm = date("Y.m.d", strtotime("-" . $days . " days"));
    $dtto = $work_date2;
}

echo "{$br}Working for the Line changed date: from $dtfm & to $dtto";
echo "{$br}{$br}Started at " . date("Y-m-d H:i:s") . "{$br}";
$lflush();
echo "{$br}line_name: " . $line_name;
// $sql = "select top 10 a.hawb_no, a.line_name, a.ref_no1, a.line_date, b.s_ctry, b.c_ctry , c.p_date as pu_date, d.adm_code as branch_code
// from ship_ref as A
// inner join tst017 as B on b.hawb_no = a.hawb_no
// inner join tst007 as C on c.hawb_no = a.hawb_no and c.p_title = 3
// left join tcb07 as D on d.adm_gubn = '04' and d.adm_sname = a.line_name and d.admitem1 = b.c_ctry
// where a.line_type = 'DLVY' and a.io_type = 'OUT' and 0 < (select count(*) from tst007 where hawb_no = a.hawb_no and p_title = 3)
// and 0 = (select count(*) from tst007 where hawb_no = a.hawb_no and p_title in (42, 52, 74, 33)) and a.hawb_no not like '%_hist'
// and a.ref_no1 <> 'MAIL-UNREG' and a.line_name = 'SKYNET' and a.line_date between '2017.08.01' and '2017.08.30'
// order by line_date desc, a.hawb_no desc";
$sql = "select a.hawb_no, a.line_name, a.ref_no1, a.line_date, b.s_ctry, b.c_ctry , c.p_date as pu_date, d.adm_code as branch_code, b.s_add1, b.assigned_no, b.order_no, b.seller_id
                , (select top 1 seller_key from seller_ids where cust_code = '000004' and shop_login = 'TIKI' ) as tiki_token				
                from ship_ref as A
				inner join tst017 as B on b.hawb_no = a.hawb_no
				inner join tst007 as C on c.hawb_no = a.hawb_no and c.p_title = 3
				left join tcb07 as D on d.adm_gubn = '04' and d.adm_sname = a.line_name and d.admitem1 = b.c_ctry
				where a.line_type = 'DLVY' and a.io_type = 'OUT'
				and 0 < (select count(*) from tst007 where hawb_no = a.hawb_no and p_title = 3)
				and 0 = (select count(*) from tst007 where hawb_no = a.hawb_no and p_title in (42, 52, 74, 33)) 
				and a.hawb_no not like '%_hist' and a.ref_no1 <> 'MAIL-UNREG'";

if (! empty($tr_no)) {
    $sql .= " and '$tr_no' in (a.hawb_no, a.ref_no1)";
} else {
    if (strpos('EMS:K-PACKET:KPOST:', $line_name) !== false) {
        $sql .= " and a.line_name in ('EMS', 'K-PACKET', 'EMSCD', 'KPOST')";
    } else {
        $sql .= " and a.line_name = '$line_name'";
    }
    if ($dtfm == $dtto) {
        $sql .= " and a.line_date = '$dtfm'";
    } else {
        $sql .= " and a.line_date between '$dtfm' and '$dtto'";
    }
}
$sql .= "order by line_date asc, a.hawb_no desc";

// echo "<br>sql=$sql";
// die();
$rs = $msdbConn->query($sql, PDO::FETCH_ASSOC)->fetchAll();
$numRows = count($rs);

echo "{$br}Selected record(s) count: $numRows{$br}";
$lflush();

// $numRows = 0;
if ($numRows == 0) {
    die("{$br}Nothing to perform.");
}

$row_no = 1;
$status_memo = "";
foreach ($rs as $row) {
    $hawb_no = strtoupper(trim($row['hawb_no']));
    $line_name = strtoupper(trim($row['line_name']));
    $ref_no1 = strtoupper(trim($row['ref_no1']));
    $line_date = trim($row['line_date']);
    $s_ctry = strtoupper(trim($row['s_ctry'])); // Sndr country code: KR, US and etc.
    $c_ctry = strtoupper(trim($row['c_ctry'])); // Cnee country code: SG, MY and etc.
    $branch_code = strtoupper(trim($row['branch_code'])); // Branch code: LNC, TQMY and etc
    $s_add1 = trim($row['s_add1']);
    $assigned_no = trim($row['assigned_no']);
    $order_no = trim($row['order_no']);
    $seller_id = trim($row['seller_id']);
    $tiki_token = trim($row['tiki_token']);
    
    $show_progress = "Y";
    
    if ($line_name == 'TCK') {
        $line_name = 'GTS';
    }
    
    if ($line_name == 'LINECLEAR' and empty($ref_no1))
        $ref_no1 = $hawb_no;
        
    if ($line_name == 'NINJAVAN')
        $ref_no1 = $hawb_no;
            
    if ($line_name == 'RAF')
        $ref_no1 = $hawb_no;
    
    if ($line_name == 'JHSS')
        $ref_no1 = str_replace("d","",$ref_no1);
                
                if (empty($line_date))
                    $line_date = "NULL";
                    
                    echo "{$br}Seq# " . $row_no ++ . ", $hawb_no - $ref_no1 (line changed at $line_date),";
                    
                    if ($row_no != 1 and $max_sleep_msec > 0) {
                        echo " sleeping ...";
                        usleep(rand($min_sleep_msec, $max_sleep_msec)); // sleep for ramdom microseconds
                    }
                    $lflush();
                    
                    $response = takeStatus($line_name, $ref_no1, $c_ctry);
                    $resp_arr = json_decode($response, true);
                    
//                      echo '<pre>' . print_r ($resp_arr, true) . '</pre>';
//                      die();

                    switch ($line_name) {
                        case "LINECLEAR":
                            $rowCnt = $resp_arr['iTotalRecords'];
                            $data_arr = $resp_arr['aaData'];
                            $dataOdr = "DESC";
                            break;
                        case "EFSVN":
                        case "LBC":
                        case "NINJAVAN":
                        case "YMT":
                        case "YUPACK":
                        case "TWOGO":
                        case "CJGLS":
                        case "EFSRU":
                            $rowCnt = count($resp_arr);
                            $dataOdr = "ASC";
                            break;
                        case "KPOST":
                        case "K-PACKET":
                        case "EMS":
                        case "EMSCD":
                            if (isset($resp_arr['xsyncData']['error_code'])) {
                                echo " " . $resp_arr['xsyncData']['error_code'] . " ";
                                echo ($resp_arr['xsyncData']['message'] == '鈺곌퀬�돳野껉퀗�궢揶쏉옙 占쎈씨占쎈뮸占쎈빍占쎈뼄.') ? 'Tracking result is notfound.' : $resp_arr['xsyncData']['message'];
                                $rowCnt = 0;
                                break;
                            }
                            $c_ctry = $resp_arr['destcountrycd'];
                            $sendflightno = $resp_arr['sendflightno'];
                            $data_arr = $resp_arr['itemlist']['item']; // itemlist
                            $rowCnt = isset($data_arr[0]) ? count($data_arr) : (isset($data_arr) ? 1 : 0);
                            $dataOdr = "ASC";
                            break;
                        case "EMSCD":
                            $rowCnt = count($resp_arr['itemlist']['item']);
                            $dataOdr = "DESC";
                            break;
                        case "TIKI":
                        case "SINGPOST":
                            $rowCnt = count($resp_arr);
                            $dataOdr = "DESC";
                            break;
                        case "DHL":
                            $rowCnt = count($resp_arr['AWBInfo']['ShipmentInfo']['ShipmentEvent']);
                            $dataOdr = "ASC";
                            break;
                        case "MYDHL":
                            $rowCnt = count($resp_arr['AWBInfo']['ShipmentInfo']['ShipmentEvent']);
                            $dataOdr = "ASC";
                            break;
                        case "EFSPH":
                            $rowCnt = count($resp_arr['data'][0]['dlvyStatus']);
                            $dataOdr = "ASC";
                            break;
                        case "SKYNET":
                            $rowCnt = count($resp_arr);
                            // echo $rowCnt;
                            $dataOdr = "DESC";
                            break;
//                         case "YTO":
//                             $rowCnt = count($resp_arr['data']['trackingList']);
//                             $dataOdr = "DESC";
//                             break;
                        case "EFSTH":
                        case "YTO":
                            $rowCnt = count($resp_arr['data']['trackings'][0]['checkpoints']);
                            $dataOdr = "ASC";
                            break;
                        case "EFSTW":
                        case "RUPOST":
                            $rowCnt = count($resp_arr['data']['accepted'][0]['track_info']['tracking']['providers']['0']['events']);
                            $dataOdr = "DESC";
                            break;
                        case "GTS":
                        case "TCK":
                        case "GTSTH":
                        case "RAF":
                            $rowCnt = count($resp_arr['WSGET']['Event']);
                            $dataOdr = "ASC";
                            break;
                        case "USPS":
//                            $rowCnt = count($resp_arr['TrackInfo']['TrackDetail']);
                             $rowCnt = count($resp_arr['TrackInfo']['TrackSummary']);
                            $dataOdr = "asc";
                            break;
                        case "SAGAWA":
                        case "SSS":
                            // echo print_r($resp_arr['INFO'][0]);
                            // echo count($resp_arr['INFO']);
                            $rowCnt = count($resp_arr['INFO']);
                            
                            $dataOdr = "ASC";
                            break;
                        case "JHSS":
                            $rowCnt = count($resp_arr['tracking'][str_replace("D","",$ref_no1)]);
                            $dataOdr = "ASC";
                            break;
                        case "EFSUS":
                            $rowCnt = count($resp_arr['data'][0]['events']);
                            $dataOdr = "ASC";
                            break;
                        default:
                            die("<br>Requested courier '$line_name' is not supported, yet.");
                            continue;
                    }
                    
                    if ($rowCnt == 0)
                        continue;
                        
                        $alpha = ($dataOdr == "DESC") ? $rowCnt - 1 : 0;
                        $omega = ($dataOdr == "DESC") ? 0 : $rowCnt - 1;
                        
                        $incrNum = ($alpha == $omega) ? 1 : ($omega - $alpha) / abs($alpha - $omega);
                        // die ();
                        
                        $jj = 0;
                        $isStsComp = false;
                        for ($i = $alpha; $i != ($omega + $incrNum); $i += $incrNum) {
                            switch ($line_name) {
                                case "LBC":
                                    $status_dtm = $resp_arr[$i]['status_dtm'];
                                    $status_name = $resp_arr[$i]['status_name'];
                                    $status_memo = $resp_arr[$i]['status_memo'];
                                    $saup_gubn = '09'; // PH
                                    $area = '09'; // PH
                                    break;
                                case "LINECLEAR":
                                    $status_dtm = $data_arr[$i]['LocalDateTime'];
                                    $status_name = $data_arr[$i]['PublicDescription'];
                                    $status_memo = $data_arr[$i]['LocationDescription'];
                                    $status_dtm = strtotime($status_dtm);
                                    $saup_gubn = $c_ctry;
                                    $area = $branch_code;
                                    break;
                                case "KPOST":
                                case "K-PACKET":
                                case "EMS":
                                case "EMSCD":
                                    $curr_arr = ($rowCnt == 1) ? $data_arr : $data_arr[$i];
                                    $status_dtm = $curr_arr['sortingdate'];
                                    $status_name = is_array($curr_arr['eventnm']) ? '' : $curr_arr['eventnm'];
                                    if (strpos(":접수:발송:발송교환국에도착:발송교환국에 도착:발송준비:", ":{$status_name}:") === false) {
                                        $status_memo = is_array($curr_arr['eventregiponm']) ? '' : $curr_arr['eventregiponm'];
                                    }
                                    
                                    $status_dtm = strtotime($status_dtm);
                                    $saup_gubn = (strpos(":접수:발송:발송교환국에도착:발송교환국에 도착:발송준비:운송사 인계:", ":{$status_name}:") === false) ? $c_ctry : $s_ctry;
                                    $area = (strpos(":접수:발송:발송교환국에도착:발송교환국에 도착:발송준비:운송사 인계:", ":{$status_name}:") === false) ? $c_ctry : $s_ctry; // empty ( $branch_code ) ? substr ( $line_name, 0, 4 ) : $branch_code;
                                    if (strpos("부천우편집중국", $curr_arr['eventregiponm']) !== false) {
                                        $saup_gubn = $s_ctry;
                                        $area = $s_ctry;
                                    }
                                    
                                    break;
                                case "TIKI":
                                    $status_memo = $resp_arr['response']['0']['history'][$i]['noted'];
                                    $status_name = $resp_arr['response']['0']['history'][$i]['status'];
                                    $status_dtm = strtotime($resp_arr['response']['0']['history'][$i]['entry_date']);
                                    $saup_gubn = "03";
                                    $area = "03";
                                    break;
                                case "DHL":
                                    $status_dtm = $resp_arr['AWBInfo']['ShipmentInfo']['ShipmentEvent'][$i] ['Date'] . " " . $resp_arr['AWBInfo']['ShipmentInfo']['ShipmentEvent'][$i] ['Time'];
                                    $status_dtm = strtotime($status_dtm);
                                    $status_name = $resp_arr ['AWBInfo']['ShipmentInfo']['ShipmentEvent'][$i] ['ServiceEvent']['Description'];
                                    $area_code = $resp_arr ['AWBInfo']['ShipmentInfo']['ShipmentEvent'][$i] ['ServiceArea']['ServiceAreaCode'];
                                    
                                    $area = $c_ctry;
                                    $saup_gubn = $c_ctry;
                                    
                                    foreach (array( ' at ', ' OF ', ' - ', ' through ') as $key => $value)
                                        $status_memo = (strstr($status_name, $value) !== false) ? explode($value, $status_name)[1] : "";
                                        foreach (array( ' at ', ' OF ', ' - ', ' through ') as $key => $value)
                                            $status_name = (strstr($status_name, $value) !== false) ? explode($value, $status_name)[0] : $status_name;
                                            break;
                                case "MYDHL":
                                    $status_dtm = $resp_arr['AWBInfo']['ShipmentInfo']['ShipmentEvent'][$i] ['Date'] . " " . $resp_arr['AWBInfo']['ShipmentInfo']['ShipmentEvent'][$i] ['Time'];
                                    $status_dtm = strtotime($status_dtm);
                                    $status_name = $resp_arr ['AWBInfo']['ShipmentInfo']['ShipmentEvent'][$i] ['ServiceEvent']['Description'];
                                    $area_code = $resp_arr ['AWBInfo']['ShipmentInfo']['ShipmentEvent'][$i] ['ServiceArea']['ServiceAreaCode'];
                                    
                                    $area = $c_ctry;
                                    $saup_gubn = $c_ctry;
                                    
                                    foreach (array( ' at ', ' OF ', ' - ', ' through ') as $key => $value)
                                        $status_memo = (strstr($status_name, $value) !== false) ? explode($value, $status_name)[1] : "";
                                        foreach (array( ' at ', ' OF ', ' - ', ' through ') as $key => $value)
                                            $status_name = (strstr($status_name, $value) !== false) ? explode($value, $status_name)[0] : $status_name;
                                            break;
                                case "EFSPH":
                                    $status_dtm = $resp_arr['data'][0]['dlvyStatus'][$i]['dateTimeUTC'];
                                    
                                    $status_date = substr($status_dtm, 0, 10);
                                    $date_arr = explode("-", $status_date);
                                    $date_arr1 = $date_arr[2];
                                    $date_arr2 = $date_arr[1];
                                    $status_date = '2022.' . $date_arr2 . '.' . $date_arr1;
                                    $status_time = substr($status_dtm, 11, 5);
                                    $status_name = $resp_arr['data'][0]['dlvyStatus'][$i]['statusCode'];
                                    $status_memo = $resp_arr['data'][0]['dlvyStatus'][$i]['statusRemark'];
                                    $saup_gubn = $resp_arr['data'][0]['dlvyStatus'][$i]['countryCode'];
                                    $area = $resp_arr['data'][0]['dlvyStatus'][$i]['countryCode'];
                                    break;
                                case "EFSVN":
                                    $status_dtm = $resp_arr[$i]['status_dtm'];
                                    $status_dtm = strtotime($status_dtm);
                                    $status_dtm = str_replace("/", ".", $status_dtm);
                                    $status_name = $resp_arr[$i]['status_name'];
                                    $status_memo = $resp_arr[$i]['status_memo'];
                                    $saup_gubn = 'VN'; // PH
                                    $area = 'VN'; // PH
                                    break;
                                case "NINJAVAN":
                                    $status_dtm = $resp_arr[$i]['updatedAt'];
                                    $status_dtm = strtotime($status_dtm);
                                    $status_name = $resp_arr[$i]['status'];
                                    $saup_gubn = $c_ctry; // 'VN';//PH
                                    $area = $c_ctry; // 'VN';//PH
                                    // echo "\$saup_gubn = " . $saup_gubn . "\$area = " . $area;
                                    break;
                                case "SAGAWA":
                                case "SSS":
                                    $status_name = $resp_arr['INFO'][$i]['STATUS'];
                                    $status_dtm = $resp_arr['INFO'][$i]['LCLDATE'] . " " . $resp_arr['INFO'][$i]['LCLTIME'];
                                    $status_dtm = strtotime($status_dtm);
                                    $status_memo = $resp_arr['INFO'][$i]['DETAIL'];
                                    // echo $status_dtm;
                                    $saup_gubn = 'JP';
                                    $area = 'JP';
                                    break;
                                case "SKYNET":
                                    $sk_track_no = $resp_arr[$i]['AWBNumber'];
                                    $status_name = $resp_arr[$i]['Description'];
                                    $status_memo = $resp_arr[$i]['Location'];
                                    $status_dtm = $resp_arr[$i]['EventDate'];
                                    // $status_dtm = explode("T",$status_dtm);
                                    $status_dtm = strtotime($status_dtm);
                                    
                                    // echo $status_dtm;
                                    
                                    $saup_gubn = 'MY';
                                    $area = 'MY';
                                    break;
                                case "USPS":
//                                    $status_name = $resp_arr['TrackInfo']['TrackDetail'][$i]['Event'];
//                                    $status_dtm1 = $resp_arr['TrackInfo']['TrackDetail'][$i]['EventDate'];
//                                    $status_dtm2 = $resp_arr['TrackInfo']['TrackDetail'][$i]['EventTime'];
//                                    $status_memo = $resp_arr['TrackInfo']['TrackDetail'][$i]['EventCity'];
                                    
                                     $status_name = $resp_arr['TrackInfo']['TrackSummary']['Event'];
                                     $status_dtm1 = $resp_arr['TrackInfo']['TrackSummary']['EventDate'];
                                     $status_dtm2 = $resp_arr['TrackInfo']['TrackSummary']['EventTime'];
                                     $status_memo = $resp_arr['TrackInfo']['TrackSummary']['EventCity'];

                                    $status_dtm = $status_dtm1 . $status_dtm2;
                                    $status_dtm = strtotime($status_dtm);
                                    
                                    $saup_gubn = $c_ctry;
                                    $area = $c_ctry;
                                    // echo $status_name;
                                    break;
                                case "JHSS":
                                    $status_dtm = $resp_arr['tracking'][str_replace("D","",$ref_no1)][$i]['tracking_time'];
                                    $status_dtm = strtotime($status_dtm);
                                    $status_name = $resp_arr['tracking'][str_replace("D","",$ref_no1)][$i]['delivery_status'];
                                    $status_memo = $resp_arr['tracking'][str_replace("D","",$ref_no1)][$i]['delivery_office'];
                                    $saup_gubn = $c_ctry;
                                    $area = $c_ctry;
                                    

                                    break;
                                case "YMT":
                                case "YUPACK":
                                    $status_dtm = $resp_arr[$i]['status_dtm'];
                                    $status_name = $resp_arr[$i]['status_name'];
                                    $status_dtm = strtotime($status_dtm);
                                    $saup_gubn = $c_ctry;
                                    $area = $c_ctry;
                                    
                                    $status_date = empty($status_dtm) ? '' : date('Y.m.d', $status_dtm);
                                    $status_time = empty($status_dtm) ? '' : date('H:i', $status_dtm);
                                    
                                    break;
//                                 case "YTO":
//                                     $status_name = $resp_arr['data']['trackingList'][$i]['eventDetail'];
//                                     $status_dtm = $resp_arr['data']['trackingList'][$i]['eventTime'];
//                                     $status_dtm = strtotime($status_dtm);
//                                     $saup_gubn = $c_ctry;
//                                     $area = $c_ctry;
//                                     break;
                                case "EFSTH":
                                case "YTO":
                                    $status_name = $resp_arr['data']['trackings'][0]['checkpoints'][$i]['tag'];
                                    $status_dtm = $resp_arr['data']['trackings'][0]['checkpoints'][$i]['checkpoint_time'];
                                    $status_dtm = strtotime($status_dtm);
                                    $saup_gubn = $c_ctry;
                                    $area = $c_ctry;
                                    break;
                                case "EFSTW":
                                case "RUPOST":
                                    $status_name = $resp_arr['data']['accepted'][0]['track_info']['tracking']['providers']['0']['events'][$i]['description'];
                                    $status_dtm = $resp_arr['data']['accepted'][0]['track_info']['tracking']['providers']['0']['events'][$i]['time_iso'];
                                    $status_dtm = strtotime($status_dtm);
                                    $country_code = $resp_arr['data']['accepted'][0]['track_info']['tracking']['providers']['0']['events'][$i]['time_raw']['timezone'];
                                    
//                                     echo $status_time."</br>";
//                                     die();
                                    
                                    if ($country_code == "+02:00") {
                                        $saup_gubn = "DE";
                                        $area = "DE";
                                    } else {
                                        $saup_gubn = $c_ctry;
                                        $area = $c_ctry;                                        
                                    }
                                    break;
                                case "GTS":
                                case "TCK":
                                case "GTSTH":
                                case "RAF":
                                    if ($resp_arr['WSGET']['Event']['EventName'] == 'Arrived Hub') {
                                        $status_name = $resp_arr['WSGET']['Event']['EventID'];
                                        if (strpos(":90:91:", ":$status_code:") !== false)
                                            continue;
                                            $status_dtm = $resp_arr['WSGET']['Event']['EventDateTime'];
                                            $status_dtm = strtotime($status_dtm);
                                            $status_memo = $resp_arr['WSGET']['Event']['EventLocation'];
                                    } else {
                                        $status_name = $resp_arr['WSGET']['Event'][$i]['EventID'];
                                        if (strpos(":90:91:", ":$status_code:") !== false)
                                            continue;
                                            $status_dtm = $resp_arr['WSGET']['Event'][$i]['EventDateTime'];
                                            $status_dtm = strtotime($status_dtm);
                                            $status_memo = $resp_arr['WSGET']['Event'][$i]['EventLocation'];
                                    }
                                    $saup_gubn = $c_ctry;
                                    $area = $c_ctry;
                                    break;
                                case "CJGLS":
                                    $status_name = $resp_arr[$i]['status_name'];
                                    $status_dtm = $resp_arr[$i]['status_dtm'];
                                    $status_dtm = strtotime($status_dtm);
                                    
                                    foreach (array(
                                        ' - '
                                    ) as $key => $value)
                                        $status_name = (strstr($status_name, $value) !== false) ? explode($value, $status_name)[0] : $status_name;
                                        
                                        if (in_array($status_name, array(
                                            "Consol",
                                            "Departure"
                                        ))) {
                                            $saup_gubn = 'KR';
                                            $area = 'KR';
                                        } else {
                                            $saup_gubn = 'SG';
                                            $area = 'SG';
                                        }
                                        break;
                                case "EFSRU":
                                    $status_name = $resp_arr[0]['State'];
                                    $status_dtm = $resp_arr[0]['StatusDate'];
                                    $status_dtm = strtotime($status_dtm);
                                    $saup_gubn = 'RU';
                                    $area = 'RU';
                                    $status_memo = ($status_name == "29") ? "Таможенные данные работают" : "";
                                    
                                    break;
                                case "EFSUS":
                                    $status_dtm = $resp_arr['data'][0]['events'][$i]['EventTime'];
                                    $status_dtm = strtotime($status_dtm);
                                    $status_name = $resp_arr['data'][0]['events'][$i]['Label'];
                                    $saup_gubn = $c_ctry;
                                    $area = $c_ctry;
                                    break;
                                case "SINGPOST":
                                    $status_dtm = $resp_arr[$i]['status_dtm'];
                                    $status_dtm = strtotime($status_dtm);
                                    $status_name = $resp_arr[$i]['status_name'];
                                    $saup_gubn = $c_ctry;
                                    $area = $c_ctry;
                                    break;
                                default:
                                    continue;
                            }
                            
                            if ($line_name != 'EFSPH' ) {
                                $status_date = empty($status_dtm) ? '' : date('Y.m.d', $status_dtm);
                                $status_time = empty($status_dtm) ? '' : date('H:i', $status_dtm);
                            }
                            
                            if (empty($status_date))
                                continue;
                            
                                $status_code = match_status($line_name, $status_name);
//                                 echo $status_time;
                                // $status_memo .= ((strpos ( ":43:99:", ":$status_code:" ) !== false) and empty ( $status_memo )) ? " ($status_name)" : '';
                                
                                $str_msg = "Status ($status_name)";
                                if (PHP_SAPI == "cli") {
                                    echo "{$br}$str_msg";
                                } else {
                                    echo "{$br}<span style='padding-left: 50px;'>$str_msg";
                                }
                                if (empty($status_code)) {
                                    echo " Do nothing - passing. ($status_date $status_time).";
                                } else if ($status_code == "NA") {
                                    // 20170816
                                    // $str_msg = " is unknown ($status_date $status_time).";
                                    if (PHP_SAPI == "cli") {
                                        echo $str_msg;
                                    } else {
                                        echo "<font color='red'>$str_msg</font>";
                                    }
                                    // $sql = "insert Into partnership_status (partner, ref_no1, hawb_no, status, description) values
                                    // ('$line_name', '$ref_no1', '$hawb_no', 'NA','$status_name')";
                                    // $msdbConn->exec ( $sql );
                                } else {
                                    echo " -> [$status_code] ";
                                    echo ValueName("16", $status_code, 0); // ValueName($strKind, $strValue, $strOP)
                                    if ($isStsComp) {
                                        echo " >> Already completed the status and NO MORE STATUS UPDATE.";
                                    } else {
                                        sts_upd($hawb_no, $saup_gubn, $area, $status_code, $status_date, $status_time, $status_memo, $show_progress, $_SESSION["cust_code"]);
                                        // echo " - $hawb_no, $saup_gubn, $area, $status_code, $status_date, $status_time, $status_memo, $show_progress, " . $_SESSION ['cust_code'] ;
                                        
                                        if ($seller_id == "kravebeauty") {
                                            if (strpos(':33:31:', $status_code) !== false) {
                                                echo Send_shopify_track_no($order_no, $assigned_no, $status_code);
                                            }
                                        }
                                    }
                                }
                                if (PHP_SAPI !== "cli") {
                                    echo "</span>";
                                }
                                $jj ++;
                                $lflush();
                                $isStsComp = ($status_code == '33') ? true : false;
                        }
                        
                        if ($jj == 0) {
                            echo " Nothing to update.";
                        }
}

echo "{$br}{$br}Ended at " . date("Y-m-d H:i:s") . "{$br}";
if (PHP_SAPI !== "cli") {
    ob_end_flush();
    echo '</body></html>';
}

/**
 * ==================== End of Main script ============================
 */
function takeStatus($courier = '', $tr_no = '', $c_ctry = '')
{
//     echo "<br>courier = $courier, tr_no = $tr_no";
    $ch = curl_init();
    $headers = array(
        "Content-type text/xml; charset=UTF-8"
    );
    switch ($courier) {
        case "LINECLEAR":
            $url = "https://main.universe.com.my/Tracking/User/Paging?sTrackingNo={$tr_no}&sOrgId=line&sEcho=1";
            break;
        case "LBC":
            $url = "http://www.lbcexpress.com/kr/track/?tracking_no=" . $tr_no;
            break;
        case "YMT":
            $url = "https://global.igsp-kuronekoyamato.com/jp/tracking/" . substr($tr_no, 0, 4) . "-" . substr($tr_no, 4, 4) . "-" . substr($tr_no, 8, 4) . "?country=US&language=JP";
            break;
        case "SINGPOST":
            $url = "https://www.singpost.com/track-items?trackingid=$tr_no";
            break;
        case "YUPACK":
            $url = "https://trackings.post.japanpost.jp/services/srv/search/direct?reqCodeNo1=" . str_replace("A", "", strtoupper($tr_no)) . "&searchKind=S002&locale=en";
            break;
        case "KPOST":
        case "K-PACKET": // "K-PACKET"
        case "EMS":
        case "EMSCD":
            $apiKey = 'ef14f56a4eb3f7f561444810646138';
            $target = 'emsTrace';
            $url = "http://biz.epost.go.kr/KpostPortal/openapi?regkey={$apiKey}&target={$target}&query={$tr_no}";
            break;
        case "EFSUS":
            $url = "https://api-parceltracking.anchanto.com/openapi/v3/asendia/tracking-details-bulk";
            break;
        case 'TIKI':
            global $tiki_token;
            $token = $tiki_token;
            $url = "https://apix.mytiki.net/connote/mpds/history"; 
//             echo $token;
//             die();
            break;
        case 'DHL':
            $url = "https://xmlpi-ea.dhl.com/XMLShippingServlet";
            $xmlData = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
							<req:KnownTrackingRequest xmlns:req=\"http://www.dhl.com\"
								xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.dhl.com
								TrackingRequestKnown.xsd\">
								<Request>
									<ServiceHeader>
										<MessageTime>2002-06-25T11:28:56-08:00</MessageTime>
										<MessageReference>1234567890123456789012345678</MessageReference>
										<SiteID>v62_4j07a42nkh</SiteID>
										<Password>dJClc5ko9L</Password>
									</ServiceHeader>
								</Request>
								<LanguageCode>en</LanguageCode>
								<AWBNumber>$tr_no</AWBNumber>
								<LevelOfDetails>ALL_CHECK_POINTS</LevelOfDetails>
							</req:KnownTrackingRequest>";
            break;
        case 'MYDHL':
            $url = "https://xmlpi-ea.dhl.com/XMLShippingServlet";
            $xmlData = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
							<req:KnownTrackingRequest xmlns:req=\"http://www.dhl.com\"
								xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.dhl.com
								TrackingRequestKnown.xsd\">
								<Request>
									<ServiceHeader>
										<MessageTime>2002-06-25T11:28:56-08:00</MessageTime>
										<MessageReference>1234567890123456789012345678</MessageReference>
										<SiteID>v62_4j07a42nkh</SiteID>
										<Password>dJClc5ko9L</Password>
									</ServiceHeader>
								</Request>
								<LanguageCode>en</LanguageCode>
								<AWBNumber>$tr_no</AWBNumber>
								<LevelOfDetails>ALL_CHECK_POINTS</LevelOfDetails>
							</req:KnownTrackingRequest>";
            break;
        case 'EFSTH': 
            $curl = curl_init();
            
            $data = array(
                    "tracking_number" => $tr_no,
                    "slug" => "flashexpress"
            );
            
            curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://api.aftership.com/tracking/2024-07/trackings',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_SSL_VERIFYPEER => "false",
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => json_encode($data),
                    CURLOPT_HTTPHEADER => array(
                            'as-api-key: asat_5403b90b407d4ca7ae0a84c9f58d7084',
                            'Content-Type: application/json'
                    ),
            ));
            
            $response = curl_exec($curl);
            curl_close($curl);
            
            break;
        case 'YTO':
            $curl = curl_init();
            
            $data = array(
                    "tracking_number" => $tr_no,
                    "slug" => "yto"
            );
            
            curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://api.aftership.com/tracking/2024-07/trackings',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_SSL_VERIFYPEER => "false",
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => json_encode($data),
                    CURLOPT_HTTPHEADER => array(
                            'as-api-key: asat_5403b90b407d4ca7ae0a84c9f58d7084',
                            'Content-Type: application/json'
                    ),
            ));
            
            $response = curl_exec($curl);
            curl_close($curl);
            
            break;
        case 'EFSPH':
            $reqFunction = "getTrackStatus";
            $userLogin = "k2l";
            $apiKey = "b683b167dc179ee7e0a64aa0000180e6";
            date_default_timezone_set("Asia/Seoul");
            $ticket = hash("sha256", $reqFunction) . " " . hash("sha256", date("Ymd")) . " " . $userLogin . " " . hash("sha256", $apiKey);
            $ticket = base64_encode($ticket);
            $ticket = str_replace("=", "-", $ticket);
            
            $data = array(
                array(
                    'hawb_no' => $tr_no
                )
            );
            $data = json_encode($data); // [{"hawb_no":"DR1000185034"}]
            $data = array(
                'ticket' => $ticket,
                'data' => base64_encode($data)
            );
            $data = json_encode($data);
            
            $target_url = 'http://web.doora.co.kr/script/api/in/?getTrackStatus';
            
            break;
        case "EFSVN":
            $url = "https://5sao.ghn.vn/Tracking/ViewTracking/$tr_no/?";
            break;
        case "JHSS":
            $url = "https://impsys.ccbs-inc.jp/api/cutomTrackingApi";
            
            $data = array(
                "target" => array(
                    "Authentication" => array(
                        "companyCode" => "EFS0000001",
                        "companyToken" => "7e2204b2226e7f19f80ce5b3b2f340fcf2fcb856c5888704209d097d83db1547"
                    ),
                    "houseData" => array(
                        str_replace("D","",$tr_no)
                    )
                )
            );
            $data = json_encode($data);
      
            break;
        case "NINJAVAN":
            $target_url = "https://api.ninjavan.co/$c_ctry/2.0/track?grant_type=client_credentials";
            $data = array(
                "trackingIds" => array(
                    "$tr_no"
                )
            );
            $data = json_encode($data);
//             echo $data;
            break;
        case "TWOGO":
            $target_url = "https://supplychain.2go.com.ph/CustomerSupport/etrace/indiv1.asp?code=$tr_no";
            break;
        case "SKYNET":
            $target_url = 'http://api.skynet.com.my/api/sn/pub/AWBTracking/';
            $token = '8663ab09SN45d6SN4d56SNbdd2SN90c92a86ec6b';
            $data = array(
                'access_token' => $token,
                'awbs' => array(
                    array(
                        'awbnumber' => $tr_no
                    )
                )
            );
            $data = json_encode($data);
            
            // echo "<br>" . print_r($data, true) . "<br>";
            break;
        case "EFSTW":
            $target_url = "https://api.aftership.com/v4/trackings/Russian-Post/$tr_no";
            break;
        case "RUPOST":
            $curl = curl_init();
            curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.17track.net/track/v2.2/register',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_SSL_VERIFYPEER => "false",
            CURLOPT_POSTFIELDS =>'[
                {
                    "number": "'.$tr_no.'",
                    "lang": "",
                    "email": "",
                    "param": "",
                    "order_no": "",
                    "order_time": "",
                    "carrier": 18031,
                    "final_carrier": 18031,
                    "auto_detection": true,
                    "tag": "",
                    "remark": ""
                },
                {
                    "number": "'.$tr_no.'",
                    "tag": ""
                }
            ]',
            CURLOPT_HTTPHEADER => array(
            "17token: A81BDEF42DC57695A9DB6004EEF79269",
            "content-type: application/json"
                ),
                ));
            $response = curl_exec($curl);
            curl_close($curl);
            
            
            $data = '[{"number":"' . $tr_no . '","carrier": "18031"}]';
            break;
            
//         case "YTO":
//             $send_data = '{"WaybillNo":"' . $tr_no . '","Language":"en-US"}';
//             $target_url = "http://api.ytoglobal.com/steward-api-yto/api/receiveMsg";
//             $data_digest = base64_encode(hex2bin(md5($send_data . "6e8d09e964ce677ba8449ea39929914abf88ca7b9dd194093945f50e35c9cdda")));
//             break;
        case "USPS":
            $target_url = "http://production.shippingapis.com/ShippingAPI.dll?API=TrackV2";
            $xmlData = "
                        http://production.shippingapis.com/ShippingAPI.dll?API=TrackV2&XML=<?xml version=\"1.0\" encoding=\"UTF-8\"?>
                        <TrackFieldRequest USERID=\"487EFSCO5226\">
                        <ClientIp>175.126.111.23</ClientIp>
                        <TrackID ID=\"$tr_no\"></TrackID>
                        </TrackFieldRequest>";
            break;
        case "RAF":
            $url = "https://ws05.ffdx.net/ffdx_ws/v12/service_ffdx.asmx/WSDataTransfer";
            $fields_string = '';
            $fields = array(
                'Username' => '61C94D0E97A7F6E874F3C258A3870903',
                'Password' => '564937B2F590B2B1EDC98A0F037DCDB9',
                
                'xmlStream' => '<?xml version="1.0" encoding="ISO-8859-1" ?>
						<WSGET>
						   <AccessRequest>
							<FileType>2</FileType>
							<Action>Download</Action>
							<EntityID>19529929B1059642ADB12BF90A42F27B</EntityID>
							<EntityPIN>2rh76e1</EntityPIN>
						   </AccessRequest>
						   <ReferenceNumber>' . $tr_no . '</ReferenceNumber>
						   <ShowAltRef>Y</ShowAltRef>
						</WSGET>',
                'LevelConfirm' => 'summary'
            );
            foreach ($fields as $key => $value) {
                $fields_string .= $key . '=' . $value . '&';
            }
            rtrim($fields_string, '&');
            
            break;
        case "GTS":
        case "TCK":
            $url = "https://ws05.ffdx.net/ffdx_ws/v12/service_ffdx.asmx/WSDataTransfer";
            $fields_string = '';
            $fields = array(
                'Username' => '95C5813F102158E1725A34BCC44757FA',
                'Password' => '00CCAF41F486DF8E23EE88F912F0B478',
                
                'xmlStream' => '<?xml version="1.0" encoding="ISO-8859-1" ?>
						<WSGET>
						   <AccessRequest>
							<FileType>2</FileType>
							<Action>Download</Action>
							<EntityID>0A24EB4CC8CE150B5E419CEC8C8BBA01</EntityID>
							<EntityPIN>GTS200305</EntityPIN>
						   </AccessRequest>
						   <ReferenceNumber>' . $tr_no . '</ReferenceNumber>
						   <ShowAltRef>Y</ShowAltRef>
						</WSGET>',
                'LevelConfirm' => 'summary'
            );
            foreach ($fields as $key => $value) {
                $fields_string .= $key . '=' . $value . '&';
            }
            rtrim($fields_string, '&');
            
            break;
        case "GTSTH":
            $url = "https://ws05.ffdx.net/ffdx_ws/v12/service_ffdx.asmx/WSDataTransfer";
            $fields_string = '';
            $fields = array( 
                'Username' => 'D77A8EDDBB7270916F82412EFC2DD13D',
                'Password' => '78C8E9DB02E9ADF8F0F7133D429EEBC6',
                
                'xmlStream' => '<?xml version="1.0" encoding="ISO-8859-1" ?>
						<WSGET>
						   <AccessRequest>
							<FileType>2</FileType>
							<Action>Download</Action>
							<EntityID>DA194D9E5C599476E56120F1237F73F8</EntityID>
							<EntityPIN>Efsasia001</EntityPIN>
						   </AccessRequest>
						   <ReferenceNumber>' . $tr_no . '</ReferenceNumber>
						   <ShowAltRef>Y</ShowAltRef>
						</WSGET>',
                'LevelConfirm' => 'summary'
            ); 
            foreach ($fields as $key => $value) {
                $fields_string .= $key . '=' . $value . '&';
            }
//             echo print_r($fields_string, true);
            rtrim($fields_string, '&');
            
            break;
            
        case "CJGLS":
            // echo "tr_no=" . $tr_no;
            $target_url = "http://customer.cjgls-asia.com/ParcelDelivery/InterSearchOrder.aspx?CNo=All&HAWBNo=$tr_no";
            break;
        case "SSS":
            $target_url = "https://tracking.sagawa-sgx.com/sgx/xmltrack.asp?REF=$tr_no";
            break;
        case "EFSRU":
            $target_url = "http://api.iml.ru/Json/GetStatuses";
            $content = array(
                //                 'OrderStatus' => 0,
                //                 'Job' => '24',
                'BarCode' => $tr_no . "001"
                //                 'DeliveryDateStart' => '2020-11-01',
                //                 'DeliveryDateEnd' => '2021-12-31'
            );
            break;
        default:
            echo "<br>The courier \"" . $courier . "\" is unknown by this script.";
            return false;
    }
    
    if ($courier == 'TIKI') {
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => "$url",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_SSL_VERIFYPEER => "false",
            CURLOPT_POSTFIELDS =>'{"cnno":"'.$tr_no.'"}',
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "x-access-token: $tiki_token"
            ),
        ));

        
        $response = curl_exec($curl);
        curl_close($curl);
        
//         echo print_r($response, true);
//         die();
        
    } else if ($courier == 'DHL') {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "$url",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $xmlData,
            CURLOPT_HTTPHEADER => array(
                "cache-control: no-cache",
                "content-type: text/xml"
            ),
            CURLOPT_SSL_VERIFYPEER => "false"
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        
        $simpleXml = simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
        $response = json_encode($simpleXml);
    } else if ($courier == 'MYDHL') {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "$url",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $xmlData,
            CURLOPT_HTTPHEADER => array(
                "cache-control: no-cache",
                "content-type: text/xml"
            ),
            CURLOPT_SSL_VERIFYPEER => "false"
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        
        $simpleXml = simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
        $response = json_encode($simpleXml);
    }else if ($courier == 'RAF') {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $response = str_replace("&lt;", "<", $response);
        $response = str_replace("&gt;", ">", $response);
        
        $simpleXml = simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
        $response = json_encode($simpleXml);
        
    } else if ($courier == 'GTS' || $courier == 'TCK') {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $response = str_replace("&lt;", "<", $response);
        $response = str_replace("&gt;", ">", $response);
        
        $simpleXml = simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
        $response = json_encode($simpleXml);
//         echo print_r($response, true);
    } else if ($courier == 'GTSTH') {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $response = str_replace("&lt;", "<", $response);
        $response = str_replace("&gt;", ">", $response);
        
        $simpleXml = simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
        $response = json_encode($simpleXml);
//         echo print_r($response, true);
    } else if ($courier == 'EFSUS') {
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api-parceltracking.anchanto.com/openapi/v3/asendia/tracking-details-bulk',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_POSTFIELDS =>'{"trackingIds":["' . $tr_no . '"]}',
                CURLOPT_HTTPHEADER => array(
                        'access_key: 1805',
                        'x-api-key: EGbAC5DI9q6Mrig49xnPj3XipfdGxj2k3XEmzrQj',
                        'Content-Type: application/json',
                        'Authorization: Bearer eyJraWQiOiJnczU3UlJmSEtBZU9VdWVIc2o0NWh4cnRQRHlPZjBQS1wvZndOTjJnTUh5OD0iLCJhbGciOiJSUzI1NiJ9.eyJzdWIiOiJ1Ym5mZ2ZuZnEwMXVuZGtyNnNkbDl2azRqIiwidG9rZW5fdXNlIjoiYWNjZXNzIiwic2NvcGUiOiJGVFwvcmVhZCBGVFwvd3JpdGUiLCJhdXRoX3RpbWUiOjE3NDQzMjk3MTUsImlzcyI6Imh0dHBzOlwvXC9jb2duaXRvLWlkcC5ldS1jZW50cmFsLTEuYW1hem9uYXdzLmNvbVwvZXUtY2VudHJhbC0xX3prUVFMdG1xViIsImV4cCI6MTc0NDQxNjExNSwiaWF0IjoxNzQ0MzI5NzE1LCJ2ZXJzaW9uIjoyLCJqdGkiOiJmNDVkMTU0MC04MjJmLTQ4MjEtODQ2Mi05ZmNkY2JkMWYzNDUiLCJjbGllbnRfaWQiOiJ1Ym5mZ2ZuZnEwMXVuZGtyNnNkbDl2azRqIn0.F-LGkCfWdePbxSl_Rn3g0zHaBtoRRAB4FqDUOG8oyR6INvHLSSg-riO32_Eapcpfscg4sxVL_ZRD1YEh3WKOSTrG6egNj-J89ws4FPMRv79THFlQ-yqMfLucyMXX6PxPLWyenukIC4VhRRgncAVijEyFb_j--A-Wbkmw6hlOZpqmvxeefIOX6W5xqqMSaSSDXW1ltKp4vf-Ne1aIXxLU7tKxdYpA9ptw3r-gpOQP_0zTPoJ2gLd-xNolAnswNtF8H0Wzpv3DCULskh4A_ZCtT-Fqt6pkCX37pX18265AhMV-eAOewxOUS2f4LuFAyMn3mbYsCab2kXJjvTn40Cw9WA'
                ),
        ));
        
        $response = curl_exec($curl);
        curl_close($curl);
    }else if ($courier == 'SSS') {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "$target_url",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTPHEADER => array(
                "cache-control: no-cache",
                "content-type: application/json"
            )
            
        ));
        $response = curl_exec($curl);
        
        $simpleXml = simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
        $response = json_encode($simpleXml);
        
    }else if ($courier == 'JHSS') {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "$url",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => array(
                "cache-control: no-cache",
                "content-type: application/json"
            )
            
        ));
        $response = curl_exec($curl);

//         echo "<pre>" . print_r($response, true) . "</pre>";
//         die();
        
    }else if ($courier == 'EFSPH') {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $target_url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, count($data));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
    } else if ($courier == 'NINJAVAN') {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "$target_url",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTPHEADER => array(
                "cache-control: no-cache",
                "content-type: application/json"
            )
        ));
        $response = curl_exec($curl);
        // echo "<pre>" . print_r ($response, true ) . "</pre>";
    } else if ($courier == 'SKYNET') {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "$target_url",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTPHEADER => array(
                "cache-control: no-cache",
                "content-type: application/json"
            )
        ));
        $response = curl_exec($curl);
        
//     } else if ($courier == 'YTO') {
//         $curl = curl_init();
//         curl_setopt_array($curl, array(
//             CURLOPT_URL => "http://api.ytoglobal.com/steward-api-yto/api/receiveMsg",
//             CURLOPT_RETURNTRANSFER => true,
//             CURLOPT_ENCODING => "",
//             CURLOPT_MAXREDIRS => 10,
//             CURLOPT_TIMEOUT => 30,
//             CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//             CURLOPT_CUSTOMREQUEST => "POST",
//             CURLOPT_POSTFIELDS => $send_data,
//             CURLOPT_HTTPHEADER => array(
//                 "Cache-Control: no-cache",
//                 "Content-Type: application/json",
//                 "partner_code: 224ed20c20f7843cba6b7b9ade976e64",
//                 "msg_type: GET_ORDER_TRAJECTORY",
//                 "msg_id: 1559754060000",
//                 "api_version: V1.0",
//                 "Accept-Language: en-us",
//                 "data_digest: $data_digest"
//             )
//         ));
        
//         $response = curl_exec($curl);
//         $resp_arr = json_decode($response, true);
        
        // echo print_r( $resp_arr, true );
    } else if ($courier == 'EFSTW') {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "$target_url",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTPHEADER => array(
                "aftership-api-key: 5f72417b-3906-4db4-a08a-a0accb1da1fb",
                "cache-control: no-cache",
                "content-type: application/json"
            )
            
        ));
        $response = curl_exec($curl);
    } else if ($courier == 'USPS') {
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => "$target_url",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "$xmlData",
            CURLOPT_HTTPHEADER => array(
                "API: TrackV2",
                "Content-Type: text/xml",
                "Postman-Token: 7acfba4a-97fa-4e31-8424-f515ddd0b511",
                "cache-control: no-cache"
            )
        ));
        
        $response = curl_exec($curl);
        $simpleXml = simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
        $response = json_encode($simpleXml);
//         echo "<pre>" . print_r($simpleXml, true) . "</pre>";
    } else if ($courier == 'EFSRU') {
        $curl = curl_init( "http://api.iml.ru/Json/GetStatuses" );
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($content));
        curl_setopt($curl, CURLOPT_USERPWD, "12078:399DnTBd");
        curl_setopt($curl, CURLOPT_SSLVERSION, 3);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($curl);
    } else if ($courier == 'RUPOST') {
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.17track.net/track/v2.2/gettrackinfo',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_POSTFIELDS => $data ,
            CURLOPT_HTTPHEADER => array(
                "17token: A81BDEF42DC57695A9DB6004EEF79269",
                "content-type: application/json"
            ),
        ));
        $response = curl_exec($curl);
    } else if ($courier == 'EFSTH') {
        $curl = curl_init();
        
        curl_setopt_array($curl, [
                CURLOPT_URL => "https://api.aftership.com/tracking/2024-07/trackings?tracking_numbers=$tr_no&slug=flashexpress",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_HTTPHEADER => [
                        "Content-Type: application/json",
                        "as-api-key: asat_5403b90b407d4ca7ae0a84c9f58d7084"
                ],
        ]);
        $response = curl_exec($curl);
    } else if ($courier == 'YTO') {
        $curl = curl_init();
        
        curl_setopt_array($curl, [
                CURLOPT_URL => "https://api.aftership.com/tracking/2024-07/trackings?tracking_numbers=$tr_no&slug=yto",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_HTTPHEADER => [
                        "Content-Type: application/json",
                        "as-api-key: asat_5403b90b407d4ca7ae0a84c9f58d7084"
                ],
        ]);
        $response = curl_exec($curl);
    } else if ($courier == 'SINGPOST') {
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_SSL_VERIFYPEER => false
        ));
        
        $html_string = curl_exec($curl);
        curl_close($curl);
        
        $html = str_get_html($html_string);
        
        $response = array();
        $seq = 0;
        
        foreach ($html->find('div.sgp-track-trace__journey-box') as $row) {
            $dateElem = $row->find('div.sgp-track-trace__date', 0);
            $timeElem = $row->find('div.sgp-track-trace__time', 0);
            $statusElem = $row->find('div.sgp-track-trace__cnt-detail p', 0);
            
            if (!isset($statusElem)) continue;
            
            $raw_date = isset($dateElem) ? trim($dateElem->plaintext) : '';
            $time = isset($timeElem) ? trim(preg_replace('/\s+/', ' ', $timeElem->plaintext)) : '';
            $status = trim($statusElem->plaintext);
            
            // 날짜 포맷 변환: 10/04/2025 → 2025.04.10
            $formatted_date = '';
            if (!empty($raw_date)) {
                $date_parts = explode('/', $raw_date); // [day, month, year]
                if (count($date_parts) == 3) {
                    $formatted_date = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
                }
            }
            
            $response[$seq]['status_name'] = $status;
            $response[$seq]['status_dtm'] = $formatted_date . ' ' . $time;
            
            $seq++;
        }
        
        $response = json_encode($response);
    } else if ($courier == 'YMT') {
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                        'Cookie: incap_ses_1591_2798546=yx44HLvasCAaZ3hQPl4UFlgUKGcAAAAAPL1hI+egPpCliVWjE2Pi2g==; visid_incap_2798546=1Pe1DSisSAu4ozNLXaQ03lgUKGcAAAAAQUIPAAAAAAAoyHXsNE7t3hUqdeyhFbyx; i18n_redirected=jp'
                ),
                CURLOPT_SSL_VERIFYPEER => false
        ));
        
        $response = curl_exec($curl);
        
        curl_close($curl);
        
        $response = preg_match('/<body.*?>(.*?)<\/body>/is', $response, $matches);
        
        $bodyContent = $matches[1]; // <body> 태그 안의 내용
        
        // <script> 태그 이후의 모든 내용 제거
        $bodyContent = preg_replace('/<script.*?>.*?<\/script>.*$/is', '', $bodyContent);
        $html = str_get_html($bodyContent);
        $seq = 0;
        $response = [];
        foreach ($html->find('div.tracking-summary') as $row2) {
            foreach ($row2->find('div div div.pc-flex') as $row) {
                if(empty(trim($row->find('div.status', 0)->plaintext)))
                    continue;
                    $response[$seq]['status_name'] = trim($row->find('div.status', 0)->plaintext);
                    $response[$seq]['status_dtm'] = trim($row->find('div.date', 0)->plaintext);
                    
                    $seq ++;
            }
        }
        $response = json_encode($response);
        
    } else {
        curl_setopt($ch, CURLOPT_URL, "$url");
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // timeout in seconds
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
        curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
        $response = curl_exec($ch);
        curl_close($ch);
//         echo $url;
        if ($courier == 'LBC') {
            $html = str_get_html($response);
            $seq = 0;
            $response = [];
            foreach ($html->find('table.track-history-table tbody') as $row2) {
                foreach ($row2->find('tr') as $row) {
                    if (empty($row->find('td')))
                        continue;
                        $time = DateTime::createFromFormat("F d, Y h:i:s A", trim($row->find('td', 0)->plaintext))->format("Y-m-d H:i:s");
                        if (empty($time))
                            continue;
                            $response[$seq]['status_dtm'] = $time;
                            $response[$seq]['status_dtm'] = strtotime($response[$seq]['status_dtm']);
                            $response[$seq]['status_name'] = trim($row->find('td', 1)->plaintext);
                            $response[$seq]['status_memo'] = '';
                            foreach (array(
                                ' AT:',
                                ' AT ',
                                ' TO ',
                                ' : '
                            ) as $value)
                                $response[$seq]['status_memo'] = (strpos($response[$seq]['status_name'], $value) !== false) ? explode($value, $response[$seq]['status_name'])[1] : $response[$seq]['status_memo'];
                                foreach (array(
                                    ' AT:',
                                    ' AT ',
                                    ' TO '
                                ) as $value)
                                    $response[$seq]['status_name'] = (strpos($response[$seq]['status_name'], $value) !== false) ? explode($value, $response[$seq]['status_name'])[0] : $response[$seq]['status_name'];
                                    foreach (array(
                                        'POSTED STATUS : CONSIGNEE',
                                        'POSTED STATUS : RELEASED',
                                        'POSTED STATUS : DELIVERED',
                                        'POSTED STATUS : NON DELIVERY',
                                        'POSTED STATUS : LATE ARRIVAL', 
                                        'ACTUAL DEPARTURE'
                                    ) as $value)
                                        $response[$seq]['status_name'] = (strpos($response[$seq]['status_name'], $value) !== false) ? $value : $response[$seq]['status_name'];
                                        if (strpos($response[$seq]['status_name'], "ACCEPTED") !== false)
                                            $response[$seq]['status_memo'] = "";
                                            foreach (array(
                                                'ACTUAL ARRIVAL',
                                                'ESTIMATED ARRIVAL'
                                            ) as $value) {
                                                $response[$seq]['status_name'] = (strpos($response[$seq]['status_name'], $value) !== false) ? $value : $response[$seq]['status_name'];
                                                $response[$seq]['status_memo'] = (strpos($response[$seq]['status_name'], $value) !== false) ? explode($value, $response[$seq]['status_name'])[0] : $response[$seq]['status_memo'];
                                            }
                                            $seq ++;
                }
            }
            $response = json_encode($response);
        } else if ($courier == 'YUPACK') {
            
            $html = str_get_html($response); // HTML 문자열을 파싱
            
            $response = [];
            
            // 두 번째 .tableType01 테이블의 데이터를 추출
            $tables = $html->find('.tableType01');
            if (count($tables) > 1) {
                $rows = $tables[1]->find('tr');
                
                foreach ($rows as $row) {
                    $columns = $row->find('td');
                    
                    if (count($columns) == 5) {
                        $response[] = [
                            'status_dtm' => trim($columns[0]->plaintext),
                            'status_name' => trim($columns[1]->plaintext),
                            // 필요한 다른 데이터도 추가 가능
                        ];
                    }
                }
            }
            
            // 결과 출력
            $response = json_encode($response); 
//             echo print_r($response, true);
        } else if ($courier == 'EFSVN') {
            $html = file_get_html($url);
            $seq = 0;
            $response = [];
            // echo $html;
            foreach ($html->find('.tk-content') as $row) {
                foreach ($row->find('.tracking div.item') as $rs) {
                    $response[$seq]['status_name'] = trim($rs->find('label', 1)->plaintext);
                    $response[$seq]['status_memo'] = trim($rs->find('span', 0)->plaintext);
                    $response[$seq]['status_dtm'] = trim($rs->find('span', 1)->plaintext);
                    $response[$seq]['status_dtm'] = DateTime::createFromFormat("d/m/Y H:i", $response[$seq]['status_dtm'])->format("Y-m-d H:i");
                    foreach (array(
                        ' - ',
                        '-->',
                        '-'
                    ) as $value)
                        $response[$seq]['status_memo_t'] = (strpos($response[$seq]['status_name'], $value) !== false) ? explode($value, $response[$seq]['status_name'])[1] : "";
                        foreach (array(
                            ' - ',
                            '-->',
                            '-'
                        ) as $value)
                            $response[$seq]['status_name'] = (strpos($response[$seq]['status_name'], $value) !== false) ? explode($value, $response[$seq]['status_name'])[0] : $response[$seq]['status_name'];
                            foreach (array(
                                ' - ',
                                '-->',
                                'TEST-',
                                '-'
                            ) as $value)
                                $response[$seq]['status_memo'] = (strpos($response[$seq]['status_memo'], $value) !== false) ? explode($value, $response[$seq]['status_memo'])[1] : $response[$seq]['status_memo'];
                                
                                if (empty($response[$seq]['status_memo_t'])) {
                                    $response[$seq]['status_memo'] = $response[$seq]['status_memo'];
                                } else {
                                    $response[$seq]['status_memo'] = $response[$seq]['status_memo'] . "[" . $response[$seq]['status_memo_t'] . "]";
                                }
                                
                                $seq ++;
                }
            }
            $response = json_encode($response);
            // echo "<pre>" . print_r(json_decode($response, true), true) . "</pre>";
        } else if ($courier == 'TWOGO') {
            $html = file_get_html($target_url);
            $seq = 0;
            $response = [];
            foreach ($html->find('.table-contents') as $row2) {
                foreach ($row2->find('tr') as $rs) {
                    if (empty($rs->find('td', 0)->plaintext))
                        continue;
                        $response[$seq]['status_dtm'] = trim($rs->find('td', 0)->plaintext);
                        $response[$seq]['status_name'] = trim($rs->find('td', 1)->plaintext);
                        if (strpos($response[$seq]['status_name'], 'DELIVERED') !== false) {
                            $response[$seq]['status_name'] = 'DELIVERED';
                            $response[$seq]['status_dtm'] = date('Y-m-d H:i');
                        }
                        
                        // echo "<br><br>##status_dtm=" . $response[$seq]['status_dtm'] . " / status_name=" . $response[$seq]['status_name'];
                        $seq ++;
                }
            }
            $response = json_encode($response);
        } else if ($courier == 'CJGLS') {
            $html = file_get_html($target_url);
            $seq = 0;
            $response = [];
            foreach ($html->find('.detail_order') as $row2) {
                foreach ($row2->find('tr') as $rs) {
                    if (empty($rs->find('td', 1)->plaintext))
                        continue;
                        $response[$seq]['status_name'] = trim($rs->find('td', 1)->plaintext);
                        $response[$seq]['status_dtm'] = trim($rs->find('td', 4)->plaintext);
                        
                        $seq ++;
                }
            }
            $response = json_encode($response);
        }
        
        if (isXml($response)) {
            // $simpleXml = str_replace(array("<![CDATA[", "]]>"), '', $response);
            // $simpleXml = simplexml_load_string($simpleXml);
            $simpleXml = simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
            // echo "<br>(in takeStatus()) simpleXml=[$simpleXml]";
            $response = json_encode($simpleXml);
        }
    }
    return $response;
}

function match_status($courier, $local_status)
{
    $courier = trim($courier);
    $local_status = trim($local_status);
//     echo " '$courier' '$local_status' ";
    switch ($courier) {
        case "THAIPOST":
            switch ($local_status) {
                case "Accept":
                    $status_code = "23"; // Accept -> Local Collection
                    break;
                case "Carded":
                    $status_code = "32"; // Carded -> Consignee absent
                    break;
                case "Container Received":
                    $status_code = "45"; // Container Received -> Arrived at Station
                    break;
                case "Dispatch":
                    $status_code = "28"; // Dispatch -> Local Transit
                    break;
                case "Items Into Container":
                    $status_code = "45"; // Items Into Container -> Arrived at Station
                    break;
                case "No Recipient":
                    $status_code = "32"; // No Recipient -> Consignee absent
                    break;
                case "Out for Delivery":
                    $status_code = "31"; // Out for Delivery -> Out for delivery
                    break;
                case "Successful":
                    $status_code = "33"; // Successful -> Delivered
                    break;
                default:
                    $status_code = "NA";
            }
            break;
        case "LINECLEAR":
            switch ($local_status) {
                case "Damage - Not Delivered":
                    $status_code = "43"; // Failed Delivery
                    break;
                case "Delivered":
                    $status_code = "33"; // Delivered
                    break;
                case "Fail to deliver":
                    $status_code = "43"; // Failed Delivery
                    break;
                case "Hold":
                    $status_code = "44"; // Office holding
                    break;
                case "Incorrect Address":
                    $status_code = "37"; // Bad Address
                    break;
                case "Location Sort Inbound Scan":
                case "Location Sort Outbound Scan":
                    $status_code = "99"; // Processing
                    break;
                case "Not In/Business Closed":
                    $status_code = "32"; // Consignee absent
                    break;
                case "Package Not Delivered/Not Attempted":
                    $status_code = "43"; // (DF)Failed delivery -> Failed Delivery
                    break;
                case "Pick Up":
                    $status_code = "23"; // Local Collection
                    break;
                case "Received PUP/DEL Shipments from Routes":
                    $status_code = "45"; // Arrived at Station
                    break;
                case "Shipment Out for Delivery":
                    $status_code = "31"; // Out for Delivery -> Out for delivery
                    break;
                case "Shipment Refused By Recipient":
                    $status_code = "38"; // Customer refused
                    break;
                case "Unable to Deliver":
                case "Undeliverable Shipment/Return":
                    $status_code = "43"; // Failed Delivery
                    break;
                default:
                    $status_code = "NA";
            }
            break;
        case "LBC":
            switch ($local_status) {
                case "ACTUAL ARRIVAL":
                    $status_code = "24"; // Arrival
                    break;
                case "ACCEPTED":
                    $status_code = "02"; // Shipment picked up by agency
                    break;
                case "ACTUAL DEPARTURE":
                    $status_code = "11"; // Onboard
                    break;
                case "DELIVERED":
                    $status_code = "33"; // Delivered
                    break;
                case 'ESTIMATED ARRIVAL':
                case 'ESTIMATED DEPARTURE':
                    $status_code = ''; //
                    break;
                case "FOR CUSTOMS PROCESSING":
                    $status_code = "71"; // Customs clearing
                    break;
                case "FORWARDED":
                case "FORWARDED TO":
                    $status_code = "28"; // Local Transit
                    break;
                case "HUB":
                    $status_code = "45"; // Arrived at
                    break;
                case "INCORRECT ADDRESS":
                    $status_code = "37"; // Bad Address
                    break;
                case "OUT FOR DELIVERY":
                    $status_code = "31"; // Out for delivery
                    break;
                case "RECEIVED":
                    $status_code = "23"; // Local Collection
                    break;
                case "RELEASED":
                    $status_code = "33"; // Delivered
                    break;
                default:
                    $status_code = "NA";
                    if (strpos($local_status, 'POSTED STATUS') == 0) { // 0 means starts with
                        if (strpos($local_status, ': RELEASED') || strpos($local_status, ': DELIVERED'))
                            $status_code = "33"; // Delivered
                            if (strpos($local_status, ': PICKED-UP'))
                                $status_code = "47"; // Customer Collected
                                else if (strpos($local_status, ': CONSIGNEE') || strpos($local_status, ': NON DELIVERY') || strpos($local_status, ': LATE ARRIVAL'))
                                    $status_code = "43"; // Failed Delivery
                                    else if (strpos($local_status, ': TRANSFER FOR PICK-UP '))
                                        $status_code = "45"; // Arrived at Station
                                        else if (strpos($local_status, ': PENDING FOR PICK-UP'))
                                            $status_code = "30"; // Ready for collection
                                            else if (strpos($local_status, ': REQUEST'))
                                                $status_code = "26"; // Delivery reschedule
                    }
            }
            break;
        case "KPOST":
        case "K-PACKET":
        case "EMS":
        case "EMSCD":
            // echo "local_status = :$local_status:";
            switch ($local_status) {
                case "":
                    $status_code = "";
                    break;
                Case "교환국 도착":
                    $status_code = "24"; // Arrival
                    break;
                Case "미배달":
                    $status_code = "43"; // Failed Delivery
                    break;
                Case "반송":
                    $status_code = "34"; // Returned
                    break;
                Case "도착":
                    $status_code = "45"; // Returned
                    break;
                    //                 case "발송":
                    //                     $status_code = "28"; // Local Transit
                    //                     break;
                Case "배달완료":
                    $status_code = "33";
                    break;
                Case "배달준비":
                    $status_code = "31"; // Out for delivery
                    break;
                Case "보관":
                    $status_code = "44"; // Office holding
                    break;
                case "상대국 도착":
                    $status_code = "24"; // Arrival
                    break;
                Case "운송사 인계":
                    $status_code = "11"; // Onboard
                    break;
                    //                 case "접수":
                    //                     $status_code = "23"; // Local Collection
                    //                     break;
                    //                 Case "통관":
                    //                     $status_code = "71"; // Customs clearing
                    //                     break;
                Case "통관 및 분류":
                    $status_code = "72"; // Customs cleared
                    break;
                    //                 Case "통관검사대기":
                    //                     $status_code = "71"; // Customs clearing
                    //                     break;
                case "항공기 출발(예정,한국시간)":
                    $status_code = "11"; // Onboard
                    break;
                    //                 case "항공사 인수":
                    //                     $status_code = "11"; // Onboard
                    //                     break;
                default:
                    $status_code = "NA";
            }
            break;
        case "TIKI":
            switch ($local_status) {
                Case "INC 01":
                    $status_code = "45"; // Arrived at Station
                    break;
                Case "Arrived":
                    $status_code = "24"; // Arrived
                    break;
                Case "DEL 01":
                    $status_code = "31"; // Out for Delivered
                    break;
                Case "Hold In Station":
                    $status_code = "44"; // Office holding
                    break;
                Case "POD 01":
                Case "POD 02":
                    $status_code = "33"; // Delivered
                    break;
                Case "MDE 02":
                    $status_code = "46"; // Data Handling
                    break;
                Case "TRS":
                    $status_code = "28"; // Local Transit
                    break;
                case "DEX 01":
                case "DEX 02":
                case "DEX 03":
                case "DEX 04":
                case "DEX 05":
                case "DEX 06":
                case "DEX 07":
                case "DEX 08":
                case "DEX 09":
                case "DEX 10":
                case "DEX 11":
                case "DEX 12":
                case "DEX 13":
                case "DEX 14":
                    $status_code = "43"; // Failed Delivery
                    break;
                default:
                    $status_code = "NA";
            }
            break;
        case "DHL":
            switch ($local_status) {
                Case "Forwarded for delivery":
                    $status_code = "31"; // Out for delivery
                    break;
                Case "Clearance processing complete":
                    $status_code = "72"; // Customs cleared
                    break;
                Case "Arrived":
                    $status_code = "24"; // Arrival
                    break;
                    // Case "Departed Facility" :
                    // $status_code = "45"; // Departed Facility
                    // break;
                    // Case "Processed":
                    // $status_code = "45"; // Processed
                    // break;
                    // Case "Customs status updated" :
                    // $status_code = "71"; // Customs clearing
                    // break;
                Case "Shipment on hold":
                    $status_code = "44";
                    break;
                Case "Transferred":
                    $status_code = "28"; // Local Transit
                    break;
                Case "Shipment picked up":
                    $status_code = "11"; // onBoard
                    break;
                Case "Delivered":
                case "Delivered - Signed for by":
                Case "Delivery arranged no details expected":
                    $status_code = "33"; // Delivered
                    break;
                Case "With delivery courier":
                    $status_code = "31"; // Out for delivery
                    break;
                Case "Returned to shipper":
                    $status_code = "34"; // Returned
                    break;
                default:
                    $status_code = "NA";
            }
            break;
        case "MYDHL":
            switch ($local_status) {
                Case "Forwarded for delivery":
                    $status_code = "31"; // Out for delivery
                    break;
                Case "Clearance processing complete":
                    $status_code = "72"; // Customs cleared
                    break;
                Case "Arrived":
                    $status_code = "24"; // Arrival
                    break;
                    // Case "Departed Facility" :
                    // $status_code = "45"; // Departed Facility
                    // break;
                    // Case "Processed":
                    // $status_code = "45"; // Processed
                    // break;
                    // Case "Customs status updated" :
                    // $status_code = "71"; // Customs clearing
                    // break;
                Case "Shipment on hold":
                    $status_code = "44";
                    break;
                Case "Transferred":
                    $status_code = "28"; // Local Transit
                    break;
                Case "Shipment picked up":
                    $status_code = "11"; // onBoard
                    break;
                Case "Delivered":
                case "Delivered - Signed for by":
                Case "Delivery arranged no details expected":
                    $status_code = "33"; // Delivered
                    break;
                Case "With delivery courier":
                    $status_code = "31"; // Out for delivery
                    break;
                Case "Returned to shipper":
                    $status_code = "34"; // Returned
                    break;
                default:
                    $status_code = "NA";
            }
            break;
        case "USPS":
            switch ($local_status) {
                Case "Forwarded for delivery":
                    $status_code = "31"; // Out for delivery
                    break;
                Case "Accepted at USPS Origin Facility":
                    $status_code = "72"; // Customs cleared
                    break;
                Case "Shipping Label Created, USPS Awaiting Item":
                    $status_code = "24"; // Arrival
                    break;
                Case "Arrived at Hub":
                case "Arrived at USPS Facility":
                case "Arrived at Post Office":
                case "Arrived at USPS Origin Facility":
                    $status_code = "45"; // 지점입고(Arrived at Station)
                    break;
                Case "Processed":
                    $status_code = "45"; // Processed
                    break;
                Case "Customs status updated":
                    $status_code = "71"; // Customs clearing
                    break;
                Case "In Transit to Next Facility":
                    $status_code = "28"; // Local Transit
                    break;
                Case "Delivered":
                case "Delivered, To Mail Room":
                case "Delivered, To Original Sender":
                case "Delivered, To Agent":
                Case "Delivered, Left with Individual":
                Case "Delivered, In/At Mailbox":
                case "Delivered, Individual Picked Up at Postal Facility":
                case "Delivered, Individual Picked Up at Post Office":
                case "Delivered, Garage or Other Location at Address":
                case "Delivered to College/University for Final Delivery":
                case "Delivered, Neighbor as Requested":
                case "Delivered, Parcel Locker":
                case "Delivered, Front Desk/Reception/Mail Room":
                case "Delivered, Front Door/Porch":
                case "Delivered, PO Box":
                case "Delivered, Garage / Other Door / Other Location at Address":
                    $status_code = "33"; // Delivered
                    break;
                Case "Out for Delivery":
                case "Delivered to Agent for Final Delivery":
                    $status_code = "31"; // Out for delivery
                    break;
                Case "Arrived at USPS Regional Destination Facility":
                Case "Arrived at USPS Regional Origin Facility":
                    $status_code = "28"; // Transit
                    break;
                default:
                    $status_code = "NA";
            }
            break;
        case "EFSPH":
            switch ($local_status) {
                Case "1":
                    $status_code = "01";
                    break;
                Case "3":
                    $status_code = "03";
                    break;
                Case "11":
                    $status_code = "11";
                    break;
                Case "99":
                    $status_code = "99";
                    break;
                Case "24":
                    $status_code = "24";
                    break;
                Case "26":
                    $status_code = "26";
                    break;
                Case "27":
                    $status_code = "27";
                    break;
                    // Case "53" :
                    // $status_code = "53";
                    // break;
                Case "46":
                    $status_code = "46";
                    break;
                Case "34":
                    $status_code = "34";
                    break;
                Case "47":
                    $status_code = "47";
                    break;
                Case "28":
                    $status_code = "28";
                    break;
                Case "42":
                    $status_code = "45";
                    break;
                Case "43":
                    $status_code = "45";
                    break;
                Case "44":
                    $status_code = "45";
                    break;
                Case "45":
                    $status_code = "45";
                    break;
                Case "31":
                    $status_code = "31";
                    break;
                Case "33":
                    $status_code = "33";
                    break;
                Case "75":
                    $status_code = "75";
                    break;
                default:
                    $status_code = "NA";
            }
            break;
        case "EFSVN":
            switch ($local_status) {
                Case "L梳쪅 th횪nh c척ng":
                Case "SGN HUB":
                Case "HN Sorting Hub":
                Case "HCM Sorting Hub":
                    $status_code = "45";
                    break;
                Case "L튼u kho l梳죍":
                    $status_code = "43";
                    break;
                Case "KH횁CH T沼� CH沼륤 NH梳촏 H�NG":
                    $status_code = "34";
                    break;
                Case "Giao th횪nh c척ng":
                    $status_code = "33";
                    break;
                Case "Tr梳� th횪nh c척ng":
                    $status_code = "33";
                    break;
                default:
                    $status_code = "NA";
            }
            break;
        case "NINJAVAN":
            switch ($local_status) {
                Case "Completed":
                    $status_code = "33";
                    break;
                Case "Cancelled":
                    $status_code = "43";
                    break;
                Case "Arrived at Sorting Hub":
                    $status_code = "45";
                    break;
                Case "On Vehicle for Delivery":
                    $status_code = "31";
                    break;
                Case "Transferred to 3PL":
                    $status_code = "28";
                    break;
                Case "เข้ารับสำเร็จ":
                    $status_code = "33";
                    break;
                Case "อยู่ในระหว่างดำเนินการจัดส่ง":
                    $status_code = "45";
                    // Case "Pending Pickup" :
                    // $status_code = "23";
                    // break;
                default:
                    $status_code = "NA";
            }
        case "SKYNET":
            switch ($local_status) {
                Case "Delivered":
                    $status_code = "33";
                    break;
                Case "Out for Delivery":
                    $status_code = "31";
                    break;
                Case "Arrived SDK":
                    $status_code = "24";
                    break;
                Case "Arrived STW":
                    $status_code = "24";
                    break;
                Case "Arrived HUB":
                    $status_code = "24";
                    break;
                Case "Arrived CR1":
                    $status_code = "24";
                    break;
                Case "Delivered by SCU":
                    $status_code = "33";
                    break;
                Case "Collection":
                    $status_code = "24";
                    break;
                Case "Arrived CR6":
                    $status_code = "45";
                    break;
                Case "Arrived CR5":
                    $status_code = "45";
                    break;
                Case "Arrived CR4":
                    $status_code = "45";
                    break;
                Case "Arrived CR3":
                    $status_code = "45";
                    break;
                Case "Arrived CR2":
                    $status_code = "45";
                    break;
                Case "CONSIGNEE NO LONGER AT THIS ADDRESS/COMPANY":
                    $status_code = "43";
                    break;
                Case "Departed to CR3":
                    $status_code = "28";
                    break;
                Case "Departed to CR2":
                    $status_code = "28";
                    break;
                Case "Departed to HUB":
                    $status_code = "28";
                    break;
                Case "INFO - REQUIRED":
                    $status_code = "43";
                    break;
                Case "N TRANSIT TO DESTINATION":
                    $status_code = "27";
                    break;
                Case "OFFICE CLOSED. DELIVERY WILL BE RE-ATTEMPTED":
                    $status_code = "43";
                    break;
                default:
                    $status_code = "NA";
            }
            break;
        case "SAGAWA":
        case "SSS":
            switch ($local_status) {
                case "OB": // 출항
                    $status_code = "11";
                    break;
                case "SL": // 배달예약
                    $status_code = "25";
                    break;
                case "IB": // 현지 도착
                    $status_code = "24";
                    break;
                case "CO": // 사무실 보관
                case "PS":
                    $status_code = "44";
                    break;
                case "LD": // 배달완료
                    $status_code = "33";
                    break;
                case "ND": // 배달실패
                    $status_code = "43";
                    break;
                case "RE": // 배달 출발
                case "DL":
                case "BD":
                    $status_code = "31";
                    break;
                default:
                    $status_code = "NA";
            }
            break;
        case "JHSS":
            switch ($local_status) {
                case "4F": // 현지 도착
                    $status_code = "24";
                    break;
                case "5D": // 배달완료
                    $status_code = "33";
                    break;
                case "6A": // 배달실패
                    $status_code = "43";
                    break;
                case "5C": // 배달 출발
                    $status_code = "31";
                    break;
                Case "4A": // 통관중
                    $status_code = "71";
                    break;
                Case "4G": // 통관완료
                    $status_code = "72";
                    break;
                Case "5A": // 집하
                    $status_code = "45";
                    break;
                Case "5B": // 이송중
                    $status_code = "28";
                    break;
                Case "5C": // 배송중
                    $status_code = "27";
                    break;
                default:
                    $status_code = "NA";
            }
            break;
        case "YMT":
            switch ($local_status) {
                Case "配達完了": // 배달완료
                    $status_code = "33";
                    break;
                Case "依頼受付（再配達）": // 재배송
                    $status_code = "31";
                    break;
                Case "持戻（ご不在）": // 보류
                    $status_code = "43";
                    break;
                Case "発送済み": // 발송
                    $status_code = "31";
                    break;
                Case "国内到着": // 현지도착
                    $status_code = "24";
                    break;
//                 Case "海外発送": // 이송중
//                     $status_code = "28";
//                     break;
//                 Case "通関手続き中": // 통관중
//                     $status_code = "71";
//                     break;
//                 Case "輸入申告許可": // 통관중 
//                     $status_code = "72";
//                     break;
                default:
                    $status_code = "NA";
            }
            break;
        case "YUPACK":
            switch ($local_status) {
                Case "Posting/Collection":
                    $status_code = "45";
                    break;
                Case "Processing at delivery Post Office":
                    $status_code = "31";
                    break;
                Case "Final delivery":
                    $status_code = "33";
                    break;
                default:
                    $status_code = "NA";
            }
            break;

        case "EFSTH":
        case "YTO":
            switch ($local_status) {
                Case "InTransit":
                    $status_code = "28"; // Local Transit
                    break;
                Case "OutForDelivery":
                    $status_code = "31";
                    break;
                Case "Delivered":
                    $status_code = "33";
                    break;
                default:
                    $status_code = "NA";
            }
            break;
        case "RUPOST":
            switch ($local_status) {

                Case "Handed over to customs":
                    $status_code = "71";
                    break;
                    
                Case "Customs clearance, Released by custom house":
                Case "Customs clearance, Sent with the obligatory payment of customs duties":
                    $status_code = "72";
                    break;
                
                Case "Processing, Departure from inward office of exchange":
                Case "Processing, Arrival at inward office of exchange":
                Case "Export of international mail":
                    $status_code = "28";
                    break;
                
                case "Delivery, Delivery to the addressee":
                Case "Delivery, Delivered to addressee using simple electronic signature":
                case "Delivery, Адресату по QR коду":
                case "Delivery, To the addressee by a mail carrier":
                case "Delivery, Delivery to the addresser":
                    $status_code = "33";
                    break;
                  
                Case "Processing, Arrival at delivery office":
                Case "Processing, Notification delivered":
                    $status_code = "29";
                    break;
                    
                Case "Acceptance, Composite":
                    $status_code = "24";
                    break;
                    
                default:
                    $status_code = "NA";
            }
            break;
            
        case "GTS":
        case "TCK":
        case "GTSTH":
        case "RAF":
            switch ($local_status) {
                Case "1":
                    $status_code = "33";
                    break;
                Case "4":
                    $status_code = "24";
                    break;
                Case "4":
                    $status_code = "23";
                    break;
                Case "15":
                    $status_code = "31";
                    break;
                Case "16":
                    $status_code = "43";
                    break;
                Case "17":
                    $status_code = "43";
                    break;
                Case "18":
                    $status_code = "43";
                    break;
                Case "53":
                    $status_code = "43";
                    break;
                    // Case "91" :
                    // $status_code = "43";
                    // break;
                Case "350":
                    $status_code = "33";
                    break;
                Case "60":
                    $status_code = "43";
                    break;
                Case "10":
                    $status_code = "71";
                    break;
                default:
                    $status_code = "NA";
            }
            break;
        case "CJGLS":
            switch ($local_status) {
                Case "Arrival at Airport W/H":
                    $status_code = "24";
                    break;
                Case "Release to Distribution Center":
                    $status_code = "72";
                    break;
                Case "Received at Processing Facility":
                    $status_code = "45";
                    break;
                Case "Further processing at Delivery Base":
                    $status_code = "53";
                    break;
                Case "With Delivery Courier - 1st attempt":
                    $status_code = "31";
                    break;
                Case "Delivery Completed":
                    $status_code = "33";
                    break;
                Case "Consol":
                    $status_code = "07";
                    break;
                Case "Departure":
                    $status_code = "11";
                    break;
                Case "With Delivery Courier":
                    $status_code = "31";
                    break;
                Case "Delivery Failed":
                    $status_code = "43";
                    break;
                default:
                    $status_code = "NA";
            }
            break;
        case "EFSRU":
            switch ($local_status) {
                Case "1":
                    $status_code = "45";
                    break;
                Case "29":
                    $status_code = "46";
                    break;
                Case "2":
                    $status_code = "53";
                    break;
                Case "14":
                    $status_code = "31";
                    break;
                Case "11":
                    $status_code = "71";
                    break;
                Case "0":
                    $status_code = "45";
                    break;
                Case "13":
                    $status_code = "53";
                    break;
                Case "30":
                    $status_code = "28";
                    break;
                Case "10":
                    $status_code = "28";
                    break;
                default:
                    $status_code = "NA";
            }
            break;
        case "EFSUS":
            switch ($local_status) {
                Case "Flight departure":
                    $status_code = "11";
                    break;
                Case "Sorting in hub":
                    $status_code = "24";
                    break;
                Case "Departure transit facility":
                    $status_code = "28";
                    break;
                Case "Out for delivery":
                Case "Handled by local carrier":
                    $status_code = "31";
                    break;
                Case "Parcel retrieved by consignee":
                Case "Delivered":
                    $status_code = "33";
                    break;
                default:
                    $status_code = "NA";
            }
            break;
        case "SINGPOST":
            switch ($local_status) {
                Case "Item is with SingPost (Singapore) for processing.":
                    $status_code = "24";
                    break;
                Case "Postman delivery in progress":
                    $status_code = "31";
                    break;
                Case "Collected by customer at Collection Point":
                Case "Delivered":
                    $status_code = "33";
                    break;
                default:
                    $status_code = "NA";
            }
            break;
        default:
            $status_code = "NA";
            
    }
    return $status_code;
}

Function ValueName($strKind, $strValue, $strOP)
{
    global $msdbConn;
    $sql_t = "select adm_name, admitem_eng from tcb07 where adm_gubn = '$strKind' and adm_code = '$strValue'";
    $rs_t = $msdbConn->query($sql_t, PDO::FETCH_ASSOC);
    
    if (count($rs_t) != 0) {
        $row = $rs_t->fetch();
        $PTname = $row['adm_name'];
        $admitem_eng = $row['admitem_eng'];
    } else {
        $PTname = "";
        if ($strKind == "16")
            $PTname = "<접수중>";
    }
    
    if ($strOP == 0) {
        echo $admitem_eng;
    } elseif ($strOP == 1) {
        echo $strValue . "-" . $PTname;
    } elseif ($strOP == 2) {
        echo $PTname . "<font color='#aaaaaa'>" . $strValue . "</font>";
    } elseif ($strOP == 3) {
        if ($strValue != "")
            $INPUTvalue = $strValue . "-";
            if ($strValue != "")
                $INPUTname = $PTname;
                echo "<input type='text' value='" . $INPUTvalue . $INPUTname . "' size='15' class='readonly' readonly>";
    } elseif ($strOP == 4) {
        if ($strValue != "")
            $INPUTvalue = $strValue . "-";
            if ($strValue != "")
                $INPUTname = $PTname;
                echo "<input type='text' value='" . $INPUTvalue . $INPUTname . "' style='width:150px;' class='readonly' readonly id='text'1 name='text'1>";
    } elseif ($strOP == 5)
    echo $PTname;
}

Function Api_ssg_track_send($assigned_no, $s_add1)
{
    if (strpos($s_add1, "SHINSEGAE Guro Center") !== false) {
        $cuscde = "EFSS00";
    } else {
        $cuscde = "EFSE00";
    }
    
    $data = array(
        'barcode' => $assigned_no,
        'cuscde' => $cuscde,
        'lastShppProgStatDtlCd' => '50'
    );
    $data = json_encode($data);
    
    $curl = curl_init();
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://geapi.ssgadm.com/v1/shpp/shppStatByBox",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => array(
            "Cache-Control: no-cache",
            "Content-Type: application/json",
            "X-Auth-Token: eyJhbGciOiJIUzUxMiIsImNhbGciOiJERUYifQ.eNqqViouTVKyUkpNK1bSUUosTclMzUtOBQqUpyYBBZKLUhNLUlOUrAxNjY3NDIyMDE0tLI10lPKLMtMz84DKcvKTE3OAClMrCkCKzEwNjS2AikBGlWQAVZVkphYDlQX5-7jGO7r4evrpgJmhwa5BSrUAAAAA__8.9bgAMSBw7jKngt8ZkC3lRqRdNhZYZU4yUb4JiZPEsOPq057PprKZcxlStU7kyaQT9VzPu9b8PhYVTXaA4VlbPQ"
        ),
        CURLOPT_SSL_VERIFYPEER => 0
    ));
    
    $response = curl_exec($curl);
    curl_close($curl);
    
    // echo print_r($response, true);
    // {"resultCode":"22","resultMessage":"NO_DATA_FOUND","resultData":null}
    if ($response['resultMessage'] == "NO_DATA_FOUND") {
        $subject = "SSG.COM 배송완료 전송";
        $mail_to = "aorm1213@efs.asia";
        $body_str = "주문번호 : " . $assigned_no;
        $result = PHPMailer_je($mail_to, $subject, $body_str);
    }
}

Function TIKI_token() 
{
    $curl = curl_init(); 
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://apix.mytiki.net/user/auth',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_SSL_VERIFYPEER => "false",
        CURLOPT_POSTFIELDS =>'{"username":"6871485551407251475","password":"e453e3eb0af0dc300c8b6a52281ab6680e4dec16"}',
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json"
        ),
    ));
    
    $response = curl_exec($curl);
    $data = json_decode($response, true);

    $token = $data["response"]["token"];
    
    return $token;
}

Function Send_shopify_track_no($order_no, $assigned_no, $status_code)
{
    $event_name = ($status_code = 33) ? "delivered" : "out_for_delivery";
    
    $json_data = json_encode(array(
        "event" => array(
            "status" => $event_name
        )
    ));
    
    $curl = curl_init();
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://krave-beauty-international.myshopify.com/admin/api/2019-07/orders/$order_no/fulfillments/$assigned_no/events.json",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $json_data,
        CURLOPT_SSL_VERIFYPEER => "false",
        CURLOPT_HTTPHEADER => array(
            "Authorization: Basic MDllNTUyZmExOTMyNGVkNTFlNTkyZjY1NGY0OWY1ZTU6YTgzOTVmZTRmOTQ4NTMxYmJiMzYyMzUzYjJhZDA4YmU=",
            "Cache-Control: no-cache",
            "Content-Type: application/json"
        )
    ));
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    echo $response;
}

?>