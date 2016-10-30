<?php
require 'serverconnect.php';

/**
    @return String IF success
        The video ID of a YouTube video
    @return boolean IF fail
        Indicates that the hostname is neither youtube.com nor youtu.be
*/
function parseVideoIdFromLink($link){
    if (parse_url($link, PHP_URL_HOST) === 'www.youtube.com'){
        $linkParsed = parse_url($link, PHP_URL_QUERY);
        $arr = explode("v=", $linkParsed);
        if ($arr[1] !== NULL){
            return $arr[1];
        }
    }
    else if (parse_url($link, PHP_URL_HOST) === 'youtu.be'){
        $linkParsed = parse_url($link, PHP_URL_PATH);
        return ltrim($linkParsed, "/");
    }
    else{
        return false;
    }
}

/**
    .htaccess says
        RewriteRule ^(.*)$ /short.php?val=$1
    val is used as the main key for matching to full link and data in database
*/
$short = $mysql->escape_string($_GET["val"]);

//$query = "SELECT title, image, description, link FROM linkTable WHERE short='$short'";
$query = $mysql->prepare("SELECT title, image, description, link FROM linkTable WHERE short=?");
$query->bind_param('s', $short);
$query->execute();
$query->close();
$result = $mysql->query($query);
if (!$result->num_rows == 0){
    $row = $result->fetch_assoc();
    $title = htmlspecialchars($row['title'], ENT_QUOTES);
    $image = htmlspecialchars($row['image'], ENT_QUOTES);
    $description = htmlspecialchars($row['description'], ENT_QUOTES);
    $link = htmlspecialchars($row['link'], ENT_QUOTES);
    
    // If it is a YouTube link, we create an og:video tag so Facebook can embed the player
    $isYouTubeVideo = false;
    if (parse_url($link, PHP_URL_HOST) === 'www.youtube.com' || parse_url($link, PHP_URL_HOST) === 'youtu.be'){
        $isYouTubeVideo = true;
    }
    if ($isYouTubeVideo){
        $videoId = parseVideoIdFromLink($link);
    }
    echo "<!DOCTYPE html><html>";
    
    //Google analytics tracker
    include_once("common/analyticstracking.php");
    echo "<meta property='og:title' content='$title'>";
    echo "<meta property='og:url' content='https://${_SERVER['HTTP_HOST']}/$short'>";
    echo "<meta property='og:image' content='$image'>";
    echo "<meta property='og:description' content='$description'>";
    if ($isYouTubeVideo){
        // Autplay is necessary otherwise Facebook will require clicking two play buttons
        echo "<meta property='og:video' content='https://youtube.com/v/$videoId&autoplay=1'>";
    }
    echo "<script>location = '$link'</script>";
    echo "<noscript><a href='$link'>Click here to go to manually go to link</a>Please enable JavaScript to automatically proceed to link</noscript>";
    echo "</html>";
}
else{
    header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
    include("404.php");
}