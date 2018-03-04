<?php
/**
 * @license CC0-1.0
 */
namespace zencodex\PackagistCrawler;

use PDO;
use DateTime;

class ExpiredFileManager
{
    /** @type PDO $pdo */
    private $pdo;

    /** @type int $expire */
    private $expire;

    function __construct($dbpath, $expire)
    {
        if (!is_string($dbpath)) {
            throw new \InvalidArgumentException('expect string but passed ' . gettype($dbpath));
        }

        if (file_exists($dbpath) && !is_writable($dbpath)) {
            throw new \RuntimeException($dbpath . ' is not writable');
        }

        $this->expire = $expire;

        $this->pdo = $pdo = new PDO("sqlite:$dbpath", null, null, array(
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ));
        $pdo->beginTransaction();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS expired ('
            .'path TEXT PRIMARY KEY, expiredAt INTEGER'
            .')'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS expiredAtIndex'
            .' ON expired (expiredAt)'
        );
    }

    function __destruct()
    {
        $this->pdo->commit();
        $this->pdo->exec('VACUUM');
    }

    /**
     * add record into expired.db
     * @param string $fullpath expired json file path
     * @param integer $now timestamp (optional)
     * @return void
     */
    function add($fullpath, $now=null)
    {
        static $insert, $path, $expiredAt;
        empty($now) or $now = $_SERVER['REQUEST_TIME'];

        if (empty($insert)) {
            $insert = $this->pdo->prepare(
                'INSERT OR IGNORE INTO expired(path,expiredAt)'
                .' VALUES(:path, :expiredAt)'
            );
            $insert->bindParam(':path', $path, PDO::PARAM_STR);
            $insert->bindParam(':expiredAt', $expiredAt, PDO::PARAM_INT);
        }

        $path = $fullpath;
        $expiredAt = $now;
        $insert->execute();
    }

    /**
     * delete record from expired.db
     * @param string $fullpath expired json file path
     * @return void
     */
    function delete($fullpath)
    {
        static $delete, $path;

        if (empty($delete)) {
            $delete = $this->pdo->prepare(
                'DELETE FROM expired WHERE path = :path'
            );
            $delete->bindParam(':path', $path, PDO::PARAM_STR);
        }

        $path = $fullpath;
        $delete->execute();
    }

    /**
     * get file list from expired.db
     * @param integer $from timestamp
     * @return Traversable (List<string>)
     */
    function getExpiredFileList($until=null)
    {
        isset($until) or $until = $_SERVER['REQUEST_TIME'] - $this->expire * 60;

        $stmt = $this->pdo->prepare(
            'SELECT path FROM expired WHERE expiredAt <= :expiredAt'
        );
        $stmt->bindValue(':expiredAt', $until, PDO::PARAM_INT);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_COLUMN, 0);
        $list = array();

        foreach ($stmt as $file){
            $list[] = $file;
        }

        return $list;
    }
}
