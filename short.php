<?php
require '../serverconnect.php';

$short = $mysql->escape_string($_GET["val"]);

$query = "SELECT title, image, description, link FROM linkTable WHERE short='$short'";
$result = $mysql->query($query);
if (!$result->num_rows == 0){
    $row = $result->fetch_assoc();
    $title = htmlspecialchars($row['title'], ENT_QUOTES);
    $image = htmlspecialchars($row['image'], ENT_QUOTES);
    $description = htmlspecialchars($row['description'], ENT_QUOTES);
    $link = htmlspecialchars($row['link'], ENT_QUOTES);

    echo "<!DOCTYPE html><html>";
    
    //Google analytics tracker
    include_once("../common/analyticstracking.php");
    echo "<meta property='og:title' content='$title'>";
    echo "<meta property='og:url' content='https://$_SERVER[HTTP_HOST]$short'>";
    echo "<meta property='og:image' content='$image'>";
    echo "<meta property='og:description' content='$description'>";
    echo "<script>location = '$link'</script>";
    echo "<noscript>$link</noscript>";
    echo "</html>";
}
else{
    echo "<script>location='404.php'</script>";
}