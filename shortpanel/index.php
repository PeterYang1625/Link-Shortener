<?php
require '../serverconnect.php';
/**
    @param String $short
        The key used to grab full data from Database
        Ex. https://short.com/key

    @param String $link
        The full link
        Ex. https://somesite.com/foo.index

    @return boolean
        False on failed database entry
*/
function addVideoToShorthand($short, $link){
    $scrapeData = scrapeLink($link);
    // This scraper returns og:tag instead of tag so we have to clean it
    $scrapeData = cleanScrapeData($scrapeData, $link);
    // insertToDB will fail if a short already exists
    if (!insertToDB($scrapeData, $short, $link)){
        return false;
    }
    return true;
}

/**
    @return array $rmetas
        An array containing the relevant og data
*/
function scrapeLink($link){
    libxml_use_internal_errors(true);
    $doc = new DomDocument();
    $doc->loadHTMLFile($link);
    $xpath = new DOMXPath($doc);
    $query = '//*/meta[starts-with(@property, \'og:\')]';
    $metas = $xpath->query($query);
    $rmetas = array();
    foreach ($metas as $meta) {
        $property = $meta->getAttribute('property');
        $content = $meta->getAttribute('content');
        $rmetas[$property] = $content;
    }
    return $rmetas;
}

/**
    @param array $scrapeData
        An array containing the relevant og data
*/
function cleanScrapeData($scrapeData, $link){
    $newScrapeData = array(
        "title" => $scrapeData["og:title"],
        "image" => $scrapeData["og:image"],
        "description" => $scrapeData["og:description"],
    );

    // og:image is often a relative link
    // If link does not start with http, it is relative
    if (substr($newScrapeData["image"], 0, 4) !== "http"){
        $link = rtrim($link, "/");
        // Append relative link to absolute link to get full url
        $newScrapeData["image"] = $link . $newScrapeData["image"];
    }
    return $newScrapeData;
}

/**
    @param array $scrapeData
        Contains key/data pairs for og:title, image, and description

    @return boolean
        False on failed database entry
*/
function insertToDB($scrapeData, $short, $link){
    global $mysql;
    $query = "SELECT short FROM linkTable WHERE short='$short'";
    $result = $mysql->query($query)->fetch_assoc();
    // If the short already exists in the database
    if ($result !== NULL){
        return false;
    }
    else{
        $title = $mysql->escape_string($scrapeData["title"]);
        $image = $mysql->escape_string($scrapeData["image"]);
        $description = $mysql->escape_string($scrapeData["description"]);
        $short = $mysql->escape_string($short);
        $link = $mysql->escape_string($link);
        $query = "INSERT INTO linkTable 
            VALUES (
                '$title',
                '$image',
                '$description',
                '$short',
                '$link'
            )";
        $mysql->query($query);
        return true;
    }
}

/**
    @param String $oldShort
        Preserved old short to be used as key

    @param array $ogs
        Contains key/data pairs for og:title, image, and description
*/
function updateDBEntry($oldShort, $ogs, $short, $link){
    global $mysql;
    $title = $mysql->escape_string($ogs["title"]);
    $image = $mysql->escape_string($ogs["image"]);
    $description = $mysql->escape_string($ogs["description"]);
    $short = $mysql->escape_string($short);
    $link = $mysql->escape_string($link);
    $query = "UPDATE linkTable SET title='$title', image='$image', description='$description', short='$short', link='$link' WHERE short='$oldShort'";
    $mysql->query($query);
}

/**
    @param String $delete
        This is the short to be used as a key to delete an entire entry from the database
*/
function deleteThis($delete){
    global $mysql;
    $mysql->escape_string($delete);
    $query = ("DELETE FROM linkTable where short='$delete'");
    $mysql->query($query);
}

/**
    @param String $delete
        This is the short to be used as a key to delete an entire entry from the database
*/
function showEditBox(){
    global $mysql;
    $short = $_POST["editRequest"];
    $query = "SELECT * FROM linkTable WHERE short = '$short'";
    $result = $mysql->query($query)->fetch_assoc();
    $editTitle = $result["title"];
    $editImage = $result["image"];
    $editDescription = $result["description"];
    $editShort = $result["short"];
    $editLink = $result["link"];
    echo "
        <div class='edit-box' id='edit-container'>
            <div class='container'>
                <form method='post' id='newData'>
                    <h2>Title</h2>
                    <input type='text' name='newTitle' value='$editTitle'>
                    <h2>Image Link</h2>
                    <input type='text' name='newImage' value='$editImage'>
                    <h2>Description</h2>
                    <textarea rows='5' name='newDescription'>$editDescription</textarea>
                    <input type='hidden' name='oldShort' value='$short'>
                    <h2>Short</h2>
                    <input type='text' name='newShort' value='$editShort'>
                    <h2>Full Link</h2>
                    <input type='text' name='newLink' value='$editLink'>
                    <input type='submit' value='submit'> </form>
                <button onclick=\"deleteItem('$short')\" id='delete-button'>Delete</button>
                <button onclick='cancel()' id='cancel-button'>Cancel</button>
                <button onclick='submit()'>Submit</button>
            </div>
        </div>
    ";
}

/**
    Prints existing shortened links to the main console
*/
// TODO Other sorting and filtering options
function printLinks(){
    global $mysql;
    // Prints by shorts in alphabetical order
    $query = "SELECT link, short FROM linkTable ORDER BY short";
    $result = $mysql->query($query);
    while ($row = $result->fetch_assoc()){
        // Clicking on full link will take you to the destination in a new tab
        echo "<div><a href='${row['link']}' target='_blank'>${row['link']}</a></div>";
        $withQuotes = "'${row['short']}'";
        // Clicking on the short will open a prompt with the text highlighted
        // TODO Automatic copy to clipboard on click with fallback methods
        echo "<div><span onclick=\"popup($withQuotes)\">${row['short']}</span><img src='edit.svg' onclick=\"edit('${row['short']}')\"></div>";
    }
}

/**
    The main code
*/
// After a form submit for a new link
if (isset($_POST["link"])){
    $link = $_POST["link"];
    $short = $_POST["short"];
    // Add video to database with scraped og - if failed, return false
    if (!addVideoToShorthand($short, $link)){
        $_SESSION["flash"] = "Short already exists. Not replacing with new one.";
    }
    $_POST = array();
}

// After a form submit to delete a preexisting link
if (isset($_POST["delete"])){
    deleteThis($_POST["delete"]);
    $_POST = array();
}

if (isset($_SESSION['flash'])){
    echo "<div id='flash'><h2>${_SESSION['flash']}</h2><h3>Click to dismiss</h3></div>";
    unset($_SESSION['flash']);
}

if (isset($_POST["editRequest"])){
    showEditBox();
    $_POST = array();
}

if (isset($_POST["newTitle"])){
    $ogs = array(
        "title" => $_POST["newTitle"],
        "image" => $_POST["newImage"],
        "description" => $_POST["newDescription"],
    );
    $oldShort = $_POST["oldShort"];
    $newShort = $_POST["newShort"];
    $newLink = $_POST["newLink"];
    updateDBEntry($oldShort, $ogs, $newShort, $newLink);
    $_POST = array();
}
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="ui.css">
    <script src="script.js"></script>
</head>

<body>
    <div class="container">
        <h1>Shortened Links</h1>
        <form method="post" id="createForm">
            <input type="text" name="link" placeholder="full link">
            <input class="right" type="text" name="short" placeholder="abbreviation">
            <input type="submit"> </form>
        <div class="links">
            <?php printLinks(); ?>
        </div>
        <form method="post" id="deleteForm">
            <input type="hidden" name="delete" id="delete"> 
            <input type="submit"> </form>
        <form method="post" id="editForm" class="hidden">
            <input type="text" name="editRequest" id="editField"> </form>
    </div>
</body>

</html>