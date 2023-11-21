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

Class Rule {
    public $rule = [];
    public $defrule = [];
    public $result = true;
    private $nocheck;

    public function __construct($path)
    {
        $ret = $this->read($path);
        if ($ret === false) {
            $this->result = false;
            return;
        }
    }

    private function read($config)
    {
        try {
            $rule = [];
            $astrule = ['cmd' => 'none', 'convert'=>'-'];
            $savetypes = [];
            $typeresult = true;

            $file = new SplFileObject($config);
            foreach ($file as $line_num => $line) {
                $num = $line_num + 1;

                # Triming space 
                $line = trim($line);

                # Skip empty line
                if ($line == "") {
                    continue;
                }

                # Skip comment line
                if (substr($line, 0, 1) == "#") {
                    continue;
                }

                # Split line
                $line_array = preg_split("/\s+/", $line, 4);

                # Check too few column
                if (count($line_array) < 3) {
                    syslog(LOG_ERR, 'Invalid format rule. Too few column.'.
                                                       $config. ":". $num);
                    $this->result = false;
                    continue;

                }

                # Check command 
                if (file_exists($line_array[2]) === false &&
                    $line_array[2] !== "none") {
                    syslog(LOG_ERR, 'No such command:'. $line_array[2]. ":".
                                                       $config. ":". $num);
                    $this->result = false;
                    continue;
                }

                if (isset($line_array[3])) {
                    $line_array[2] .= " ". $line_array[3]; 
                }

                # Check file type string
                $ret = filter_var($line_array[0], FILTER_VALIDATE_REGEXP, 
                                  ["options"=>['regexp'=>'/^[\x20-\x7E]+$/']]);
                if ($ret === false) {
                    syslog(LOG_ERR, 'Invalid file type:'. $line_array[0]. ":".
                                                       $config. ":". $num);
                    $this->result = false;
                    continue;
                }

                # Check convert file type string
                $ret = filter_var($line_array[1], FILTER_VALIDATE_REGEXP,
                                  ["options"=>['regexp'=>'/^[\x20-\x7E]+$/']]);
                if ($ret === false) {
                    syslog(LOG_ERR, 'Invalid convert file type:'. 
                                      $line_array[1]. ":". $config. ":". $num);
                    $this->result = false;
                    continue;
                }

                # Create file type list
                $types = explode(",", $line_array[0]);
                foreach ($types as $type) {
                    $type = strtolower($type);
                    if (isset($savetypes[$type])) {
                        syslog(LOG_ERR, 'File type is registered twice:'.
                                               $type. ":". $config. ":". $num);
                        $typeresult = false;
                        continue;
                    }

                    # Varialbles for duplicate type check
                    $savetypes[$type] = true;

                    # asterisk
                    if ($type === '*') {
                        $astrule = ["cmd"=>$line_array[2],
                                    "convert"=>$line_array[1]];  
                        continue;
                    }

                    $rule[] = ["$type" => ["cmd"=>$line_array[2],
                                          "convert"=>$line_array[1]]]; 
                }

                if ($typeresult === false) {
                    $this->result = false;
                    continue;
                }
            }

            if ($this->result === true) {
                $this->rule = $rule;
                $this->defrule = $astrule;
            }

        } catch (Exception $e) {
            $this->result = false;
            $err = $e->getMessage();
            syslog(LOG_ERR, 'Cannot read rule file: '. $err);
        }

    }

}
