<?php
/*
* Copyright (C) 2005-2015 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* utilsMail.php - main email sending function
*
*  TODO: Rationalise: THIS FILE IS DUPLICATED IN /php/common
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage
*/


function sendEmail($email_to, $email_title, $email_text, $email_header, $is_utf8=false){

    $res = null;

    if(!$email_to){
        $res = "Mail send failed. Recipient email address is not defined.";
    }else if(!$email_text){
        $res = "Mail send failed. Message text is not defined.";
    }

    if(!$res){

        if(!$email_title){
            $email_title = "";
        }
        $email_title = "[HEURIST] ".$email_title;

        if(!$email_header){
            $email_header = "From: HEURIST";
            if(defined('HEURIST_SERVER_NAME')){
                $email_header = $email_header." (".HEURIST_DBNAME.") <no-reply@".HEURIST_SERVER_NAME.">";
            }
        }


        if($is_utf8){
            $email_header = $email_header."\r\nContent-Type: text/plain; charset=utf-8\r\n";
            $email_title = '=?utf-8?B?'.base64_encode($email_title).'?=';
        }

        $email_text = $email_text."\n\n"
        ."-------------------------------------------\n"
        ."This email was generated by Heurist"
        .(defined('HEURIST_BASE_URL') ?(":\n".HEURIST_BASE_URL) :"")."\n";


        $rv = mail($email_to, $email_title, $email_text, $email_header);
        if(!$rv){
            $res = "Cannot send email. This may indicate that mail transport agent is not correctly configured on server. Please advise the system adminstrator";
        }
    }

    if($res){ //error
    }else{
        $res = "ok";
    }

    return "ok";
}

function checkSmtp(){

    $smtpHost = 'localhost';
    $smtpPort = '25';
    $smtpTimeout = 5;

    $res = @fsockopen($smtpHost,
                  $smtpPort,
                  $errno,
                  $errstr,
                  $smtpTimeout);

  if (!is_resource($res))
  {
    error_log("email_smtp_error {$errno} {$errstr}");
    return false;
  }
  return true;
}
?>
