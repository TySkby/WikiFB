<?php

function getRemoteHtml($rem, $loc) {

    // Time to cache in hours
    $cacheTime = 1;
    // Connection time out
    $connTimeout = 120;
    // File to download
    $remoteFile = $rem;
    // Local file for saving
    $localFile = $loc;

    if (file_exists($localFile) && (time() - ($cacheTime * 3600) < filemtime($localFile))) {
        readfile($localFile);
    } else {
        $url = parse_url($remoteFile);
        $host = $url['host'];
        $path = isset($url['path']) ? $url['path'] : '/';

        if (isset($url['query'])) {
            $path .= '?' . $url['query'];
        }

        $port = isset($url['port']) ? $url['port'] : '80';

        $fp = @fsockopen($host, '80', $errno, $errstr, $connTimeout);

        if (!$fp) {
            // If connection failed, return the cached file
            if (file_exists($localFile)) {
                readfile($localFile);
            }
        } else {
            // Header Info
            $header = "GET $path HTTP/1.0\r\n";
            $header .= "Host: $host\r\n";
            $header .= "User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6\r\n";
            $header .= "Accept: */*\r\n";
            $header .= "Accept-Language: en-us,en;q=0.5\r\n";
            $header .= "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7\r\n";
            $header .= "Keep-Alive: 300\r\n";
            $header .= "Connection: keep-alive\r\n";
            $header .= "Referer: http://$host\r\n\r\n";

            $response = '';
            fputs($fp, $header);
            // Get the file content
            while ($line = fread($fp, 4096)) {
                $response .= $line;
            }
            fclose($fp);

            // Remove Header Info
            $pos = strpos($response, "\r\n\r\n");
            $response = substr($response, $pos + 4);
            echo $response;

            // Save the file content
            if (!file_exists($localFile)) {
                // Create the file, if it doesn't exist already
                fopen($localFile, 'w+');
            }
            if (is_writable($localFile)) {
                if ($fp = fopen($localFile, 'w+')) {
                    fwrite($fp, $response);
                    fclose($fp);
                    echo "Remote page saved to " . $loc . "\n";
                    return TRUE;
                } else {
                    echo "Error saving remote page to " . $loc . "\n";
                    return FALSE;
                }
            }
        }
    }
}

function parseLocalContent($loc, $parseBegin, $parseEnd) {
    $localFile = $loc;
    $handle = fopen($localFile, 'r+');
    $contents = fread($handle, filesize($localFile));
    fclose($handle);

    function get_string_between($string, $start, $end) {
        $string = " " . $string;
        $ini = strpos($string, $start);
        if ($ini == 0)
            return "";
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }

    $parsed = get_string_between($contents, $parseBegin, $parseEnd);

    $contentParsed = $parseBegin . $parsed . $parseEnd;

    $handle = fopen($localFile, 'r+');
    $writer = fwrite($handle, $contentParsed);
    fclose($handle);

    echo "Parsed content saved to " . $loc . "\n";
    return TRUE;
}

function linkCatchVal($loc, $fbcmdPath, $numLinks) {

    set_time_limit(30);

    $localFile = $loc;
    $handle = fopen($localFile, 'r+');
    $contents = fread($handle, filesize($localFile));
    fclose($handle);

    function url_exists($link) {
        if ((strpos($link, "http")) === false)
            $link = "http://" . $link;
        if (is_array(@get_headers($link)))
            return true;
        else
            return false;
    }

    $dom = new DOMDocument();
    @$dom->loadHTML($contents);
    $xpath = new DOMXPath($dom);
    //Provide the DOM path in $customPath variable
    $customPath = "/html/body//a";
    $hrefs = $xpath->evaluate($customPath);
    $num_hrefs = $hrefs->length;

    $active_url;
    for ($i = 0; $i < $num_hrefs; $i++) {
        $href = $hrefs->item($i);
        $url[$i] = $href->getAttribute('href');
        //print($url[$i]);
    }

    echo "Links successfully retrieved from table! \n";

    $auid = 0;
    if ($numLinks == 0) {  //Use all scraped links
        $totalLinks = $num_hrefs;
    } else if ($num_hrefs <= $numLinks) { //Scraped links less than number specified for Status, use all scraped links
        $totalLinks = $num_hrefs;
    } else { //Number specified is less than available scraped links, ok to use number specified
        $totalLinks = $numLinks;
    }
    while ($auid < $totalLinks) {
        $rand = rand(0, $num_hrefs);
        $check_url = url_exists($url[$rand]);
        if ($check_url == true) {
            $auid++;
            $active_url[$auid] = $url[$rand];
            $url_list = $url_list . $active_url[$auid];
        }
    }

    $url_file = $fbcmdPath;
    $handle = fopen($url_file, 'w+');
    fwrite($handle, $url_list);
    fclose($handle);

    echo "Space-separated URLs have been validated and saved to $url_file. \n";
    echo "Update operation complete!";
    return TRUE;
}

function fbCustom($fbcmdPath, $preStatus, $scrapeFile) {
    require $fbcmdPath . 'fbcmd_include.php';
    FbcmdIncludeInit();
    FbcmdIncludeAddArgument('-quiet=0');
    FbcmdIncludeAddArgument('-facebook_debug=0');
    FbcmdIncludeAddCommand('WLSTATUS', 'Updates Status with scraped and validated links.');
    require $fbcmdPath . '../fbcmd.php';
    $updateCall = fbUpdate($preStatus, $scrapeFile);
    if ($updateCall) {
        return TRUE;
    }
}

function fbUpdate($preStatus, $scrapeFile) {
    $urlhandle = fopen($scrapeFile, 'r');
    $wl_links = fread($urlhandle, filesize('urls'));
    fclose($urlhandle);
    $statusText = $preStatus . $wl_links;
    try {
        $fbReturn = $fbObject->api_client->call_method('facebook.users.setStatus', array('status' => $statusText, 'status_includes_verb' => true));
        TraceReturn($fbReturn);
        return True;
    } catch (Exception $e) {
        FbcmdException($e);
        return False;
    }
}

?>
