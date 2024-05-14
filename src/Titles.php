<?php
namespace Concrete\Package\Finder;
use Concrete\Core\Controller\Controller as RouteController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use PDO;
use User;
use Page;
mb_internal_encoding("UTF-8");


/**
  Infer occurrences count from a matchinfo SQLite byte blob
  Is a bit slower than offsets, unavailable in sqlite 3.6.20 (CentOS6)
  but more precise with phrase query
$db->sqliteCreateFunction('matchinfo2occ', 'Sqlite::matchinfo2occ', 1);
$res = $db->prepare("SELECT matchinfo2occ(matchinfo(search, 'x')) AS occ , text FROM search  WHERE text MATCH ? ");
$res->execute(array('"Felix the cat"'));
« Felix the cat, Felix the cat »
the cat felix       = 6
"felix the cat"     = 2
"felix the cat" the = 4
  matchinfo(?, 'x')
  32-bit unsigned integers in machine byte-order
  3 * cols * phrases
  1) In the current row, the number of times the phrase appears in the column.
  2) The total number of times the phrase appears in the column in all rows in the FTS table.
  3) The total number of rows in the FTS table for which the column contains at least one instance of the phrase.
*/
function matchinfo2occ($matchinfo)
{
  $ints = unpack('L*', $matchinfo);
  $occs = 0;
  $max = count($ints)+1;
  // option par défaut, pcx, 
  for($a = 3; $a <$max; $a = $a+3 ) {
    $occs += $ints[$a];
  }
  return $occs;
}


class Titles extends RouteController
{
  static $sqlfile = DIR_FILES_UPLOADED_STANDARD."/rougemont/ddr.sqlite";
  static $pdo;
  static $insert;
  static $tsv = "";
  /**
   * Renew a database with an SQL script to create tables
   */
  static function sqlcreate($sqlfile)
  {
    if (file_exists($sqlfile)) unlink($sqlfile);
    self::mkdir(dirname($sqlfile));
    $pdo = self::sqlopen($sqlfile);
    @chmod($sqlfile, 0775);
    return $pdo;
  }
  /**
   * Open a pdo link
   */
  static private function sqlopen($sqlfile)
  {
    $dsn = "sqlite:".$sqlfile;
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("PRAGMA temp_store = 2;");
    return $pdo;
  }
  /**
   * A safe mkdir dealing with rights
   */
  static function mkdir($dir)
  {
    if (is_dir($dir)) return false;
    if (!mkdir($dir, 0775, true)) throw new Exception("Directory not created: ".$dir);
    @chmod(dirname($dst), 0775);  // let @, if www-data is not owner but allowed to write
  }
  
  /**
   *
   */
  private static function load()
  {
    $start_time = microtime(true); 
    self::$pdo = self::sqlcreate(self::$sqlfile);
    self::$pdo->exec("
PRAGMA encoding='UTF-8';
CREATE TABLE data (
  path    TEXT,
  sort    INTEGER,
  title   TEXT,
  name    TEXT,
  description TEXT
);
CREATE INDEX data_sort ON data(sort);
CREATE VIRTUAL TABLE page USING fts4 (
  path, 
  title,
  sort    INTEGER,
  notindexed=path,
  prefix='2,4',
  tokenize=unicode61 'remove_diacritics=1'
);
CREATE VIRTUAL TABLE page_terms USING fts4aux(page);
    ");

    
    // input page object 
    
    
    $sql = "INSERT INTO data(path, sort, title, name, description) VALUES (?, ?, ?, ?, ?);";
    self::$insert = self::$pdo->prepare($sql);
    self::$pdo->beginTransaction();

    self::crawl(Page::getByID(Page::getHomePageID()), true);
    self::$pdo->commit();
    /*
    self::$tsv .= "path\tname\ttitle\tdescription\n";
    self::$tsv .= microtime(true) - $start_time;
    */
    self::$pdo->exec("INSERT INTO page (path, title, sort) SELECT path, title, sort FROM data ORDER BY sort, rowid");
    self::$pdo->exec("INSERT INTO page(page) VALUES('optimize');");
  }
  
  /**
   * Insert children
   */
  private static function crawl($page)
  {
    if (Page::getHomePageID() === $page->getCollectionID()) {
      // do not exclude root from nav
    }
    else if ($page->getAttribute('exclude_nav')) {
      return;
    }
    self::insertPage($page);
    $path = $page->getCollectionPath();
    $children = $page->getCollectionChildren();
    $count = count($children);
    if ($path == '/livres') {
      for($i = $count - 1; $i >=0; $i--) {
        self::crawl($children[$i]);
      }
    }
    else {
      for($i = 0; $i < $count; $i++) {
        self::crawl($children[$i]);
      }
    }
  }
  
  private static function insertPage($page)
  {
    if ($page->getAttribute('exclude_nav')) return;
    $sorting = array(
      "@^$@" => 0,
      "@^/[^/]+$@" => 1,
      "@^/[^/]+/[^/]+$@" => 2,
      "@^/livres/[^/]+/[^/]+$@" => 4,
      "@^/.*$@" => 3,
    );
    // populate tsv to output
    $title = $page->getAttribute('meta_title');
    if (!$title) $title = $page->getCollectionName();
    $path = $page->getCollectionPath();
    $sort = preg_replace(array_keys($sorting), array_values($sorting), $path);
    // path, sort, title, name, description
    self::$insert->execute(array($path, $sort, $title, $page->getCollectionName(), $page->getCollectionDescription()));
    $subheads = $page->getAttribute('subheads');
    foreach (preg_split('/\r\n|\r|\n/', $subheads) as $line) {
      if(!$line) continue;
      list($anchor, $title) = explode("\t", $line);
      self::$insert->execute(array($path.'#'.$anchor, 4, $title, null, null));
    }
  }


  public function echo() {
    $html = "";
    $start_time = microtime(true); 
    $uinfo = new User();
    $admin = $uinfo->isLoggedIn();
    
    
    if(!$admin); // do not allow admin op by anonymous
    else if(isset($_POST['reindex'])) {
      self::load($pdo);
      $pdo = self::sqlopen(self::$sqlfile);
      $stmt = $pdo->prepare("SELECT count(*) FROM page");
      $stmt->execute();
      list($count) = $stmt->fetch();
      $tsv = "$count titres\n";

      $stmt = $pdo->prepare("SELECT * FROM page"); 
      $stmt->execute();
      while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tsv .= implode("\t", $row)."\n";
      }
      return Response::create(
        $tsv, 
        200, 
        [
          'Content-Type'=>'text/plain',
          'Cache-Control' => 'no-cache, must-revalidate',
          'Expires' => 'Sat, 26 Jul 1997 05:00:00 GMT',
        ]
      )->setCharset("UTF-8");
    }
    
    $pdo = self::sqlopen(self::$sqlfile);
    list($reqpath) = explode("?", $_SERVER['REQUEST_URI'], 2);
    
    
    // $pdo->sqliteCreateFunction('rank', "Concrete\Package\Finder\matchinfo2occ", 1);
    if (isset($_REQUEST['q']) && $_REQUEST['q']) {
      $q = trim($_REQUEST['q']);
      $limit = 100;
      // no limit if last term is not a joker and more than 2 letters
      if (mb_substr($q, -1) != '*' && mb_strlen($q) > 2) $limit = -1;
      
      $stmt = $pdo->prepare("SELECT count(*) FROM page WHERE page MATCH ?");
      $stmt->execute(array($q));
      list($count) = $stmt->fetch();
      if ($limit < 0 || $count <= $limit) $html .= "<p class=\"count\"><b>$count</b> / <b>$count</b> titres contenant <b>$q</b></p>";
      else $html .= "<p class=\"count\"><b>$limit</b> titres / <b>$count</b> contenant <b>$q</b></p>";
      // $stmt = $pdo->prepare("SELECT path, snippet(page, '<mark>', '</mark>', '…', -1, 64) as snippet, rank(matchinfo(page)) as rank FROM page WHERE page MATCH ? ORDER BY rank DESC, docid LIMIT ? OFFSET 0");
      $stmt = $pdo->prepare("SELECT path, snippet(page, '<mark>', '</mark>', '…', -1, 64) FROM page WHERE page MATCH ? LIMIT ? OFFSET 0"); 
      $stmt->execute(array($q, $limit));
      $html .=  '<ul class="nav">'."\n";
      while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        list($class) = explode("/", trim($row[0], '/'));
        $html .=  '<li class="result '.$class.'"><a href="'.DIR_REL.$row[0].'">'.$row[1]."</a></li>\n";
      }
      $html .=  "</ul>\n";
    }
    // liste complète
    else if (true) {
      $stmt = $pdo->prepare("SELECT path, title, sort FROM page"); 
      $stmt->execute();
      $html .=  '<ul class="nav">'."\n";
      while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        if ($row[2] >=3) break;
        list($class) = explode("/", trim($row[0], '/'));
        $html .=  '<li class="result '.$class.'"><a href="'.DIR_REL.$row[0].'">'.$row[1]."</a></li>\n";
      }
      $html .=  "</ul>\n";
    }
    else {
      $limit = 200;
      
      $stmt = $pdo->prepare("SELECT * FROM page_terms WHERE col = 1 ORDER BY occurrences DESC"); // vérifier l’index
      $stmt->execute();
      $first = true;
      $html .=  "<p>\n";
      while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        if ($first) $first = false;
        else $html .=  ",\n";
        $html .=  '<a href="'.$reqpath.'?q='.$row[0].'">'.$row[0]."</a>";
      }
      $html .=  ".</p>\n";
    }
    
    
    $html .=  "<!-- ".(microtime(true) - $start_time)."s. -->\n";
    
    if ($admin) {
      $html .=  '<form action="'.$_SERVER['REQUEST_URI'].'" method="post"><button name="reindex" type="submit">Réindexer</button></form>';
    }
    // return new JsonResponse($data);
    return Response::create($html, 200, array(
        'Content-Type'=>'text/html',
        'Cache-Control' => 'no-cache, must-revalidate',
        'Expires' => 'Sat, 26 Jul 1997 05:00:00 GMT',
      )
    )->setCharset("UTF-8");

  }
}
?>
