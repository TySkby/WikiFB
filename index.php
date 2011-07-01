<?php

/* ---------- BEGIN CONFIGURATION SETTINGS ---------- */

//File and Path definitions
$localFileName = 'scrapecontent.htm';  //Local file to save scraped content to
$remoteURL = 'http://wikileaks.belfalas.org/Mirrors.html'; //URL to scrape
$fbcmdPath = 'C:/Program Files (x86)/fbcmd/support/'; //Path to fbcmd/support directory
$fbcmdListFile = 'links'; //Filename in fbcmdPath to be created and save list of validated links- can be whatever you want

//Set how many links you want to include in your Status update
//To include all scraped links, set $numLinks to 0
$numLinks = 3;

//Optional: Define a message to be displayed in your status before the link list
//If you do not want a message, just make $preMsg an empty string (eg. $preMsg = '';)
$preMsg = 'Auto-Update: New validated mirror links to WikiLeaks: ';

//Optional: Define area to parse between (such as matching tags) to shorten file to desired content
//If you do not wish to use this feature, set $useParser to FALSE.
//See README for more information on $useParser.
$useParser = TRUE;
$parserStartString = "<table>";
$parserEndString = "</table>";

/* ---------- END CONFIGURATION SETTINGS ---------- */
/* ---------- DO NOT EDIT BELOW THIS LINE ---------- */
include './functions.php';
$fbcmd = $fbcmdPath . $fbcmdListFile;
$getRemote = getRemoteHtml($localFileName, $remoteURL);
if ($getRemote) {
    if ($useParser) {
        $localParse = parseLocalContent($localFileName, $parserStartString, $parserEndString);
        if ($localParse) {
            $linkCatcher = linkCatchVal($localFileName, $fbcmd);
        }
    } else {
        $linkCatcher = linkCatchVal($localFileName, $fbcmd, $numLinks);
    }
    if ($linkCatcher) {
        $updateFb = fbCustom($fbcmdPath, $preMsg, $fbcmdListFile);
    }
    if ($updateFb) {
        $listFileCleanup = unlink($fbcmd);
    }
    if ($listFileCleanup) {
        $scrapeFileCleanup = unlink($localFileName);
    }
    if ($scrapeFileCleanup) {
        echo 'Cleanup complete';
    }
}
?>