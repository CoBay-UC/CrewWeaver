<?php

declare(strict_types=1);

namespace CrewWeaver\Models;

use PDO;

class RateLimitRepository
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected PDO $_pdo;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(PDO $pdo)
    {
        $this->_pdo=$pdo;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function consume(string $key,int $windowSeconds): int
    {
        $hash=hash('sha256',$key);$expires=gmdate('Y-m-d H:i:s',time()+$windowSeconds);$statement=$this->_pdo->prepare('INSERT INTO rate_limits (bucket_key,attempts,window_started_at,expires_at) VALUES (:key,1,UTC_TIMESTAMP(),:expires) ON DUPLICATE KEY UPDATE attempts=IF(expires_at<=UTC_TIMESTAMP(),1,attempts+1),window_started_at=IF(expires_at<=UTC_TIMESTAMP(),UTC_TIMESTAMP(),window_started_at),expires_at=IF(expires_at<=UTC_TIMESTAMP(),VALUES(expires_at),expires_at)');$statement->execute(['key'=>$hash,'expires'=>$expires]);if(random_int(1,100)===1){$this->_pdo->exec('DELETE FROM rate_limits WHERE expires_at<UTC_TIMESTAMP()');}$read=$this->_pdo->prepare('SELECT attempts FROM rate_limits WHERE bucket_key=:key');$read->execute(['key'=>$hash]);return (int)$read->fetchColumn();
    }
}
