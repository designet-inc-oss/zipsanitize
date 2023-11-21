<?php
/*
 * SaMMA zipsanitize plugin
 *
 * Copyright (C) 2017 DesigNET, INC.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

class DB {
    private $pdo;
    public $result = true;

    public function __construct($config)
    {
        try {
            $type = $config["DBDriver"];
            $db = $config["DB"];

            # connect database
            $pdo = new PDO($type. ":". $db);

            # Modify errormode: error -> exception
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            # Modify Fetch Mode
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            $this->pdo = $pdo;
        } catch (PDOException $e) {
            $this->result = false;
            $err = $e->getMessage();
            syslog(LOG_ERR, 'Cannot connect DB: '. $err);
        }

    }

    public function mkTable()
    {
        $pdo = $this->pdo;

        try {
            # Define sqls;
            $zipinfock = "select count(*) as num from sqlite_master ". 
                                         "where type='table' and name='zipinfo'";

            $queueck = "select count(*) as num from sqlite_master ". 
                                           "where type='table' and name='queue'";

            $zipinfomk = "create table zipinfo (".
                         "hash text primary key,".
                         "create_date text,".
                         "filename text,".
                         "original text,".
                         "sanitized text,".
                         "status int,".
                         "stmodify_date text,".
                         "sanitize_date text,".
                         "sanitize_result text,".
                         "sanitize_errors text,".
                         "pwhash text,".
                         "type int".
                         ")";

            $queuemk = "create table queue (".
                       "hash text,".
                       "maddr text".
                       ")";

            # check zipinfo table
            $stmt = $pdo->prepare($zipinfock);
            $stmt->execute();
            $data = $stmt->fetchAll();
 
            # make zipinfo table
            if ($data[0]["num"] === "0") {
                $pdo->exec($zipinfomk);
            }

            # check queue table
            $stmt = $pdo->prepare($queueck);
            $stmt->execute();
            $data = $stmt->fetchAll();

            # make queu table
            if ($data[0]["num"] === "0") {
                $pdo->exec($queuemk);
            }

        } catch (PDOException $e) {
            $this->result = false;
            $err = $e->getMessage();
            echo('Cannot create table: '. $err);
        }
    }

    public function addHash($data)
    {
        try {
            $pdo = $this->pdo;
            $sql = "insert into zipinfo('hash', 'create_date', 'filename', ".
                  "'original', 'sanitized', 'status', 'sanitize_date', 'type')".
                   "values".
                   "(?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            syslog(LOG_DEBUG, 'Insert data to zipinfo table: '. $data[0]);
        } catch (PDOException $e) {
            $this->result = false;
            $err = $e->getMessage();
            syslog(LOG_ERR, 'Cannot insert data to zipinfo table: '. $err);
        }
    }

    public function deleteHash($hash)
    {
        try {
            $pdo = $this->pdo;
            $sql = "delete from zipinfo where hash=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$hash]);
            syslog(LOG_DEBUG, 'Delete data from zipinfo table: '. $hash);
        } catch (PDOException $e) {
            $this->result = false;
            $err = $e->getMessage();
            syslog(LOG_ERR, 'Cannot delete data to zipinfo table: '. $hash. 
                                                                     ':'.$err);
        }
    }

    public function updateStatus($hash, $status)
    {
        try {
            $time = time();
            $pdo = $this->pdo;
            $sql = "update zipinfo set status=?, stmodify_date=? where hash=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status, $time, $hash]);
            syslog(LOG_DEBUG, 'Update data zipinfo status: '.
                                                         $hash. ':'. $status);
        } catch (PDOException $e) {
            $this->result = false;
            $err = $e->getMessage();
            syslog(LOG_ERR, 'Cannot update data to zipinfo status: '. $hash.
                                                                     ':'.$err);
        }
    }

   public function updatePwhash($hash, $pwhash)
    {
        try {
            $pdo = $this->pdo;
            $sql = "update zipinfo set pwhash=? where hash=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$pwhash, $hash]);
            syslog(LOG_DEBUG, 'Update data zipinfo pwhash: '.
                                                         $hash. ':'. $pwhash);
        } catch (PDOException $e) {
            $this->result = false;
            $err = $e->getMessage();
            syslog(LOG_ERR, 'Cannot update data to zipinfo pwhash: '. $hash.
                                                                     ':'.$err);
        }
    }

    public function updateSanitized($hash, $date, $path, $result, $errors, $pwhash)
    {
        try {
            $pdo = $this->pdo;
            $sql = "update zipinfo set sanitized=?, sanitize_date=?,".
                           " sanitize_result=?, sanitize_errors=?, pwhash=? where hash=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$path, $date, $result, $errors, $pwhash, $hash]);
            syslog(LOG_DEBUG, 'Update data zipinfo sanitize data: '.
                                                         $hash);
        } catch (PDOException $e) {
            $this->result = false;
            $err = $e->getMessage();
            syslog(LOG_ERR, 'Cannot update data to zipinfo sanitize data: '.
                                                           $hash.  ':'.$err);
        }
    }


    public function addQueue($data)
    {
        try {
            $pdo = $this->pdo;
            $sql = "insert into queue('hash', 'maddr') values ".
                   "(?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            syslog(LOG_DEBUG, 'Insert data to queue table: '. $data[0]);
        } catch (PDOException $e) {
            $this->result = false;
            $err = $e->getMessage();
            syslog(LOG_ERR, 'Cannot insert data to queue table: '. $err);
        }
    }

    public function deleteQueue($data)
    {
        try {
            $pdo = $this->pdo;
            $sql = "delete from queue where hash=? and maddr=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            syslog(LOG_DEBUG, 'Delete data from queue table: '. 
                                                    $data[0]. " and ". $data[1] );
        } catch (PDOException $e) {
            $this->result = false;
            $err = $e->getMessage();
            syslog(LOG_ERR, 'Cannot delete data to queue table: '. $err);
        }

    }
    public function deleteQueueAll($hash)
    {
        try {
            $pdo = $this->pdo;
            $sql = "delete from queue where hash=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$hash]);
            syslog(LOG_DEBUG, 'Delete data from queue table: '. 
                                                    $hash);
        } catch (PDOException $e) {
            $this->result = false;
            $err = $e->getMessage();
            syslog(LOG_ERR, 'Cannot delete data to queue table: '. $err);
        }

    }



    public function zipinfoByHash($hash)
    {
        try {
            $pdo = $this->pdo;
            $sql = "select * from zipinfo where hash=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$hash]);
            $data = $stmt->fetchAll();
            return $data;
        } catch (PDOException $e) {
            $this->result = false;
            $err = $e->getMessage();
            syslog(LOG_ERR, 'Cannot search data from zipinfo table: '. $err);
        }

    }

    public function queueByHash($hash)
    {
        try {
            $pdo = $this->pdo;
            $sql = "select * from queue where hash=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$hash]);
            $data = $stmt->fetchAll();
            return $data;
        } catch (PDOException $e) {
            $this->result = false;
            $err = $e->getMessage();
            syslog(LOG_ERR, 'Cannot search data from queue table: '. $err);
        }
    }

    public function queueByHashMaddr($data)
    {
        try {
            $pdo = $this->pdo;
            $sql = "select * from queue where hash=? and maddr=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            $data = $stmt->fetchAll();
            return $data;
        } catch (PDOException $e) {
            $this->result = false;
            $err = $e->getMessage();
            syslog(LOG_ERR, 'Cannot search data from queue table: '. $err);
        }
    }

    public function allZipinfo($cols = "")
    {
        if ($cols == "") {
            $col = "*";
        } else {
            $col = $cols;
        }

        $pdo = $this->pdo;
        $sql = "select ". $col. " from zipinfo";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetchAll();
        return $data;
    }

    public function allQueue()
    {
        try {
            $pdo = $this->pdo;
            $sql = "select * from queue";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll();
            return $data;
        } catch (PDOException $e) {
            $this->result = false;
            $err = $e->getMessage();
            echo('Cannot search data from zipinfo table: '. $err);
        }
    }

}
