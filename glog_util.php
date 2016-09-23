<?php
/* PHP 5.4 */
define("LIBGLOGUTIL_VERSION", "0.29.0");

define("GLOG_GET_FILENAME", 1); // для glog_codify: режим совместимости со старой функцией get_filename();
define("GLOG_CODIFY_FILENAME", 1); // для glog_codify: режим совместимости со старой функцией get_filename();
define("GLOG_CODIFY_FUNCTION", 2); // для glog_codify: возвращает имя пригодное для функции (буквы, цифры, подчеркивание);
define("GLOG_RENDER_USE_FUNCTIONS", 1); // для glog_render_string: распознавать выражения типа %%caption|func%%, выполнять func при подстановке caption
if ( ! defined("GLOG_DEFAULT_LANG") ) define("GLOG_DEFAULT_LANG", "RU"); 
if ( ! defined("GLOG_FILE_ENCODING") ) define("GLOG_FILE_ENCODING", "UTF-8"); 

function glog_get_log_levels(){
    
    return array(
        "DEBUG",
        "NOTICE",
        "INFO",
        "WARNING",
        "ERROR",
        "FATAL ERROR",
    );
    
}
function glog_log_level($level=""){
    static $log_level = 1;
    
    $levels = glog_get_log_levels();
    
    if ( ! $level ){
        // get
        if ( $log_level ) return $log_level;
        else return 0;
    }else{
        // set
        $log_level = array_search($level, $levels);
    };
}
function glog_get_msg_log_level($message){
    $levels = glog_get_log_levels();
    
    for( $i = count($levels)-1; $i>=0; $i--){
        if ( strpos($message, $levels[$i].":") !== false) return $i;
    };
    
    return $i;
}
function glog_dosyslog($message) {								// Пишет сообщение в системный лог при включенной опции GLOG_DO_SYSLOG.
    static $last_invokation_time;
    static $last_memory_usage;
    
    if ( ! defined("GLOG_DO_SYSLOG")) define("GLOG_DO_SYSLOG", false);
    if ( ! defined("GLOG_DO_PROFILE")) define("GLOG_DO_PROFILE", GLOG_DO_SYSLOG);

    if ( glog_get_msg_log_level($message) < glog_log_level() ) return false;
        
    if (GLOG_DO_SYSLOG || GLOG_DO_PROFILE){
        if ( ! defined("GLOG_SYSLOG") ){
            die("Code: ".__FUNCTION__."-".__LINE__."-GLOG_SYSLOG");
        };
        if (!is_dir(dirname(GLOG_SYSLOG))) mkdir(dirname(GLOG_SYSLOG), 0777, true);

    
    
        if ( ! $last_invokation_time ) $last_invokation_time = isset($_SERVER['REQUEST_TIME_FLOAT']) ? $_SERVER['REQUEST_TIME_FLOAT'] : microtime(true);
        if ( ! $last_memory_usage ) $last_memory_usage = memory_get_usage(true);
        
        $invokation_time = microtime(true);
        $memory_usage = memory_get_usage(true);
        
        $time_change = round(($invokation_time - $last_invokation_time), 4);
        $request_time_change = isset($_SERVER['REQUEST_TIME_FLOAT']) ? round(($invokation_time - $_SERVER['REQUEST_TIME_FLOAT']), 4) : 0;
        $memory_change = $memory_usage-$last_memory_usage;
        
        $data = array(
            @$_SERVER["REMOTE_ADDR"],
            date("Y-m-d\TH:i:s"),
            GLOG_DO_PROFILE && $request_time_change ? $request_time_change."s" : "",
            GLOG_DO_PROFILE ? $time_change."s" : "",
            GLOG_DO_PROFILE ? glog_convert_size($memory_usage) : "",
            GLOG_DO_PROFILE && $memory_change ? glog_convert_size($memory_change) : "",
            $message,
        );
           
               
        $str = implode("\t", $data) . "\n";
        
        if (file_put_contents(GLOG_SYSLOG, $str, FILE_APPEND) === false) {
            $Subject = "Error in ".$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'];
            $extraheader = "Content-type: text/plain; charset=UTF-8";
            $message= "Can not write data to log file '".GLOG_SYSLOG."'!\nUnsaved data:\n".$message."\n";
            if ($_SERVER["HTTP_HOST"] == "localhost"){
                die("Code: ".__FUNCTION__."-".__LINE__."- \"".$subject." - ".$message."\"");
            }else{
                mail(EMAIL,$Subject,$message,$extraheader);
            };
            
            return false;
        };

        $last_invokation_time = $invokation_time;
        $last_memory_usage = $memory_usage;
    };
    
    return true;
};

function glog_isodate($date = "", $withTime = false) {				/* Принимает дату в формате "дд.мм.гггг" и возвращает в формате "гггг-мм-дд" */
    
    if ( ! $date ) $date = $withTime ? date("c") : date("Y-m-d");
        
    
    if (preg_match("/^\d{4}\-\d\d\-\d\d/", $date)) return $date; // дата уже в формате iso
    
        
    // Дата задана в русском формате
    if (preg_match("/^\d\d\.\d\d\.\d{4}/", $date)){
        $m = (int) substr($date,3,2); $m = str_pad($m, 2, "0", STR_PAD_LEFT);
        $d = (int) substr($date,0,2); $d = str_pad($d, 2, "0", STR_PAD_LEFT);
        $y = (int) substr($date,6,4);
        if (!checkdate($m,$d,$y)) {
            return false;
        } else {
        
            if ($withTime){
                $h = substr($date,11,2); $h = str_pad($h, 2, "0", STR_PAD_LEFT);
                $i = substr($date,14,2); $i = str_pad($i, 2, "0", STR_PAD_LEFT);
                $s = substr($date,17,2); $s = str_pad($s, 2, "0", STR_PAD_LEFT);
                
                return "$y-$m-$d $h:$i:$s";
            }else{
                return "$y-$m-$d";
            };
        }; 
    };
    
    // Дата задана строкой, распознаваемой PHP (http://php.net/manual/ru/datetime.formats.php)
    if ( ! is_numeric($date) && ( ($ut = strtotime($date)) !== false) ){ // strtotime() behavior differs from PHP 5.2 to 5.3
        $date = $ut;
        unset($ut);
    };
    
    
    // Дата задана меткой времени
    if ( is_numeric($date) ){ // unix timestamp
        if ($withTime){
            return date("c", $date);
        }else{
            return date("Y-m-d", $date);
        };
    };
    
};
function glog_rusdate($date="", $withTime = false) {				/* Принимает дату в формате "гггг-мм-дд" и возвращает в формате "дд.мм.гггг" */
    
    if ( ! $date ) $date = date("Y-m-d");
    
    if (preg_match("/\d\d\.\d\d\.\d{4}/", $date)) return $date; // дата уже в формате дд.мм.гггг
    if ($date == "all") return "";
    if ($date == "toModerate") return "";
    
    if ( is_numeric($date) ){ // unix timestamp
        $date = date("c", $date);
    };
    
    $m = (int) substr($date,5,2); $m = str_pad($m, 2, "0", STR_PAD_LEFT);
    $d = (int) substr($date,8,2); $d = str_pad($d, 2, "0", STR_PAD_LEFT);
    $y = (int) substr($date,0,4);
    if (!checkdate($m,$d,$y)) {
        return false;
    } else {
    
        if ($withTime){
            if (strlen(substr($date, 11)) == 4){ // время без секунд
                $h = substr($date,11,2); $h = str_pad($h, 2, "0", STR_PAD_LEFT);
                $i = substr($date,14,2); $i = str_pad($i, 2, "0", STR_PAD_LEFT);
                
                return "$d.$m.$y $h:$i";
            }else{
                $h = substr($date,11,2); $h = str_pad($h, 2, "0", STR_PAD_LEFT);
                $i = substr($date,14,2); $i = str_pad($i, 2, "0", STR_PAD_LEFT);
                $s = substr($date,17,2); $s = str_pad($s, 2, "0", STR_PAD_LEFT);
                
                return "$d.$m.$y $h:$i:$s";
            };
        }else{
            return "$d.$m.$y";
        }
    }; 
};
function glog_weekday($day_no="", $short = false, $lang = GLOG_DEFAULT_LANG){                                 // Возвращает наименгование для недели по его номеру (0 - вс, 6 - сб )

    $day_names = glog_weekdays($lang);
    
    if ( ! $day_no){
        $day_no = date("w");
    };
    
    if ( isset($day_names[$day_no]) ){
        return $day_names[$day_no];
    }else{
        glog_dosyslog(__FUNCTION__.": ERROR: ". get_callee() . ": Bad day number '".$day_no."'.");
        return "";
    } 
    
}
function glog_weekdays($short = false, $lang = GLOG_DEFAULT_LANG){

    if ( $short ) {
        return array(
            1 => "пн",
            2 => "вт",
            3 => "ср",
            4 => "чт",
            5 => "пт",
            6 => "сб",
            0 => "вс"
        );
    }else{
        return array(
            1 => "понедельник",
            2 => "вторник",
            3 => "среда",
            4 => "четверг",
            5 => "пятница",
            6 => "суббота",
            0 => "воскресенье"
        );
    };
};
function glog_period($start_date="", $end_date="", $sort = "asc"){   // Возвращает массив дат от start_date до end_date включительно
    
    $start_date = $start_date  or glog_isodate(); // today;
    $end_date = $end_date or glog_isodate(strtotime("+1 day", strtotime($start_date)));
    
    if ($sort != "desc") $sort = "asc";
       
    $dates = array();
    $date = $start_date;
    while ($date <= $end_date) {
        $dates[] = $date;
        $date = glog_isodate(strtotime("+1 day", strtotime($date)));
    }
    unset ($date);
    
    if ($sort == "desc") $dates = array_reverse($dates);
    
    return $dates;
}
function glog_date_add($date, $add_on = "+1"){
    return glog_isodate(strtotime($add_on . " days", strtotime($date)));
}

function glog_time_parse($time_str){
    
    $m = array();
    $res = array();
    
    if (preg_match("/(?:(\d?\d):)?(\d\d):(\d\d)(?:.(\d+))?/", $time_str, $m)){
        $res["hour"] = $m[1] ? $m[1] : "00";
        $res["minute"] = $m[2];
        $res["second"] = $m[3];
        $res["fraction"] = $m[4];
        
        return $res;
        
        
    }else{
        return false;
    }
    
    
}

function glog_convert_size($size_in_bytes, $lang=""){
    // source http://php.net/manual/ru/function.memory-get-usage.php#96280
    
    if ($lang == "RU"){
        $unit=array('байт','Кб','Мб','Гб','Тб','Пб');
    }else{
        $unit=array('b','kb','mb','gb','tb','pb');
    };
    return @round($size_in_bytes/pow(1024,($i=floor(log(abs($size_in_bytes),1024)))),2).' '.$unit[$i];
}
function glog_get_age($anketaORbirthdate, $add_units = false) { 				// Возвращает текущий возраст в формате строки "n" ($add_units = false) или "n лет" ($add_units = true). Принимает анкету.
    
    $age = "";
    
    if ( is_string($anketaORbirthdate) ){
        $birthdate = $anketaORbirthdate;
        
        $birthdate = glog_isodate($birthdate);
        
        $byear  = substr($birthdate,0,4);
        $bmonth = substr($birthdate,5,2);
        $bday   = substr($birthdate,8,2);
        
    }else{
        $anketa = $anketaORbirthdate;
   
        if (!empty($anketa["age_field"]) && !empty($anketa["formdata"][$anketa["age_field"]])){
            $byear = $bmonth = $bday = "";
            $age = $anketa["formdata"][$anketa["age_field"]];    
        }else{
            if(!empty($anketa["birthdate_field"])){
                $birthdate = @$anketa["formdata"][$anketa["birthdate_field"]];
                $byear = @substr($birthdate,0,4);
                $bmonth = @substr($birthdate,5,2);
                $bday = @substr($birthdate,8,2);
            }else{
                $byear = @$anketa["formdata"][$anketa["birth_year_field"]];
                $bmonth = @$anketa["formdata"][$anketa["birth_month_field"]];
                $bday = @$anketa["formdata"][$anketa["birth_day_field"]];
            };
        };
    };

    if ($byear || $bmonth || $bday){
        $age = (date('Y')-$byear);
        if ((int)$bmonth > (int)date('m')){
            $age--;
        } elseif (((int)$bmonth == (int)date('m')) && ((int) $bday > (int) date('d'))) {
            $age--;
        };
    };
    
    
    if ( $add_units ) $age = glog_get_age_str($age);    
    
    return $age;
};
function glog_get_age_str($age){    // возвращает строку вида "n лет"
    
    return glog_get_num_with_unit($age, "год","года", "лет");

}
function glog_get_num_with_unit($num, $unit1="", $unit2_4="",$unit5_9=""){    // возвращает строку вида "n чего-нибудь"
    
    if ( ($num>=10) && ( substr($num,-2,1) == 1) ){
        $suf = $unit5_9; // for num = 10..14
    }else{
        switch (substr($num,-1,1)) {
            case 1:
                $suf = $unit1;
                break;
            case 2:
            case 3:
            case 4:
                $suf = $unit2_4;
                break;
            case 5:
            case 6:
            case 7:
            case 8:
            case 9:
            default:
                $suf = $unit5_9;
        };
    };
        
    
    return trim($num." ".$suf);
}
function glog_codify($str, $flags = 0){                                      // Возвращает строку в виде, пригодном для использования в именах файлов, url, css-классах, ... .
	
    if ( ! is_string($str) ){
        glog_dosyslog(__FUNCTION__.get_callee().": ERROR: Parameter str should be string, ".gettype($str)." given.");
    };
    
    $result = glog_translit($str);
    
    if ($flags & (GLOG_GET_FILENAME || GLOG_CODIFY_FILENAME)){
        $result = str_replace(array("%", "!","?","+","&"," ",",",":",";",",","/","\\","(",")","'","\""),array("_percent", "_excl_", "_quest_", "_plus_","_and_","_","-","-","-"),$result); 
        $result = strtolower($result);
        $result = urlencode($result);
        $result = str_replace("%", "_", $result);
    }elseif($flags & GLOG_CODIFY_FUNCTION){
        $result = preg_replace("/[^\w\d_]/","_", $result);
        if (preg_match("/^\d/", $result)){
            $result = "_".$result;
        };
        $result = strtolower($result);
    }else{
        $result = str_replace(array("+","&"," ",",",":",";",".",",","/","\\","(",")","'","\""),array("_plus_","_and_","-","-","-","-"),$result); 
        $result = strtolower($result);
        $result = str_replace("%","_prc_", urlencode($result));
    };
    
	return $result;
};
function glog_translit($s) {                                        //Возвращает транслитирированную строку.
    $result = $s;

    $result = str_replace(array("а","б","в","г","д","е","ё","з","и","й","к","л","м","н","о","п","р","с","т","у","ф","х","ы","э"), array("a","b","v","g","d","e","e","z","i","j","k","l","m","n","o","p","r","s","t","u","f","h","y","e"), $result);
    $result = str_replace(array("А","Б","В","Г","Д","Е","Ё","З","И","Й","К","Л","М","Н","О","П","Р","С","Т","У","Ф","Х","Ы","Э"), array("A","B","V","G","D","E","E","Z","I","J","K","L","M","N","O","P","R","S","T","U","F","H","Y","E"), $result);
	
	$result = str_replace(array("ж","ц","ч","ш","щ","ю","я","ъ","ь"), array("zh","ts","ch","sh","sch","yu","ya"),$result);
	$result = str_replace(array("Ж","Ц","Ч","Ш","Щ","Ю","Я","Ъ","Ь"), array("ZH","TS","CH","SH","SCH","YU","YA"),$result);

	return $result;
};
function glog_show_array_count($arr, $sort=true){ 

    $unique_arr = array_unique($arr);
    $cu = count($unique_arr);
    $ca = count($arr);

    $id = uniqid("id");
       
    if ($ca>0){
        $HTML = '<a href="#" id="'.$id.'_link" onclick="var el = document.getElementById(\''.$id.'\'); if (el.style.display == \'none\') el.style.display=\'block\'; else el.style.display=\'none\'; return false;">'.($ca == $cu ? $ca : $cu . "/" . $ca).'</a>';
        $HTML .= "<div id='".$id."' style='display:none;'>";
        if ($sort) sort($arr);
        for($i=0; $i<$ca;$i++){
            if ($i && $arr[$i] == $arr[$i-1]){
                $HTML .= "<br>" . "<span style='color:#ccc'>".$arr[$i]."</span>";
            }else{
                $HTML .= "<br>" . $arr[$i];
            }
        }
        $HTML .= "</div>";
    }else{
        $HTML = $ca;
    }
    return $HTML;
}

function glog_show_phone($phone_cleared){ 						    // Форматирует номер телефона (только цифры) к  виду (123) 456-78-90
	return "(" . substr($phone_cleared, 0, 3) . ") " . substr($phone_cleared, 3, 3) . "-" . substr($phone_cleared, 6, 2) . "-" . substr($phone_cleared, 8, 3);
}
function glog_clear_phone($phone){                              	// возвращает телефон в формате 9031234567 - только цифры
	$phone_cleared = "";
	for($i=0,$l=strlen($phone); $i<$l; $i++){
		if ( ($phone{$i} >= '0') && ($phone{$i} <= '9') ){
			$phone_cleared .= $phone{$i};
		};
	};
	return $phone_cleared;
}

function glog_file_read($file_name, $title="" ){
    $res = "";
    
    if (file_exists($file_name)){
        $res = @file_get_contents($file_name);
        $res = ltrim($res, "\xEF\xBB\xBF"); // избавляемся от BOM, если кодировка файла UTF-8-BOM
        
        if ( ! $res ){
            if ($res === ""){
                glog_dosyslog(__FUNCTION__ . ": WARNING: Файл '" . $file_name . "' пустой.");
            }else{
                glog_dosyslog(__FUNCTION__ . ": ERROR: Ошибка чтения " . $file_name );
            };
        };
    }else{
        glog_dosyslog(__FUNCTION__ . ": WARNING: Файл '" . $file_name . "' не существует.");
    }

    return $res;
}
function glog_file_read_as_array($file_name){
    $res = array();
    
    if (file_exists($file_name)){
        $res = @file($file_name, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ( ! $res ){
            if ($res === array()){
                glog_dosyslog(__FUNCTION__ . ": WARNING: Файл '" . $file_name . "' пустой.");
            }else{
                glog_dosyslog(__FUNCTION__ . ": ERROR: Ошибка чтения " . $file_name );
            };
        };
    }else{
        glog_dosyslog(__FUNCTION__ . ": WARNING: Файл '" . $file_name . "' не существует.");
    }

    return $res;
}
function glog_mail_create_multipart( $text, array $attachments, array $attachment_cids = array(), $from="", $reply_to = "" ){
    
        
    $un        = strtoupper(uniqid(time()));
    
    $headers   = "";
    if (!empty($from)) $headers .= "From: $from\n";
    $headers  .= "X-Mailer: Glog_util\n";
    if (!empty($reply_to)) $headers .= "Reply-To: $reply_to\n";
    $headers  .= "Mime-Version: 1.0\n";
    $headers  .= "Content-Type:multipart/related;";
    $headers  .= "boundary=\"----------".$un."\"\n\n";
       
    
    
    $message   = "------------".$un."\nContent-Type:text/html;charset=" . GLOG_FILE_ENCODING . "\n";
    $message  .= "Content-Transfer-Encoding: 8bit\n\n$text\n\n";
    
    foreach($attachments as $attachment_name => $attachment_content){

        $message  .= "------------".$un."\n";
        if ( ! empty($attachment_cids[$attachment_name]) ){
            $message  .= "Content-Type: ". $attachment_cids[$attachment_name] .";\n";
            $message  .= "Content-Transfer-Encoding:base64\n";
            $message  .= "Content-ID:<".$attachment_name.">\n\n";
        }else{
            $message  .= "Content-Type: application/octet-stream;";
            $message  .= "name=\"".basename($attachment_name)."\"\n";
            $message  .= "Content-Transfer-Encoding:base64\n";
            $message  .= "Content-Disposition:attachment;";
            $message  .= "filename=\"".basename($attachment_name)."\"\n\n";
        };
        $message  .= chunk_split(base64_encode( $attachment_content ))."\n";
        
    };
    
    $message  .= "------------".$un."--";
    

    return array("message"=>$message, "headers"=> $headers);
}


function glog_http_get($url, $use_cache = true, $user_agent = "", $other_headers = array()){
    return glog_http_request("GET", $url, array(), $use_cache, "", $user_agent, $other_headers);
};
function glog_http_post($url, $data, $use_cache = true, $content_type="", $user_agent = "", $other_headers = array()){
    return glog_http_request("POST", $url, $data, $use_cache, $content_type, $user_agent, $other_headers);
};
function glog_http_request($method, $url, $data, $use_cache = true, $content_type = "", $user_agent = "", $other_headers = array()){ // Выполняет HTTP запрос методом $method на $url с параметрами $data

    $cache_ttl = 60*60; // 1 час
    $cache_dir = DATA_DIR . ".cache/" . __FUNCTION__ . "/";
    if ( ! is_dir($cache_dir) ) mkdir($cache_dir, 0777, true);

    $max_tries = 5;
    $sleep_coef = .5;
    $max_response_length_for_log = 50;
    
    $result = "";
    $request_id = uniqid();
    
    $method = strtoupper($method);
        
    if ($method == "POST"){
        if ( ! $content_type ) $content_type  = "application/x-www-form-urlencoded";
        switch($content_type){
            case "application/json":
                $postdata = json_encode($data, JSON_UNESCAPED_UNICODE);  break;
            case "application/x-www-form-urlencoded":
            default:
                $postdata = http_build_query($data);
        }
    } 
    
    $headers = array();
    $headers["Content-type"] = "Content-type: " . $content_type;
    if ( ! empty($other_headers) ) $headers = array_merge($headers, $other_headers);
    
    
	$opts = array('http' => array( 'method'  => $method ) );
    if ( ! empty($headers) )      $opts["http"]['header']     = implode("\r\n", $headers);
    if ( ! empty($user_agent) )   $opts["http"]['user_agent'] = $user_agent;
    if ( ! empty($postdata) )     $opts["http"]['content']    = $postdata;

    
    glog_dosyslog(__FUNCTION__.": NOTICE: " . $method . "-запрос " . $request_id . " на '" . $url . "'" . ( ! empty($postdata) ? " с данными '" . urldecode($postdata) . "'" : "" ) . ( ! empty($headers) ? " Заголовки: " . implode("; ", $headers) . "." : "") . " ... ");
    
    $tries = $max_tries;
    
    $hash = md5( serialize( func_get_args() ) );
    $cache_file = $cache_dir . $hash . ".php";
    if ( $use_cache ){        
        if ( file_exists($cache_file) && ( time() - filemtime($cache_file) < $cache_ttl ) ){            
            $response = @file_get_contents($cache_file);
            glog_dosyslog(__FUNCTION__ . ": NOTICE: Ответ на запрос '" . $request_id . "' взят из кэша '".basename($cache_file). "'.");
        };
    };
    
    
    if ( empty($response) ){    
        $context = stream_context_create($opts);
        
        while ( ! ( $response = @file_get_contents($url , false, $context) ) && ($tries--) ){
            if ( ! $response ){
                dosyslog(__FUNCTION__.": WARNING: Empty response for ".$request_id."." . (!empty($http_response_header) ? " HTTP headers: ".json_encode($http_response_header) : ""));
                sleep( $sleep_coef * ($max_tries-$tries));
            };
        };            
        glog_dosyslog(__FUNCTION__.": NOTICE: Отправлен " . $method . "-запрос " . $request_id . " на '" . $url . "' ... " . ($response === false ? "ERROR" : "OK"));
        if ( ! empty($postdata) ) glog_dosyslog(__FUNCTION__.": NOTICE: " . $request_id . " POST-данные: '".$postdata."'.");
        
    };
    
    

    if ($response){
        $result = ltrim($response, "\xEF\xBB\xBF"); // избавляемся от BOM, если кодировка ответа UTF-8
        if ($result){
            if ($tries<$max_tries){
                if ( strlen($result) <= $max_response_length_for_log ){
                    glog_dosyslog(__FUNCTION__.": NOTICE: Получен ответ на " . $request_id . ": '" . $result . "'. Сделано попыток запроса: ".($max_tries-$tries));    
                }else{
                    glog_dosyslog(__FUNCTION__.": NOTICE: Получен ответ на " . $request_id . ". Сделано попыток запроса: ".($max_tries-$tries));
                }
            }
            
            if ( $use_cache ){
                if ( ! file_put_contents($cache_file, $result) ){
                    glog_dosyslog(__FUNCTION__ . ": ERROR: Ошибка записи в кэш-файл '" . $cache_file . "'.");
                };
            };
            
        }else{
            dosyslog(__FUNCTION__.": WARNING: Пустой ответ на " . $request_id . ": '" . $response . "'.");
        }
    }else{
        dosyslog(__FUNCTION__.": ERROR: Не удалось получить ответ на " . $request_id . " после " . $max_tries .  " попыток.");
        $result = false;
    }

    return $result;
}

function glog_render($template_file, array $data){
    $HTML = "";
    
    if (file_exists($template_file)){
        $template = file_get_contents($template_file);
        if (empty($template)){
            glog_dosyslog(__FUNCTION__.": ERROR: Файл шаблона пустой - '".$template_file."'.");
            $template = defined("TEMPLATE_DEFAULT") ? TEMPLATE_DEFAULT : "";
        };
        
        $HTML = glog_render_string($template, $data);
    
    }else{
		$HTML = "<p><b>Ошибка!</b> Файл шаблона не найден".(defined("DEV_MODE") && DEV_MODE ? " - '".$template_file."'" : "")."</p>";
        glog_dosyslog(__FUNCTION__.": ERROR: Файл шаблона не найден - '".$template_file."'.");
    };
    
    return $HTML;
};
function glog_render_string($template, array $data, $options = 0){
    
    // parse template.
    $template = str_replace("\r\n", "\n", $template);
    $template = str_replace("\r", "\n", $template);
    
    // Подстановка данных
    if ($options && GLOG_RENDER_USE_FUNCTIONS){
        $template = preg_replace_callback("/%%(.+)%%/u", function(array $m) use ($data){
            $res = "";
            if (!empty($m[1])){
                if (strpos($m[1],"|") === false){
                    $key = $m[1];
                    $func = "";
                }else{
                    list($key, $func) = explode("|", $m[1],2);
                };
                if (isset($data[$key])){
                    $res = $data[$key];
                };
                
                if ($func){
                    // Substring
                    $matches = array();
                    if (preg_match("/{(\d+),(\d+)}/", $func, $matches)){
                        $res = mb_substr($res, $matches[1], $matches[2], "UTF-8");
                    };
                    
                    if ($_SERVER["REMOTE_ADDR"] == "176.195.57.123"){
                        $test = true;
                    }else{
                        $test = false;
                    };
                    
                    switch($func){
                        case "hour":
                        case "minute":
                        case "second":
                        case "fraction";
                            $tmp = glog_time_parse($data[$key]);
                           
                            $res = $tmp[$func];
                           
                            break;
                        case "CP1251":
                            $res = iconv("UTF8","CP1251", $data[$key]);
                        
                    };
                    // More functions to come... 
                    // ...
                };
            };
            return $res;
            
        }, $template);
        
    }else{
        foreach($data as $k=>$v){
            $template = str_replace("%%".$k."%%", $v, $template);
        };
    };
        
    $template = preg_replace("/%%[^%]+%%/","",$template); // удаляем все placeholders для которых нет данных во входных параметрах.
    $HTML = $template;
    //glog_dosyslog(__FUNCTION__.": NOTICE: Успешно применен шаблон '".$template_file."'.");
    
    return $HTML;
}
function glog_str_from_num($int, $lang = GLOG_DEFAULT_LANG){  // Возвращает число прописью. ЭКСПЕРИМЕНТАЛЬНО: до 19999 и только по-русски.
    
    if ( ! is_int($int) ){
        glog_dosyslog(__FUNCTION__.get_callee().": ERROR: Wrong parameter int:".$int.". Should be integer. Returned as is.");
        return $int;
    }
    
    if ($int > 19999){
        glog_dosyslog(__FUNCTION__.get_callee().": ERROR: Function is experimental. Max number supported is 19999. ".int." given. Returned as is.");
        return $int;
    }
    
    
    $num = strrev( (string) $int);
    $len = strlen($num);
    $str = "";
    $tmp = array();
    
    for($i = 0; $i < $len; $i++){
        
        switch ($lang){
        case "RU":
        default:
            
            if (in_array($i, array(0, 3))){

                if (isset($num[$i+1]) && ($num[$i+1] == 1)){
                    switch($num[$i]){
                    case"0": $tmp[$i] = "десять" ; break;
                    case"1": $tmp[$i] = "одиннадцать"  ; break;
                    case"2": $tmp[$i] = "двенадцать"   ; break;
                    case"3": $tmp[$i] = "тринадцать"   ; break;
                    case"4": $tmp[$i] = "четырнадцать"; break;
                    case"5": $tmp[$i] = "пятнадцать"  ; break;
                    case"6": $tmp[$i] = "шестнадцать" ; break;
                    case"7": $tmp[$i] = "семнадцать"  ; break;
                    case"8": $tmp[$i] = "восемнадцать"; break;
                    case"9": $tmp[$i] = "девятнадцать"; break;
                    };
                }else{
                    switch($num[$i]){
                    case"0":
                        if ($len == 1){
                            $tmp[$i] = "ноль";
                        }else{
                            $tmp[$i] = "";
                        };
                        break;
                    case"1": $tmp[$i] = "один"  ; break;
                    case"2": $tmp[$i] = "два"   ; break;
                    case"3": $tmp[$i] = "три"   ; break;
                    case"4": $tmp[$i] = "четыре"; break;
                    case"5": $tmp[$i] = "пять"  ; break;
                    case"6": $tmp[$i] = "шесть" ; break;
                    case"7": $tmp[$i] = "семь"  ; break;
                    case"8": $tmp[$i] = "восемь"; break;
                    case"9": $tmp[$i] = "девять"; break;
                    };
                }
            };
            if (in_array($i, array(1))){
                switch($num[$i]){
                case"0": $tmp[$i] = "";  break;
                case"1": $tmp[$i] = "";  break;
                case"2": $tmp[$i] = "двадцать"; break;
                case"3": $tmp[$i] = "тридцать";  break;
                case"4": $tmp[$i] = "сорок"; break;
                case"5": $tmp[$i] = "пятьдесят"; break;
                case"6": $tmp[$i] = "шестьдесят"; break;
                case"7": $tmp[$i] = "семьдесят"; break;
                case"8": $tmp[$i] = "восемьдесят"; break;
                case"9": $tmp[$i] = "девяносто"; break;
                };
            };
            if (in_array($i, array(2))){
                switch($num[$i]){
                case"0": $tmp[$i] = "";     break;
                case"1": $tmp[$i] = "сто";  break;
                case"2": $tmp[$i] = "двести";  break;
                case"3": $tmp[$i] = "триста"; break;
                case"4": $tmp[$i] = "четыреста"; break;
                case"5": $tmp[$i] = "пятьсот"; break;
                case"6": $tmp[$i] = "шестьсот"; break;
                case"7": $tmp[$i] = "семьсот"; break;
                case"8": $tmp[$i] = "восемьсот"; break;
                case"9": $tmp[$i] = "девятьсот"; break;
                };
            };
            if (in_array($i, array(3))){
                if (isset($num[$i+1]) && ($num[$i+1] == 1)){
                    $tmp[$i] .= " тысяч"; break;
                }else{
                    switch($num[$i]){
                    case"0": $tmp[$i] = "";     break;
                    case"1": $tmp[$i] = "одна тысяча";  break;
                    case"2": $tmp[$i] = "две тысячи";  break;
                    case"3": $tmp[$i] = "три тысячи"; break;
                    case"4": $tmp[$i] = "четыре тысячи"; break;
                    case"5": $tmp[$i] = "пять тысяч"; break;
                    case"6": $tmp[$i] = "шесть тысяч"; break;
                    case"7": $tmp[$i] = "семь тысяч"; break;
                    case"8": $tmp[$i] = "восемь тысяч"; break;
                    case"9": $tmp[$i] = "девять тысяч"; break;
                    };
                };
            };

            
            
        }
    };
    
    switch($lang){
    case "RU":
    default:
        $str = implode(" ", array_reverse($tmp));
    }
    
    return $str;
    
}
function glog_str_limit($str, $limit, $noHTML = false){  
    
    if ( ! $str ) return  "";
    
    if (mb_strlen($str, "UTF-8") > $limit){
        if ($noHTML){
            return mb_substr($str,0, $limit - 3, "UTF-8") . "&hellip;";
        }else{
            return "<span title='".htmlspecialchars($str)."'>" . mb_substr($str,0, $limit - 3, "UTF-8") . "&hellip;</span>";
        };
    }else{
        return $str;
    }
};
function glog_str_ucfirst($str, $enc = 'utf-8') {
    return mb_strtoupper(mb_substr($str, 0, 1, $enc), $enc).mb_strtolower(mb_substr($str, 1, mb_strlen($str, $enc), $enc), $enc);
} 
// ----------------
if (!function_exists("get_callee")){
    function get_callee($options = true, $limit = 0){
        $dbt    = debug_backtrace($options, $limit);
        
        $dbt_ = array_map(function($dbt_item){
            $className = isset($dbt_item["class"]) ? $dbt_item["class"] : "";
            $type = isset($dbt_item["type"]) ? $dbt_item["type"] : "";
            $args = isset($dbt_item["args"]) ?  implode(", ", array_map(function($arg){
                return is_scalar($arg) ? $arg : "[".gettype($arg)."]";
            },$dbt_item["args"])) : "";
            return $className.$type.$dbt_item["function"]."(" . $args . ")";
        }, array_slice($dbt,2, 3));
        
        return " < " . implode(" < ", $dbt_); // вызывающая функция; для целей логирования.
    };
};
if (!function_exists("dosyslog")){
    function dosyslog($message){
        return glog_dosyslog($message);
    }
};
if (!function_exists("dump")){
    function dump($var, $title="") {						// Печатает дамп переменной, окруженной тегами PRE
        if ( (defined("DEV_MODE") && DEV_MODE) || ! defined("DEV_MODE") ){
            if ($title) echo "$title : \n";
            echo "<pre>";
            var_dump($var);
            echo "</pre>"; 
        };
    };
};
if (!function_exists("return_btytes")){
    function return_bytes($val) {  // http://php.net/manual/ru/function.ini-get.php
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        return $val;
    }
}
// ----------------
