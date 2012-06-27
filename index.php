<?php
error_log(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);
header('Content-Type: text/html; charset=utf-8');

require_once 'Zend/Loader/Autoloader.php';

Zend_Loader_Autoloader::getInstance()->registerNamespace('Zend');

$db = new Zend_Db_Adapter_Pdo_Mysql(array(
    'host' => 'localhost',
    'username' => 'root',
    'password' => 'root',
    'dbname' => 'eksi-sinema',
    'charset' => 'utf8'
));

define('EKSI_ENTRY_PAGE_TEMPLATE', 'http://www.eksisozluk.com/show.asp?t=');

$titles = array(
    "2009'un en iyi filmleri",
    "2008 yılının en iyi filmleri",
    "2007'nin en iyi filmleri",
    "2006'nın en iyi filmleri",
    "2005'in en iyi filmleri",
    "2004'ün en iyi filmleri",
    "2003'ün en iyi filmleri",
    "2002'nin en iyi filmleri",
    "2001'in en iyi filmleri",
    "2000'in en iyi filmleri",
    "sözlükçülerin en iyi 10 film listesi",
    "sözlükçülerin en iyi 10 bilimkurgu film listesi",
    "adları bir türlü hatırlanamayan filmler",
    "çocukken izlenip net hatırlanmayan filmler",
    "ikinci kez izlemesi ilkinden güzel olan filmler",
    "bittiğinde oha dedirten filmler",
    "insanı tribe sokan filmler",
    "aranıp da bulunamayan filmler",
    "insana yaşama sevinci veren filmler",
    "erkekleri ağlatan filmler",
    "ölünceye kadar defalarca izlenmesi gereken filmler",
    "uzun süre seyretmeyince özlenen filmler",
    "tesadüfen izlenen muhteşem filmler",
    "birden fazla seyredilebilirliği olan filmler",
    "yazarların rol almak istediği filmler ve rolleri",
    "durduk yerde adamın amına koyan filmler",
    "az kişi tarafından bilinen şaheser filmler",
    "ağlatan filmler",
    "ekşi sinema top 250",
    "içinde olmak istenen filmler",
    "mutlaka izlenmesi gereken filmler",
    "en etkili açılış sahneli filmler",
    "bünyeye ağır gelen filmler",
    "hayata bakış açısını değiştiren filmler",
    "aşık olma isteği uyandıran filmler",
    "arşivlenecek filmler",
    "hayatın anlamını sorgulatan filmler",
    "sevgiliyle gidilmesi gereken filmler",
    "unutulmayacak film sahneleri",
    "unutulmayan film replikleri",
    "aşık olunan film karakterleri",


);

/*foreach ($titles as $title) {
    $url =  EKSI_ENTRY_PAGE_TEMPLATE . urlencode($title);
    $pageBody = downloadPage($url);
    $pageCount = getTotalPageCountForTitle($pageBody);

    for ($i = 1; $i <= $pageCount; $i++) {
        $pageBody = downloadPage($url . '&p=' . $i);
        $list = parseLinks($pageBody);
        addListToDb($list);
    }
} */

$results = calculateFrequency();

foreach ($results as $result) {
    echo $result['title'] . '-------' . $result['frequency'] . "<br>" ;
}

function calculateFrequency()
{
    global $db;

    return $db
        ->fetchAll('select title, count(*) as frequency from list group by title order by count(*) desc');
}

function addListToDb($list)
{
    global $db;

    foreach ($list as $item) {
        $db->insert('list', array('title' => $item));
    }
}

function parseLinks($pageBody)
{
    $dom = new Zend_Dom_Query($pageBody);
    $links = $dom->query('a[href*="show"]');
    $texts = array();

    $blacklist = array(
        '<<',
        '>>',
        'sourtimes entertainment',
        'konulu videolar',
        '*'
    );

    foreach ($links as $link) {
        if (is_numeric($link->nodeValue) || empty($link->nodeValue) || in_array($link->nodeValue, $blacklist)) {
            continue;
        }
        $texts[] = $link->nodeValue;
    }

    return $texts;
}

function downloadPage($url)
{
    if (($body = getCachedPage($url)) === false) {
        $body = getHTTPClient()->setUri($url)->request()->getBody();
        cachePage($url, $body);
    }

    return $body;
}

function getTotalPageCountForTitle($pageBody)
{
    $dom = new Zend_Dom_Query($pageBody);
    $pages = $dom->query('.pagis option')->count();

    if ($pages === 0) {
        return 1;
    } else {
        return ($pages / 2) + 1;
    }

}

/**
 * @param bool $reset
 * @return Zend_Http_Client
 */
function getHTTPClient($reset = false)
{
    if (!Zend_Registry::isRegistered('httpClient') || $reset === true) {
        $httpClient = new Zend_Http_Client();
        $httpClient->setCookieJar();

        $httpClient->setHeaders('User-Agent', 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.83 Safari/535.11');
        $httpClient->setHeaders('Accept-Charset', 'ISO-8859-9,utf-8;q=0.7,*;q=0.3');
        $httpClient->setHeaders('Accept-Encoding', 'gzip,deflate,sdch');

        Zend_Registry::set('httpClient', $httpClient);
    }

    return Zend_Registry::get('httpClient');
}

function cachePage($url, $body)
{
    file_put_contents('temp/' . slugify($url), $body);
}

function getCachedPage($url)
{
    $filename = 'temp/' . slugify($url);
    return (file_exists($filename)) ? file_get_contents($filename) : false;
}

function slugify($string)
{
    $url = $string;
    $url = preg_replace('~[^\\pL0-9_]+~u', '-', $url); // substitutes anything but letters, numbers and '_' with separator
    $url = trim($url, "-");
    $url = iconv("utf-8", "us-ascii//TRANSLIT", $url); // TRANSLIT does the whole job
    $url = strtolower($url);
    $url = preg_replace('~[^-a-z0-9_]+~', '', $url); // keep only letters, numbers, '_' and separator
    return $url;
}