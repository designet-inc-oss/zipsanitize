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

Class  Config {
    public $config = [];
    public $result = true;
    private $nocheck;

    public function __construct($path)
    {
        global $prog;
        openlog($prog, LOG_PID, LOG_LOCAL0);

        $ret = $this->read($path);
        if ($ret === false) {
            $this->result = false;
            return;
        }

        $this->commonvalidate();
        $this->dbvalidate();
        $this->connectorvalidate();
        $this->zipsanitizevalidate();
        $this->zipdeletevalidate();

        closelog();
    }

    private function read($config)
    {
        $nocheck = @parse_ini_file($config, true);
        if ($nocheck === false) {
            $lasterr = error_get_last();
            syslog(LOG_ERR, 'Cannot read configuration file: '. 
                                                         $lasterr["message"]);
            return false;
        }
        $this->nocheck = $nocheck;

    }

    private function checker($section, $filters)
    {

        $checked = filter_var_array($this->nocheck[$section], $filters, true);
        
        # Check setting items
        foreach ($checked as $key => $item) {
            if ($item === null) {
                syslog(LOG_ERR, 'Setting item does not exist: '. $key);
                $this->result = false;
            }
            if ($item === false) {
                syslog(LOG_ERR, 'Invalid format item: '. $key);
                $this->result = false;
            }
        }

        foreach ($this->nocheck[$section] as $key => $value) {
            if (!isset($checked[$key])) {
                syslog(LOG_ERR, 'Ivalid setting item:'. $key);
                $this->result = false;
            }
        }

        return $checked;

    }

    private function commonvalidate()
    {
        $section = "common";

        $facility = "LOG_LOCAL0|LOG_LOCAL1|LOG_LOCAL2|LOG_LOCAL3|LOG_LOCAL4|".
                    "LOG_LOCAL5|LOG_LOCAL6|LOG_LOCAL7|LOG_AUTH|LOG_AUTHPRIV|".
                    "LOG_CRON|LOG_DAEMON|LOG_MAIL|LOG_USER";

        $filters = [
            'ZipSaveDir' => ['filter'  =>FILTER_VALIDATE_REGEXP,
                             'options'=>['regexp' => '/^\/.*/']],
            'TmpDir'     => ['filter'  =>FILTER_VALIDATE_REGEXP,
                             'options'=>['regexp' => '/^\/.*/']],
            'SanitizeZIPSaveDir' =>
                            ['filter'  => FILTER_VALIDATE_REGEXP,
                             'options'=>['regexp' => '/^\/.*/']],
            'WebUser' =>
                            ['filter'  => FILTER_VALIDATE_REGEXP,
                             'options'=>['regexp' => '/.*/']],
            'SyslogFacility'=>
                            ['filter'  => FILTER_VALIDATE_REGEXP,
                             'options'=>['regexp' => "/^(". $facility. ")$/"]],
                   ];

        $checked = $this->checker($section, $filters);

        $this->config[$section] = $checked;
    }

    private function dbvalidate()
    {
        $section = "database";

        $filters = [
            'DBDriver' => ['filter'  =>FILTER_VALIDATE_REGEXP,
                             'options'=>['regexp' => '/^sqlite$/']],
            'DB'       => ['filter'  =>FILTER_VALIDATE_REGEXP,
                             'options'=>['regexp' => '/^\/.*/']],
                   ];

        $checked = $this->checker($section, $filters);

        $this->config[$section] = $checked;
    }

    private function connectorvalidate()
    {
        $section = "connector";

        $filters = [
                  'URLPrefix'  => ['filter' =>FILTER_VALIDATE_URL,
                                   'options'=>['regexp' => '/^\/.*/']],
                   ];

        $checked = $this->checker($section, $filters);

        $this->config[$section] = $checked;
    }

    private function zipsanitizevalidate()
    {
        $section = "zipsanitize";

        $filters = [
                  'SanitizeFailedAction' =>
                                ['filter'  => FILTER_VALIDATE_REGEXP,
                                 'options'=>['regexp' => '/^(pass|encrypt)$/']],
                  'MailSubject' =>
                                ['filter'  => FILTER_VALIDATE_REGEXP,
                                 'options'=>['regexp' => '/.*/']],
                  'WarnMailSubject' =>
                                ['filter'  => FILTER_VALIDATE_REGEXP,
                                 'options'=>['regexp' => '/.*/']],
                  'ErrMailSubject' =>
                                ['filter'  => FILTER_VALIDATE_REGEXP,
                                 'options'=>['regexp' => '/.*/']],
                  'MailFrom'=>FILTER_VALIDATE_EMAIL,
                  'ZipFileMax' => ['filter'=> FILTER_VALIDATE_INT,
                                 'options'=>['min_range' => '1']]
                   ];

        $checked = $this->checker($section, $filters);

        $this->config[$section] = $checked;
    }


    private function zipdeletevalidate()
    {
        $section = "zipdelete";

        $filters = [
                  'SaveLimit'=>FILTER_VALIDATE_INT,
                   ];

        $checked = $this->checker($section, $filters);

        $this->config[$section] = $checked;
    }


    public function __destruct()
    {
        closelog();
    }
}
