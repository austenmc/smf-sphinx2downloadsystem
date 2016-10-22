<?php

$servername = 'localhost';
$dbname = 'smf';
$username = 'admin';
$password = 'mypass';

$oldCategoryTable = 'smf_download_cats';
$oldDownloadTable = 'smf_download_items';

$newCategoryTable = 'smf_down_cat';
$newDownloadTable = 'smf_down_file';

function main()
{
    global $servername, $dbname, $username, $password;

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        // set the PDO error mode to exception
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "Connected successfully $servername:$dbname\n"; 
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }

    $categories = readOldCategories($conn);
    $downloads = readOldDownloads($conn);

    $categoryMapping = writeNewCategories($conn, $categories, $downloads);

    writeNewDownloads($conn, $downloads, $categoryMapping);
}

// Returns an associative array of oldCategoryId => array of table
// columns and values.
function readOldCategories($conn)
{
    global $oldCategoryTable;

    $stmt = $conn->prepare("SELECT id, name, description, isSub, permission FROM $oldCategoryTable;");
    $stmt->execute();
    $result = $stmt->fetchAll();

    $mapping = [];

    foreach ($result as $row) {
        $mapping[$row['id']] = $row;
    }
    return $mapping;
}

// Returns a mapping of old category id to new category id.
function writeNewCategories($conn, $categories, $downloads)
{
    global $newCategoryTable;

    // Count the number of downloads in each category and add them to $totals.
    $totals = [];
    foreach ($downloads as $oldId => $d) {
        $oldCategoryId = $d['cat'];
        if (array_key_exists($oldCategoryId, $totals)) {
            $count = $totals[$oldCategoryId];
            $count += 1;
            $totals[$oldCategoryId] = $count;
        } else {
            $totals[$oldCategoryId] = 1;
        }
    } 

    // Progressively insert the categories, starting with the root categories,
    // since you can't insert a subcategory before its parent.
    $inserted = [];

    $stmt = $conn->prepare("INSERT INTO $newCategoryTable (title, description, id_parent, total) VALUES(?, ?, ?, ?)");
    do {;
        foreach ($categories as $oldId => $c) {
            $oldParentId = $c['isSub'];
            if (array_key_exists($oldId, $inserted)) {
                // Already inserted this one.
                continue;
            }
            // If this is a root category or we've already inserted the parent,
            // insert a new category row.
            if ($oldParentId == 0 || array_key_exists($oldParentId, $inserted)) {
                $newParentId = $oldParentId == 0 ? 0 : $inserted[$oldParentId];
                $name = $c['name'];

                $total = 0;
                if (array_key_exists($oldId, $totals)) {
                    $total = $totals[$oldId];
                }

                echo "Inserting category oldId={$oldId} oldParentId={$oldParentId} name={$name}...";
                $stmt->execute([$name, $c['description'], $newParentId, $total]);
                // build a mapping of old to new IDs.
                $newId = $conn->lastInsertId();
                $inserted[$oldId] = $newId;
                echo "newId={$newId}\n";
            }
        }
    } while (count($inserted) < count($categories));

    // Root category is still category 0, but we dont have to add it.
    $inserted[0] = 0;

    return $inserted;
}

// Returns an associative array of oldDownloadId => array of table
// columns and values.
function readOldDownloads($conn)
{
    global $oldDownloadTable;

    $stmt = $conn->prepare("SELECT id, cat, name, ddesc, filename, author_name, author_email, filesize, downloads, website, website_title, date, views FROM $oldDownloadTable");
    $stmt->execute();
    $result = $stmt->fetchAll();

    $mapping = [];

    foreach ($result as $row) {
        $mapping[$row['id']] = $row;
    }
    return $mapping;
}

function writeNewDownloads($conn, $downloads, $categoryMapping)
{
    global $newDownloadTable;

    $stmt = $conn->prepare("INSERT INTO $newDownloadTable (date, title, description, views, totaldownloads, filesize, orginalfilename, filename, id_cat, approved) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");

    foreach ($downloads as $oldId => $d) {
        $name = $d['name'];
        $oldCatId = $d['cat'];
        $newCatId = $categoryMapping[$oldCatId];
        $newDescription = $d['ddesc'] . '<br><br>Author: ' . $d['author_name'] . ' &lt;' . $d['author_email'] . '&gt;';
        echo "Inserting download oldId={$oldId} title={$name} oldCatId={$oldCatId}...";
        $stmt->execute([$d['date'], $d['name'], $newDescription, $d['views'], $d['downloads'], $d['filesize'], $d['filename'], $d['filename'], $newCatId]);
        $newId = $conn->lastInsertId();
        echo "newId={$newId} newCatId={$newCatId}\n";
     }
}

main();
