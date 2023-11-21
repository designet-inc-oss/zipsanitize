<?php
###############################################################################
# Initilized
###############################################################################
define("BASEDIR", dirname(dirname(__file__)));

###############################################################################
# Include
###############################################################################
include(BASEDIR. "/class/Init.php");
include(LIBDIR. "Config.php");
include(LIBDIR. "Database.php");
include(LIBDIR. "/vendor/div.php");

define("ENC_TMPL", TMPLDIR. "/enc_web.tmpl");
define("UNENC_TMPL", TMPLDIR. "/unenc_web.tmpl");
define("PROG", "zipsanitize_web");
###############################################################################
# Class
###############################################################################
class sanitizeWeb {
    private $tags = array();
    private $type = "";
    private $hash = "";
    private $pwhash = "";
    private $status = "";
    private $original = "";
    private $filename = "";

    public function __construct()
    {
        $this->tags["msg"] = "";
        $this->tags["errmsg"] = "";
        $this->tags["zipfile"] = "";
        $this->tags["hash"] = "";

        # Read config
        $this->c = new Config(CONFIG);
        if ($this->c->result === false) {
            $this->tags["errmsg"] = "問題が発生しました。管理者にお問い合わせください。";
            $this->_view();
            throw new Exception('system error');
        }

        # Start log.
        if (openlog(PROG, LOG_PID,
                constant($this->c->config["common"]["SyslogFacility"]))
                                                                   === FALSE) {
            $this->tags["errmsg"] = "問題が発生しました。管理者にお問い合わせください。";
            syslog(LOG_ERR, "Failed openlog.");
            $this->_view();
            throw new Exception('system error');
        }

        # Start DB.
        $db = new DB($this->c->config["database"]);
        if ($db->result === false)  {
            $this->tags["errmsg"] = "問題が発生しました。管理者にお問い合わせください。";
            syslog(LOG_ERR,
                   "Cannot connect database. Please see the log for details.");
            $this->_view();
            throw new Exception('system error');
        }
        $this->db = $db;

        if (isset($_POST["submit"])) {
            $this->submit();
        } else {
            if ($this->init() === TRUE) {
                $this->tags["msg"] = "ZIPファイルの検査を行います。";
            }
        }
        $this->_view();
    }

    private function init()
    {
        # check hash
        if ($this->check_hash() === FALSE) {
            return FALSE;
        }

        # select DB
        $data = $this->get_zipinfo();
        if ($data === FALSE) {
            return FALSE;
        }

        # set tag
        $this->tags["zipfile"] = $data[0]["filename"];
        $this->tags["hash"] = $this->hash;
        $this->type = $data[0]["type"];

        return TRUE;
    }

    private function submit()
    {
        # get hash
        if(!isset($_POST["hash"]) || $_POST["hash"] == "") {
            $this->tags["errmsg"] = "不正なアクセスです。";
            syslog(LOG_ERR, "Failed to get hash.");
            return FALSE;
        }
        $this->hash = $_POST["hash"];

        # get email
        if (!isset($_POST['email'])) {
            $this->tags["errmsg"] = "不正なアクセスです。";
            syslog(LOG_ERR, "Failed to get email:". $this->hash);
            return FALSE;
        }
        $email = $_POST['email'];

        # select DB
        $data = $this->get_zipinfo();
        if ($data === FALSE) {
            return FALSE;
        }

        $this->type = $data[0]["type"];
        $this->pwhash = $data[0]["pwhash"];
        $this->status = $data[0]["status"];
        $this->original = $data[0]["original"];
        $this->filename = $data[0]["filename"];

        # get password
        $password = "";
        if ($this->type == "1") {
            if (!isset($_POST['password'])) {
                $this->tags["errmsg"] = "不正なアクセスです。";
                syslog(LOG_ERR, "Failed to get password:". $this->hash);
                return FALSE;
            }
            $password = $_POST['password'];
        } else {
            if (isset($_POST['password'])) {
                $this->tags["errmsg"] = "不正なアクセスです。";
                syslog(LOG_ERR, "Got a password even though it is not encrypted zip:". $this->hash);
                return FALSE;
            }
        }

        # validation check
        if ($this->check_validation($email, $password) === FALSE) {
            return FALSE;
        }

        # unzip zipfile
        if ($this->status == "0") {
            if ($this->unzipfile($data, $password) === FALSE) {
                return FALSE;
            }
        } else {
            $pwhash = hash('sha256', $password);
            if ($this->pwhash != $pwhash) {
                $this->tags["errmsg"] = "ZIPファイルのパスワードが異なります。";
                $this->tags["zipfile"] = $this->filename;
                $this->tags["hash"] = $this->hash;
                syslog(LOG_ERR, sprintf("Different hashed password:%s:%s", $password, $this->hash));
                return FALSE;
            }
        }

        # Set environment variable
        if ($this->set_env($password) === FALSE) {
            return FALSE;
        }

        # Execute command
        if ($this->exec_command($email) === FALSE) {
            return FALSE;
        }

        return TRUE;
    }

    private function check_hash()
    {
        if(!isset($_GET['h'])) {
            $this->tags["errmsg"] = "不正なアクセスです。";
            syslog(LOG_ERR, "Failed to get hash.");
            return FALSE;
        }

        $hash = $_GET['h'];
        if (strlen($hash) !== 64) {
            $this->tags["errmsg"] = "不正なアクセスです。";
            syslog(LOG_ERR, "hash is not 256 bit:". $hash);
            return FALSE;
        }

        if (!preg_match('/^[a-f0-9]+$/', $hash)) {
            $this->tags["errmsg"] = "不正なアクセスです。";
            syslog(LOG_ERR, "Invalid hash format:". $hash);
            return FALSE;
        }
        $this->hash = $hash;
    }

    private function get_zipinfo()
    {
        # select DB
        $data = $this->db->zipinfoByHash($this->hash);
        if ($this->db->result === false) {
            $this->tags["errmsg"] = "問題が発生しました。管理者にお問い合わせください。";
            syslog(LOG_ERR, "Cannot search data from zipinfo table:".
                                                                  $this->hash);
            return FALSE;
        }
        if (count($data) === 0) {
            $this->tags["errmsg"] = "問題が発生しました。管理者にお問い合わせください。";
            syslog(LOG_ERR, "No data in zipinfo table:". $this->hash);
            return FALSE;
        }

        return $data;
    }

    private function check_validation($email, $password)
    {
        # validation check
        if ($email == "") {
            $this->tags["hash"] = $this->hash;
            $this->tags["zipfile"] = $this->filename;
            $this->tags["errmsg"] = "メールアドレスが入力されていません。";
            syslog(LOG_ERR, "E-mail address is not registered:". $this->hash);
            return FALSE;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->tags["hash"] = $this->hash;
            $this->tags["zipfile"] = $this->filename;
            $this->tags["errmsg"] = "メールアドレスの形式ではありません。";
            syslog(LOG_ERR, sprintf("Invalid E-mail address.(%s):%s" , $email,
                                                                 $this->hash));
            return FALSE;
        }
        if (strlen($this->hash) !== 64) {
            $this->tags["errmsg"] = "不正なアクセスです。";
            syslog(LOG_ERR, "hash is not 256 bit:". $this->hash);
            return FALSE;
        }
        if (!preg_match('/^[a-f0-9]+$/', $this->hash)) {
            $this->tags["errmsg"] = "不正なアクセスです。";
            syslog(LOG_ERR, "Invalid hash format:". $this->hash);
            return FALSE;
        }
        if ($this->type == "1") {
            if ($password == "") {
                $this->tags["hash"] = $this->hash;
                $this->tags["zipfile"] = $this->filename;
                $this->tags["errmsg"] = "パスワードが入力されていません。";
                syslog(LOG_ERR, "password is not registered:". $this->hash);
                return FALSE;
            }
        }
    }

    private function unzipfile($data, $password)
    {
        # check TmpDir
        if (file_exists($this->c->config["common"]["TmpDir"]) === FALSE) {
            $this->tags["errmsg"] = "問題が発生しました。管理者にお問い合わせください。";
            syslog(LOG_ERR, sprintf('Cannot find directory:%s:%s',
                           $this->c->config["common"]["TmpDir"], $this->hash));
            return FALSE;
        }
        if (is_writable($this->c->config["common"]["TmpDir"]) === FALSE) {
            $this->tags["errmsg"] = "問題が発生しました。管理者にお問い合わせください。";
            syslog(LOG_ERR, sprintf('Cannot make directory. Permission denied:%s:%s',
                           $this->c->config["common"]["TmpDir"], $this->hash));
            return FALSE;
        }
        
        # make unzip_dir
        $unzip_dir = $this->c->config["common"]["TmpDir"] . rand();
        if (mkdir($unzip_dir) === FALSE) {
            $this->tags["errmsg"] = "問題が発生しました。管理者にお問い合わせください。";
            syslog(LOG_ERR, sprintf('Failed to make directory:%s:%s',
                                                     $unzip_dir, $this->hash));
            return FALSE;
        }

        # check original
        if (file_exists($this->original) === FALSE) {
            $this->tags["errmsg"] = "問題が発生しました。管理者にお問い合わせください。";
            syslog(LOG_ERR, sprintf('Cannot find file:%s:%s',
                                                          $this->original, $this->hash));
            return FALSE;
        }

        if ($this->type == "1") {
            exec("unzip -P " . $password . " " . $this->original.
                                           " -d " . $unzip_dir, $output, $ret);
        } else {
            exec("unzip " . $this->original . " -d " . $unzip_dir,
                                                                $output, $ret);
        }
        if ($ret === 82) {
            $logmsg = implode(":", $output);
            $this->tags["errmsg"] = "ZIPファイルのパスワードが異なります。";
            $this->tags["hash"] = $this->hash;
            $this->tags["zipfile"] = $this->filename;
            syslog(LOG_ERR, sprintf('Different password:%s:%s:%s',
                                             $password, $logmsg, $this->hash));
            return FALSE;
        } else if ($ret !== 0) {
            $logmsg = implode(":", $output);
            $this->tags["errmsg"] = "問題が発生しました。管理者にお問い合わせください。";
            syslog(LOG_ERR, sprintf('Cannot extract zip archive:%s:%s:%s',
                                       $this->original, $logmsg, $this->hash));
            return FALSE;
        }

        # remove directory
        exec("rm -rf " . $unzip_dir, $output, $ret);
        if ($ret !== 0) {
            $logmsg = implode(":", $output);
            $this->tags["errmsg"] = "問題が発生しました。管理者にお問い合わせください。";
            syslog(LOG_ERR, sprintf('Failed to rm command:%s:%s', $logmsg,
                                                                 $this->hash));
            return FALSE;
        }
    }

    private function set_env($password)
    {
        # Set environment variable
        if (putenv("SNZIPPASSWD=$password") === FALSE) {
            $this->tags["errmsg"] = "問題が発生しました。管理者にお問い合わせください。";
            syslog(LOG_ERR, 'Failed to putenv:'. $this->hash);
            return FALSE;
        }
    }

    private function exec_command($email)
    {
        # Check command
        if (file_exists(BINDIR. "zipsanitize") === FALSE) {
            $this->tags["errmsg"] = "問題が発生しました。管理者にお問い合わせください。";
            syslog(LOG_ERR, sprintf('Cannot find command:%s:%s',
                                          BINDIR. "zipsanitize", $this->hash));
            return FALSE;
        }

        if (is_executable(BINDIR. "zipsanitize") === FALSE) {
            $this->tags["errmsg"] = "問題が発生しました。管理者にお問い合わせください。";
            syslog(LOG_ERR, sprintf('Cannot execute command. Permission denied:%s:%s',
                                          BINDIR. "zipsanitize", $this->hash));
            return FALSE;
        }

        $esc_hash = escapeshellarg($this->hash);
        $esc_email = escapeshellarg($email);
        # Execute command
        $command = "nohup ". BINDIR. "zipsanitize " . $esc_hash . " "
                                                              . $esc_email . " &";
        exec($command, $output, $ret);
        if ($ret !== 0) {
            $this->tags["errmsg"] = "問題が発生しました。管理者にお問い合わせください。";
            $logmsg = implode(":", $output);
            syslog(LOG_ERR, sprintf('Command execution failed:%s:%s:%s',
                                             $command, $logmsg, $this->hash));
            return FALSE;
        }

        $this->tags["msg"] = "ZIPファイルの検査を開始しました。検査完了後、メールでファイルを送信します。本ページを閉じてお待ちください。";
        $this->tags["hash"] = $this->hash;
        $this->tags["zipfile"] = $this->filename;
    }

    private function _view()
    {
        if ($this->type == "1") {
            $tmpl = ENC_TMPL;
        } else {
            $tmpl = UNENC_TMPL;
        }

        # Check tmpl
        if (file_exists($tmpl) === FALSE) {
            $this->tags["msg"] = "問題が発生しました。管理者にお問い合わせください。";
            syslog(LOG_ERR, sprintf('Cannot read template file. No such file:%s:%s',
                                                          $tmpl, $this->hash));
            return FALSE;
        }

        # Check readable
        if (is_readable($tmpl) === FALSE) {
            $this->tags["msg"] = "問題が発生しました。管理者にお問い合わせください。";
            syslog(LOG_ERR, sprintf('Cannot read template file. Permission denied:%s:%s',
                                                          $tmpl, $this->hash));
            return FALSE;
        }

        $view = new div($tmpl, $this->tags);
        if ($view == $tmpl) {
            syslog(LOG_ERR, sprintf('Cannot read template file:%s:%s',
                                                          $tmpl, $this->hash));
            return FALSE;
        }
        print $view;
    }
}

try {
    new sanitizeWeb();
} catch (Exception $e) {
}
