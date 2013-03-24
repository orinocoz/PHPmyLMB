<?php
/*
 * PHPmyLMP, a Lightweight media browser.
 * Generate a web page with media contents such as videos and audios.
 * Very useful to share with people :)
 * Forked from mitsumedia (https://github.com/mitsukarenai/mitsumedia) and
 * *really* redesigned code part.
 * LICENCE is WTFPL.
 */

require('config.inc.php');

/**
 * @desc Construct an array with all files found.
 *
 * @param string $sort Mode used for sorting files.
 *  Default: asc
 *  Can be: asc or mtime
 *
 * @return array $filesArray
 */
function getFiles($sort = 'asc') {

    global $conf;
    /* Find all dir and in each dir get files. */
    $directories = glob('*', GLOB_ONLYDIR);
    foreach ($directories as $dir) {
        $files = glob('./' . $dir . '/' . $conf['allowed_extensions'], GLOB_BRACE);
        foreach ($files as $file) {
            $name = explode("/", $file);
            $mtime = filemtime($file);
            $filesValues[$mtime] = array(
                'name' => $name[2],
                'mtime' => $mtime,
            );
        }
        if ($sort == 'mtime') {
            krsort($filesValues);
            $filesArray[$dir] = $filesValues;
        } else { // Default to ascending.
            $filesArray[$dir] = $filesValues;
        }
        unset($filesValues);
    }
    return $filesArray;
}

/**
 * @desc Contruct the explorer HTML part.
 * @return string $explorer HTML code to insert.
 */
function explorerHTML() {

    (isset($_GET['sort'])) ? $sort = $_GET['sort'] : $sort = 'asc';
    $filesArray = getFiles($sort);
    $explorer = '';
    foreach ($filesArray as $dirname => $files) {
        $dirnameurlencoded = rawurlencode($dirname);
        $explorer .= <<<EOT

    <div class="vignette">
        <div class="title">$dirname/</div>
            <ul>

EOT;
        foreach($files as $file) {
            $filenameurlencoded = rawurlencode($file['name']);
            $explorer .= <<<EOT

                <li>
                    <a href="$dirnameurlencoded/$filenameurlencoded"><img title="Right click → Save as" alt="" src="save.png"></a>
                    <a href="?file=$dirnameurlencoded/$filenameurlencoded&amp;sort=$sort">{$file['name']}</a>
                </li>

EOT;
        }
        $explorer .= <<<EOT

        </ul>
    </div>

EOT;
    }
    return $explorer;
}

/* Feed part. */
if (isset($_GET['feed'])) {
    header('Content-Type: application/atom+xml; charset=UTF-8');
    $date = date('c');
    print <<<EOT
<?xml version="1.0" encoding="utf-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
    <title type="text">{$conf['title']}</title>
    <link rel="self" type="application/atom+xml" href="{$conf['uri']}/?feed" />
    <id>tag:phpmylmb,2000:1</id>
    <updated>$date</updated>

EOT;
    $filesArray = getFiles();
    foreach ($filesArray as $dirname => $files) {
        foreach ($files as $file) {
            $nameurlencoded = rawurlencode($file['name']);
            $dirnameurlencoded = rawurlencode($dirname);
            $updated = date(DATE_ATOM, $file['mtime']);
            $entries[$file['mtime']] = <<<EOT

    <entry>
        <title>$dirname/{$file['name']}</title>
        <link href="{$conf['uri']}/?file=$dirnameurlencoded/$nameurlencoded"/>
        <id>{$conf['uri']}/?file=$dirnameurlencoded/$nameurlencoded</id>
        <updated>$updated</updated>
        <author><name>{$conf['author']}</name></author><summary>{$file['name']}</summary>
    </entry>

EOT;
        }
    }
    /* Finally return the last entries ($conf['feed_items']) for the feed.*/
    krsort($entries);
    $entries = array_slice($entries, 0, $conf['feed_items']);
    foreach ($entries as $entry) {
        print $entry;
    }
    print '</feed>';
    exit(0);
}

/* File part. When user has clicked on a file. Generate the embeded media. */
if (isset($_GET['file'])) {
    $path = urldecode($_GET['file']);
    /* Verify if the file exists and construct the embedded media. */
    if (file_exists('./' . $path)) {
        $mtime = filemtime($path);
        $mtimeATOM = date(DATE_ATOM, $mtime);
        $mtimeHuman = date(DATE_RFC822, $mtime);
        $mediatitle = $path;
        $pathurlencoded = rawurlencode($path);
        $mediacode = <<<EOT

            <div class="fileinfo">
                File: <time datetime="$mtimeATOM">$path</time><br />
                Added: $mtimeHuman
            </div>

EOT;
        if (strpos($path, '.webm')) {
            $mediacode .= "\t\t\t" . '<video id="media" src="' . $pathurlencoded  .'" controls="" autoplay="">Your browser doesn\'t support this format. Try Firefox.</video>';
        } elseif (preg_match('/(.opus|.ogg)/i', $path)) {
            $mediacode .= "\t\t\t" . '<audio id="media" src="' . $pathurlencoded  .'" controls="" autoplay="">Your browser doesn\'t support this format. Try Firefox.</audio>';
        } elseif (preg_match('/(.jpg|.jpeg|.png|.webp|.svg|.gif)/i', $path)) {
            $mediacode .= "\t\t\t" . '<img id="media" alt="' . $mediatitle . '" src="' . $pathurlencoded . '"/>';
        }
    } else {
        header("HTTP/1.0 404 File not found");
        print "<h1>404 File not found</h1>";
        exit(1);
    }
}

/* Below the HTML part. */
$mediacode = (isset($mediacode)) ? $mediacode : '';
$mediatitle = (isset($mediatitle)) ? $mediatitle : 'Home';
print <<<EOT
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <title>{$conf['title']} – $mediatitle</title>
    <meta name="description" content="{$conf['desc']}" />
    <link rel='stylesheet' href='style.css' type='text/css' media='screen' />
    <link rel="icon" type="image/png" href="favicon.png" />
    <link rel="alternate" type="application/atom+xml" title="ATOM last uploaded files" href="{$conf['uri']}/?feed" />
    <meta name="robots" content="index" />
</head>
<body>
<div id="header">
    {$conf['header']}
</div>
<div id="content">
    <div style="min-height:150px;">
        <h1>{$conf['title']}</h1>
        <span style="font-size:small">{$conf['desc']}</span><br>
        <div id="mediacode">
            $mediacode
        </div>
    </div>

EOT;
        $options = '';
        if (isset($_GET['sort']) && $_GET['sort'] == 'asc') {
            $options .= '<option value="?sort=asc" selected="">Ascending</option>' ."\n";
        } else {
            $options .= '<option value="?sort=asc">Ascending</option>' ."\n";
        } if (isset($_GET['sort']) && $_GET['sort'] == 'mtime') {
            $options .= "\t\t\t" . '<option value="?sort=mtime" selected="">Last uploaded files</option>';
        } else {
            $options .= "\t\t\t" . '<option value="?sort=mtime">Last uploaded files</option>';
        }
        print <<<EOT

    <small>
        Sort by:
        <select onChange="if (this.value) window.location.href=this.value">
            $options
        </select>
    </small>
    <br />

EOT;
        /* Construct the "explorer". */
        print explorerHTML();
print <<<EOT

</div>
    <div id="footer">
        {$conf['footer']}
    </div>
</body>
</html>
EOT;
?>
