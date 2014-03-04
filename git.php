<?php



// +------------------------------------------------------------------------+
// | git-php - PHP front end to git repositories                            |
// +------------------------------------------------------------------------+
// | Copyright (c) 2006 Zack Bartel                                         |                                                  |
// +------------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or          |
// | modify it under the terms of the GNU General Public License            | 
// | as published by the Free Software Foundation; either version 2         | 
// | of the License, or (at your option) any later version.                 |
// |                                                                        |
// | This program is distributed in the hope that it will be useful,        |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of         |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the          |
// | GNU General Public License for more details.                           |
// |                                                                        |
// | You should have received a copy of the GNU General Public License      |
// | along with this program; if not, write to the Free Software            |
// | Foundation, Inc., 59 Temple Place - Suite 330,                         |
// | Boston, MA  02111-1307, USA.                                           |
// +------------------------------------------------------------------------+
// | Author: Zack Bartel <zack@bartel.com>                                  |
// +------------------------------------------------------------------------+ 

//Forked by Easton Elliott <easton@geekness.eu> 2014


// The file git repositories are loaded from
// Full path to git repo, line separated
$repo_file = "repos.txt";

// Optionally specify a directory containing git repositories
$repo_directory = null;

// Use the default CSS
$git_css = true;

// Add the git logo in the footer 
$git_logo = true;

// Page title
$title = "git-php";




// Load git repositories
// It first checks for repos in $repo_file, a directory then manually specified in the $repos array
if (file_exists($repo_file)) {
    $r = file($repo_file);
    foreach ($r as $repo) {
        $repos[] = trim($repo);
    }

// Scan the repository directory for git repositories
} else if ((file_exists($repo_directory)) && (is_dir($repo_directory))) {
    if ($handle = opendir($repo_directory)) {
        while (false !== ($file = readdir($handle))) {
            if ($file != "." && $file != "..") {
                /* TODO: Check for valid git repos */
                $repos[] = trim($repo_directory . $file);
            }
        }
        closedir($handle);
    }
} else {
    // Specify custom repositories
    $repos = array(
        "../test/mygitrepo",
        "../test/myothergitrepo"
    );
}
// Sort by newest first
sort($repos);


// Download methods
if (isset($_GET['dl'])) {
    switch ($_GET['dl']) {

        case 'targz':
            write_targz(get_repo_path($_GET['p'], $repos));
            break;

        case 'zip':
            write_zip(get_repo_path($_GET['p'], $repos));
            break;

        case 'git_logo':
            write_git_logo();
            break;

        case 'plain':
            write_plain($repos);
            break;

        case 'rss':
            generate_feed($repos);
            break;

        default:
            die('something broke');
            break;
    }
}

html_header($title);

html_style();

html_breadcrumbs();

// Check if viewing a project
if (isset($_GET['p'])) {
    html_spacer();
    html_title("Summary");
    html_summary($_GET['p'], $repos);
    html_spacer();
    // Check if we're viewing a diff of a commit
    if (isset($_GET['a']) == "commitdiff") {
        html_diff($_GET['p'], $_GET['h'], $_GET['hb'], $repos);
    } else {
        html_title("Files");
        html_browse($_GET['p'], $repos);
    }
} else {
    // Main page
    html_spacer();
    html_home($repos);
}

html_footer($git_logo);

/**
 * HTML formatted summary
 * @param string $proj A git repository
 * @param array $repos Git repos
 */
function html_summary($proj, $repos) {

    $repo = get_repo_path($proj, $repos);

    //Check if the repo is valid
    if ($repo) {
        html_desc($repo, $proj);
        if (!isset($_GET['t']) && !isset($_GET['b'])) {
            html_shortlog($repo, 6);
        }
    } else {
       die('invalid repo');
    }
}

/**
 * Browse the repo, display the git tree
 * @param string $proj A git repository
 * @param array $repos Git repos
 */
function html_browse($proj, $repos) {

    if (isset($_GET['b'])) {
        html_blob($proj, $_GET['b'], $repos);
    } else {
        // Get the tree, otherwise default to HEAD
        if (isset($_GET['t'])) {
            $tree = $_GET['t'];
        } else {
            $tree = "HEAD";
        }
        html_tree($proj, $tree, $repos);
    }
}

/**
 * View a blob
 * @param string $proj
 * @param string $blob 
 */
function html_blob($proj, $blob, $repos) {
    $repo = get_repo_path($proj, $repos);
    $out = array();

    $plain = "<a href=\"" . sanitized_url() . "p=$proj&dl=plain&h=$blob\">plain</a>";

    echo "<div style=\"float:right;padding:7px;\">$plain</div>\n";
    exec("GIT_DIR=$repo/.git git cat-file blob $blob", $out);
    echo '<div class="gitcode">';

    highlight_string(implode("\n", $out));

    echo '</div><br>';
}

/**
 * Generate HTML diff between two commits
 * @param string $proj
 * @param string $commit
 * @param string $parent 
 */
function html_diff($proj, $commit, $parent, $repos) {
    $repo = get_repo_path($proj, $repos);
    $out = array();
    exec("GIT_DIR=$repo/.git git diff $parent $commit", $out);

    echo '<div class="gitcode">';
    echo '<b>diff</b><br>';
    echo highlight_code(implode("\n", $out));
    echo '</div><br>';
}

/**
 * Get the tree of a repo
 * @param string $proj A git repository
 * @param string $tree The git tree
 * @param array $repos Git repos
 */
function html_tree($proj, $tree, $repos) {
    $t = git_ls_tree(get_repo_path($proj, $repos), $tree);

    echo '<div class="gitbrowse"><table>';

    foreach ($t as $t) {
        $plain = "";
        $perm = permissions_string($t['perm']);
        if ($t['type'] == 'tree')
            $objlink = "<a href=\"" . sanitized_url() . "p=$proj&t={$t['hash']}\">{$t['file']}</a>";
        else if ($t['type'] == 'blob') {
            $plain = "<a href=\"" . sanitized_url() . "p=$proj&dl=plain&h={$t['hash']}\">plain</a>";
            $objlink = "<a class=\"blob\" href=\"" . sanitized_url() . "p=$proj&b={$t['hash']}\">{$t['file']}</a>";
        }

        echo "<tr><td>$perm</td><td>$objlink</td><td>$plain</td></tr>";
    }
    echo '</table></div>';
}

/**
 * HTML formatted summarized git log output
 * @param string $repo Git repository
 * @param int $count Number of commits from HEAD to get
 */
function html_shortlog($repo, $count) {
    echo '<table>';
    $c = git_commit($repo, "HEAD");
    for ($i = 0; $i < $count && $c; $i++) {

        $date = date('D n/j/y G:i', intval($c['date']));
        $commit_id = $c['commit_id'];
        $parent_id = $c['parent'];

        $commit_message = short_desc($c['message'], 110);
        $diff = "<a href=\"" . sanitized_url() . "p={$_GET['p']}&a=commitdiff&h=$commit_id&hb=$parent_id\">commitdiff</a>";
        echo "<tr><td>$date</td><td>{$c['author']}</td><td>$commit_message</td><td>$diff</td></tr>\n";
        $c = git_commit($repo, $parent_id);
    }
    echo '</table>';
}

/**
 * HTML formatted repository description
 * @param string $repo Path to git repository
 * @param string $proj Git repository name
 */
function html_desc($repo, $proj) {

    // Check if the git repo has a description file
    if(file_exists("$repo/.git/description")){
        $desc = short_desc(file_get_contents("$repo/.git/description"));
    }else{
        $desc = "No description";
    }
    $owner = get_file_owner($repo);
    $last = get_last($repo);
    $git_clone_url = 'http://'.$_SERVER['HTTP_HOST'].'/git/'.$proj;
   
    echo "<table>";
    echo "<tr><td>description</td><td>$desc</td></tr>";
    echo "<tr><td>owner</td><td>$owner</td></tr>";
    echo "<tr><td>last change</td><td>$last</td></tr>";
    echo "<tr><td>URL</td><td>$git_clone_url</td></tr>";
    echo "</table>";
}

/**
 * Index page
 * @param array $repos Paths to Git repositories
 */
function html_home($repos) {

    echo "<table>";
    echo "<tr><th>Project</th><th>Description</th><th>Owner</th><th>Last Changed</th><th>Download</th></tr>";
    foreach ($repos as $repo) {

        // Check if the git repo has a description file
        if(file_exists("$repo/.git/description")){
            $desc = short_desc(file_get_contents("$repo/.git/description"));
        }else{
            $desc = "No description";
        }
        $owner = get_file_owner($repo);
        $last = get_last($repo);
        $proj = get_project_link($repo);
        $dlt = get_project_link($repo, "targz");
        $dlz = get_project_link($repo, "zip");
        $git_clone_url = 'http://'.$_SERVER['HTTP_HOST'].'/git/'.$proj;

        echo "<tr><td><a href=\"" . sanitized_url() . "p=$proj\">$proj</a></td><td>$desc</td><td>$owner</td><td>$last</td><td>$dlt | $dlz | <a href=$git_clone_url>git clone</a></td></tr>";
    }
    echo "</table>";
}

/**
 * Header
 * @param string $title Page title
 */
function html_header($title) {
    ?>
    <!DOCTYPE HTML>
    <html>
        <head>
            <meta charset="utf-8" />
            <title><?= $title; ?></title>
        </head>
    <?php
    //Add RSS feed link
    if (isset($_GET['p'])) {
        echo "<link rel=\"alternate\" title=\"{$_GET['p']}\" href=\"" . sanitized_url() . "p={$_GET['p']}&dl=rss\" type=\"application/rss+xml\" />\n";
    }
    echo '<div id="gitbody">';
}

/**
 * Git logo 
 */
function write_git_logo() {

    $git = "\x89\x50\x4e\x47\x0d\x0a\x1a\x0a\x00\x00\x00\x0d\x49\x48\x44\x52" .
            "\x00\x00\x00\x48\x00\x00\x00\x1b\x04\x03\x00\x00\x00\x2d\xd9\xd4" .
            "\x2d\x00\x00\x00\x18\x50\x4c\x54\x45\xff\xff\xff\x60\x60\x5d\xb0" .
            "\xaf\xaa\x00\x80\x00\xce\xcd\xc7\xc0\x00\x00\xe8\xe8\xe6\xf7\xf7" .
            "\xf6\x95\x0c\xa7\x47\x00\x00\x00\x73\x49\x44\x41\x54\x28\xcf\x63" .
            "\x48\x67\x20\x04\x4a\x5c\x18\x0a\x08\x2a\x62\x53\x61\x20\x02\x08" .
            "\x0d\x69\x45\xac\xa1\xa1\x01\x30\x0c\x93\x60\x36\x26\x52\x91\xb1" .
            "\x01\x11\xd6\xe1\x55\x64\x6c\x6c\xcc\x6c\x6c\x0c\xa2\x0c\x70\x2a" .
            "\x62\x06\x2a\xc1\x62\x1d\xb3\x01\x02\x53\xa4\x08\xe8\x00\x03\x18" .
            "\x26\x56\x11\xd4\xe1\x20\x97\x1b\xe0\xb4\x0e\x35\x24\x71\x29\x82" .
            "\x99\x30\xb8\x93\x0a\x11\xb9\x45\x88\xc1\x8d\xa0\xa2\x44\x21\x06" .
            "\x27\x41\x82\x40\x85\xc1\x45\x89\x20\x70\x01\x00\xa4\x3d\x21\xc5" .
            "\x12\x1c\x9a\xfe\x00\x00\x00\x00\x49\x45\x4e\x44\xae\x42\x60\x82";

    // header("Content-Type: img/png");
    //  header("Expires: +1d");
    echo $git;
    //die();
}

/**
 * HTML footer
 * 
 */
function html_footer($git_logo) {

    echo '<div class="gitfooter">';

    if (isset($_GET['p'])) {
        echo "<a class=\"rss_logo\" href=\"" . sanitized_url() . "p={$_GET['p']}&dl=rss\" >RSS</a>\n";
    }

    if ($git_logo) {
        echo '<a href="http://www.kernel.org/pub/software/scm/git/docs/">' . '<img src="' . sanitized_url() . 'dl=git_logo" style="border-width: 0px;"/></a>';
    }

    echo "</div>";
    echo "</div>";

    echo "</body>";
    echo "</html>";
}

/**
 * Get a Git tree
 * @param type $gitdir
 * @param type $tree 
 */
function git_tree($gitdir, $tree) {

    $out = array();
    $command = "GIT_DIR=$gitdir/.git git ls-tree --name-only $tree";
    exec($command, $out);
}

function get_git($repo) {

    if (file_exists("$repo")) {
        $gitdir = "$repo";
    } else {
        $gitdir = $repo;
        return $gitdir;
    }
}

/**
 * Get the file owner
 * @param type $path
 * @return string 
 */
function get_file_owner($path) {
   $owner = posix_getpwuid(fileowner($path));
   return $owner['name'];
}

/**
 * Get time of last change
 * @param string $repo A git repositor
 *
 */
function get_last($repo) {
    $out = array();
    $date = exec("GIT_DIR=$repo/.git git rev-list  --header --max-count=1 HEAD | grep -a committer | cut -f5-6 -d' '", $out);
    return date("D n/j/y G:i", intval($date));
}
/**
 * Get URL to git-php repo
 * @param string $repo A git repository
 * @param string $type Download file type
 */
function get_project_link($repo, $type = false) {
    $path = basename($repo);

    switch ($type) {
        case 'targz':
            return "<a href=\"" . sanitized_url() . "p=$path&dl=targz\">.tar.gz</a>";
            break;
        case 'zip':
            return "<a href=\"" . sanitized_url() . "p=$path&dl=zip\">.zip</a>";
            break;
        case false:
            return $path;
            break;
    }
}
/**
 * Get a single git commit
 * @param string $repo A git repository
 * @param string $cid Commit ID (hash)
 *
 */
function git_commit($repo, $cid) {
    $out = array();
    $commit = array();

    if (strlen($cid) <= 0) {
        return 0;
    }
    exec("GIT_DIR=$repo/.git git rev-list  --header --max-count=1 $cid", $out);


    if (!empty($out)) {

        $commit["commit_id"] = $out[0];


        $g = explode(" ", $out[1]);

        $commit["tree"] = $g[1];




        $g = explode(" ", $out[2]);
        $commit["parent"] = $g[1];




        $g = explode(" ", $out[3]);

        if (isset($g[3])) {


            // $commit['author'] = $g[1].' '.$g[2];
            /* variable number of strings for the name */
            /*
              for ($i = 0; $g[$i][0] != '<' && $i < 5; $i++) {
              // $commit["author"] = $g[1];

              $commit["author"] = " $g[$i] ";
              }
             */

            for ($i = 1; $g[$i][0] != '<' && $i < 5; $i++) {


                $commit["author"] = $g[1];
                $commit["author"] .= ' ' . $g[2];
                // $commit["author"] = "";
                // $commit["author"] .= " $g[$i] ";
            }

            //  $commit['author'] = $g[1].' '.$g[2];



            /* add the email */

            $commit["date"] = "{$g[++$i]} {$g[++$i]}";
            // $commit["date"] = $g[5];s
            $commit["message"] = "";
            $size = count($out);



            for (; $i < $size - 1; $i++) {
                $commit["message"] .= $out[$i];
            }
            return $commit;
        }
    }
}

/**
 * Get the git repo path
 * @param string $proj A git repository
 * @param string $repos Git repos
 */
function get_repo_path($proj, $repos) {

    foreach ($repos as $repo) {
        $path = basename($repo);
        if ($path == $proj)
            return $repo;
    }
}

/**
 * Get the contents of a tree
 * @param string $repo Git repository
 * @param string $tree Tree name 
 * @return array 
 */
function git_ls_tree($repo, $tree) {
    $ary = array();

    $out = array();
    //Have to strip the \t between hash and file
    exec("GIT_DIR=$repo/.git git ls-tree $tree | sed -e 's/\t/ /g'", $out);

    foreach ($out as $line) {
        $entry = array();
        $arr = explode(" ", $line);
        $entry['perm'] = $arr[0];
        $entry['type'] = $arr[1];
        $entry['hash'] = $arr[2];
        $entry['file'] = $arr[3];
        $ary[] = $entry;
    }
    return $ary;
}

/**
 * Sanitize a URL
 * @global type $git_embed
 * @return type 
 */
function sanitized_url() {


    /* the sanitized url */
    //  $url = "{$_SERVER['SCRIPT_NAME']}?";
    $url = filter_var("{$_SERVER['SCRIPT_NAME']}?", FILTER_SANITIZE_URL);

    return $url;


    /*
      // the GET vars used by git-php
      $git_get = array('p', 'dl', 'b', 'a', 'h', 't');


      foreach ($_GET as $var => $val) {
      if (!in_array($var, $git_get)) {
      $get[$var] = $val;
      $url.="$var=$val&amp;";
      }
      }
      return $url;
     */
}

/**
 * View a file in plain text 
 */
function write_plain($repos) {
    $repo = get_repo_path($_GET['p'], $repos);
    $hash = $_GET['h'];
    header("Content-Type: text/plain");
    $str = system("GIT_DIR=$repo/.git git cat-file blob $hash");
    echo $str;
    die();
}

/**
 * Create a tarball
 * @param string $repo Git repository
 */
function write_targz($repo) {
    $p = basename($repo);
    $proj = explode(".", $p);
    $proj = $proj[0];
    //TODO: clean this up
    exec("cd /tmp && git-clone $repo && rm -Rf /tmp/$proj && tar czvf $proj.tar.gz $proj && rm -Rf /tmp/$proj");

    $filesize = filesize("/tmp/$proj.tar.gz");
    header("Pragma: public"); // required
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: private", false); // required for certain browsers
    header("Content-Transfer-Encoding: binary");
    header("Content-Type: application/x-tar-gz");
    header("Content-Length: " . $filesize);
    header("Content-Disposition: attachment; filename=\"$proj.tar.gz\";");
    echo file_get_contents("/tmp/$proj.tar.gz");
    die();
}

/**
 * Create a zip file
 * @param string $repo Git repository
 */
function write_zip($repo) {
    $p = basename($repo);
    $proj = explode(".", $p);
    $proj = $proj[0];
    //TODO: clean this up
    exec("cd /tmp && git-clone $repo && rm -Rf /tmp/$proj && zip -r $proj.zip $proj && rm -Rf /tmp/$proj");

    $filesize = filesize("/tmp/$proj.zip");
    header("Pragma: public"); // required
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: private", false); // required for certain browsers
    header("Content-Transfer-Encoding: binary");
    header("Content-Type: application/x-zip");
    header("Content-Length: " . $filesize);
    header("Content-Disposition: attachment; filename=\"$proj.zip\";");
    echo file_get_contents("/tmp/$proj.zip");
    die();
}

/**
 * Generate RSS feed 
 */
function generate_feed($repos) {
    $proj = $_GET['p'];
    $repo = get_repo_path($proj, $repos);

    $link = "http://{$_SERVER['HTTP_HOST']}" . sanitized_url() . "p=$proj";
    $c = git_commit($repo, "HEAD");

    header("Content-type: application/rss+xml", true);

    echo '<?xml version="1.0" encoding="utf-8"?>';
    ?>
       <rss version="2.0">
            <channel>
                <title><?php echo $proj ?></title>
                <link><?php echo $link ?></link>
                <description><?php echo $proj ?></description>
                <generator>git-php</generator>
                <language>en</language>
    <?php for ($i = 0; $i < 10 && $c; $i++): ?>
                    <item>
                        <title><?php echo htmlspecialchars($c['message']); ?></title>
                        <link><?php echo $link ?></link>
                        <pubDate><?php echo date('D, d M Y G:i:s', intval($c['date'])) ?></pubDate>
                        <guid isPermaLink="false"><?php echo $link ?></guid>
                        <description><?php echo htmlspecialchars($c['message']); ?></description>
                       <content><pre><?php echo htmlspecialchars($c['message']); ?></pre></content>
                    </item>
        <?php
        $c = git_commit($repo, $c['parent']);
        $link = "http://{$_SERVER['HTTP_HOST']}" . sanitized_url() . "p=$proj&amp;a=commitdiff&amp;h={$c['commit_id']}&amp;hb={$c['parent']}";
    endfor;
    ?>
            </channel>
        </rss>
    <?php
    die();
}
/**
 * Return a more readable permission string
 * @param string $perms Permission
 */
function permissions_string($perms) {

    switch ($perms) {
        case '040000':
            return 'drwxr-xr-x';
        case '100644':
            return '-rw-r--r--';
        case '100755':
            return '-rwxr-xr-x';
        case '120000':
            return 'lrwxrwxrwx';

        default:
            return '----------';
    }
}

/**
 * Shorten a description
 * @param string $desc Git log message
 * @param int $size Character length
 * @return string 
 */
function short_desc($desc, $size = 25) {
    $trunc = false;
    $short = "";
    $d = explode(" ", $desc);
    foreach ($d as $str) {
        if (strlen($short) < $size)
            $short .= "$str ";
        else {
            $trunc = true;
            break;
        }
    }

    if ($trunc) {
        $short .= "...";
    }
    return $short;
}

/**
 * A space, what else?
 */
function html_spacer($text = "&nbsp;") {
    echo '<div class="gitspacer">' . $text . '</div>';
}

/**
 * Make a title
 */
function html_title($text = "&nbsp;") {
    echo '<div class="gittitle">' . $text . '</div>';
}
/**
 * Breadcrumb navigation generation
 */
function html_breadcrumbs() {
    echo '<div class="githead">';
    $crumb = '<a href="' . sanitized_url() . '">projects</a> / ';

    if (isset($_GET['p']))
        $crumb .= "<a href=\"" . sanitized_url() . "p={$_GET['p']}\">{$_GET['p']}</a> / ";

    if (isset($_GET['b']))
        $crumb .= "blob";

    if (isset($_GET['t']))
        $crumb .= "tree";

    if (isset($_GET['a']) == 'commitdiff') {
        $crumb .= 'commitdiff';
    }
    echo $crumb;
    echo "</div>";
}

function highlight($code) {

    if (substr($code, 0, 2) != '<?') {
        $code = "<?\n$code\n?>";
        $add_tags = true;
    }
    $code = highlight_string($code, 1);

    if ($add_tags) {
        //$code = substr($code, 0, 26).substr($code, 36, (strlen($code) - 74));
        $code = substr($code, 83, strlen($code) - 140);
        $code.="</span>";
    }

    return $code;
}

function highlight_code($code) {

    define('COLOR_DEFAULT', '000');
    define('COLOR_FUNCTION', '00b'); //also for variables, numbers and constants
    define('COLOR_KEYWORD', '070');
    define('COLOR_COMMENT', '800080');
    define('COLOR_STRING', 'd00');

    // Check it if code starts with PHP tags, if not: add 'em.
    if (substr($code, 0, 2) != '<?') {
        $code = "<?\n" . $code . "\n?>";
        $add_tags = true;
    }

    $code = highlight_string($code, true);

    // Remove the first "<code>" tag from "$code" (if any)
    if (substr($code, 0, 6) == '<code>') {
        $code = substr($code, 6, (strlen($code) - 13));
    }

    // Replacement-map to replace deprecated "<font>" tag with "<span>"
    $xhtml_convmap = array(
        '<font' => '<span',
        '</font>' => '</span>',
        'color="' => 'style="color:',
        '<br />' => '<br/>',
        '#000000">' => '#' . COLOR_DEFAULT . '">',
        '#0000BB">' => '#' . COLOR_FUNCTION . '">',
        '#007700">' => '#' . COLOR_KEYWORD . '">',
        '#FF8000">' => '#' . COLOR_COMMENT . '">',
        '#DD0000">' => '#' . COLOR_STRING . '">'
    );

    // Replace "<font>" tags with "<span>" tags, to generate a valid XHTML code
    $code = strtr($code, $xhtml_convmap);

    //strip default color (black) tags
    $code = substr($code, 25, (strlen($code) - 33));

    //strip the PHP tags if they were added by the script
    if ($add_tags) {

        $code = substr($code, 0, 26) . substr($code, 36, (strlen($code) - 74));
    }

    return $code;
}
/**
 * CSS
 */
function html_style() {

    // Use the stylesheet in style.css, otherwise use the default
    if (file_exists("style.css")) {
        echo '<link rel="stylesheet" href="style.css" type="text/css" />';
    }else{

    echo '<style type="text/css">';
    echo <<< EOF
            #gitbody    {
                margin: 10px 10px 10px 10px;
                border-style: solid;
                border-width: 1px;
                border-color: gray;
                font-family: sans-serif;
                font-size: 12px;
            }

            div.githead    {
                margin: 0px 0px 0px 0px;
                padding: 10px 10px 10px 10px;
                background-color: #d9d8d1;
                font-weight: bold;
                font-size: 18px;
            }

            #gitbody th {
                text-align: left;
                padding: 0px 0px 0px 7px;
            }

            #gitbody td {
                padding: 5px 0px 0px 7px;
            }

            tr:hover { background-color:#edece6; }

            div.gitbrowse a.blob {
                text-decoration: none;
                color: #000000;
            }

            div.gitcode {
                padding: 10px;
            }

            div.gitspacer   {
                padding: 1px 0px 0px 0px;
                background-color: #FFFFFF;
            }

            div.gitfooter {
                padding: 7px 2px 2px 2px;
                background-color: #d9d8d1;
                text-align: right;
            }

            div.gittitle   {
                padding: 7px 7px 7px 7px;
                background-color: #d9d8d1;
                font-weight: bold;
            }

            div.gitbrowse a.blob:hover {
                text-decoration: underline;
            }
            a.gitbrowse:hover { text-decoration:underline; color:#880000; }
            a.rss_logo {
                float:left; padding:3px 0px; width:35px; line-height:10px;
                    margin: 2px 5px 5px 5px;
                    border:1px solid; border-color:#fcc7a5 #7d3302 #3e1a01 #ff954e;
                    color:#ffffff; background-color:#ff6600;
                    font-weight:bold; font-family:sans-serif; font-size:10px;
                    text-align:center; text-decoration:none;
                }
            a.rss_logo:hover { background-color:#ee5500; }
EOF;

    echo "</style>";
    }
}
