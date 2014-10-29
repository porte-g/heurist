<?php

    /**
    *
    * createNewDB.php: Create a new database by applying blankDBStructure.sql and coreDefinitions.txt
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @author      Ian Johnson     <ian.johnson@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     3.2
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

    /**
    * Extensively modified 4/8/11 by Ian Johnson to reduce complexity and load new database in
    * a series of files with checks on each stage and cleanup code. New database creation functions
    * Oct 2014 by Artem Osmakov to replace command line execution to allow operation on dual tier systems
    * **/

    define('NO_DB_ALLOWED',1);
    require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../../common/php/dbUtils.php');
    require_once(dirname(__FILE__).'/../../../common/php/dbScript.php');

    // must be logged in if a dbname is passed to it
    if (!is_logged_in() && HEURIST_DBNAME!="") {
        header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME.'&last_uri='.urlencode(HEURIST_CURRENT_URL) );
        return;
    }

    // must be logged in anyway to define the master user for the database
    if (!is_logged_in()) {
        header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME.'&last_uri='.urlencode(HEURIST_CURRENT_URL) );
        return;
    }

    // clean up string for use in SQL query
    function prepareDbName(){
        $db_name = substr(get_user_username(),0,5);
        $db_name = preg_replace("/[^A-Za-z0-9_\$]/", "", $db_name);
        return $db_name;
    }

?>
<html>
    <head>
        <title>Create New Heurist Database</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <link rel="stylesheet" type="text/css" href="../../../common/css/global.css">
        <link rel="stylesheet" type="text/css" href="../../../common/css/admin.css">
        <link rel="stylesheet" type="text/css" href="../../../common/css/edit.css">


        <style>
            .detailType {width:180px !important}
        </style>

        <script>

            function hideProgress(){
                var ele = document.getElementById("loading");
                if(ele){
                    ele.style.display = "none";
                }
            }


            function showProgress(force){
                var ele = document.getElementById("loading");
                if(force) ele.style.display = "block";
                if(ele.style.display != "none"){
                    ele = document.getElementById("divProgress");
                    if(ele){
                        ele.innerHTML = ele.innerHTML + ".";
                        setTimeout(showProgress, 500);
                    }
                }
            }


            // does a simple word challenge to allow admin to globally restrict new database creation
            function challengeForDB(){
                var pwd_value = document.getElementById("pwd").value;
                if(pwd_value==="<?=$passwordForDatabaseCreation?>"){
                    document.getElementById("challengeForDB").style.display = "none";
                    document.getElementById("createDBForm").style.display = "block";
                }else{
                    alert("Password incorrect, please check with system administrator. Note: password is case sensitive");
                }
            }


            function onKeyPress(event){

                event = event || window.event;
                var charCode = typeof event.which == "number" ? event.which : event.keyCode;
                if (charCode && charCode > 31)
                {
                    var keyChar = String.fromCharCode(charCode);
                    if(!/^[a-zA-Z0-9$_]+$/.test(keyChar)){
                        event.cancelBubble = true;
                        event.returnValue = false;
                        event.preventDefault();
                        if (event.stopPropagation) event.stopPropagation();
                        return false;
                    }
                }
                return true;
            }


            function setUserPartOfName(){
                var ele = document.getElementById("uname");
                if(ele.value==""){
                    ele.value = document.getElementById("ugr_Name").value.substr(0,5);
                }
            }


            function onBeforeSubmit(){
                <?php if(!is_logged_in()) { ?>
                    function __checkMandatory(field, label) {
                        if(document.getElementById(field).value==="") {
                            alert(label+" is mandatory field");
                            document.getElementById(field).focus();
                            return true;
                        }else{
                            return false;
                        }
                    }


                    // check mandatory fields
                    if(
                        __checkMandatory("ugr_FirstName","First name") ||
                        __checkMandatory("ugr_LastName","Last name") ||
                        __checkMandatory("ugr_eMail","Email") ||
                        //__checkMandatory("ugr_Organisation","Institution/company") ||
                        //__checkMandatory("ugr_Interests","Research Interests") ||
                        __checkMandatory("ugr_Name","Login") ||
                        __checkMandatory("ugr_Password","Password")
                    ){
                        return false;
                    }


                    if(document.getElementById("ugr_Password").value!==document.getElementById("ugr_Password2").value){
                        alert("Passwords are different");
                        document.getElementById("ugr_Password2").focus();
                        return false;
                    }
                    <?php } ?>

                var ele = document.getElementById("createDBForm");
                if(ele) ele.style.display = "none";

                showProgress(true);
                return true;
            }

        </script>
    </head>

    <body class="popup">

        <?=(@$_REQUEST['popup']=="1"?"":"<div class='banner'><h2>Create New Database</h2></div>") ?>

        <div id="page-inner" style="overflow:auto">
            <div id="loading" style="display:none">
                <img src="../../../common/images/mini-loading.gif" width="16" height="16" />
                <strong><span id="divProgress">&nbsp;Creating database, please wait</span></strong>
            </div>

        <?php
            $newDBName = "";
            // Used by buildCrosswalks to detemine whether to get data from coreDefinitions.txt (for new database)
            // or by querying an existing Heurist database using getDBStructureAsSQL (for crosswalk)
            $isNewDB = false;

            global $errorCreatingTables; // Set to true by buildCrosswalks if error occurred
            global $done; // Prevents the makeDatabase() script from running twice
            $done = false; // redundant
            $isCreateNew = true;

            if(isset($_POST['dbname'])) {
                $isCreateNew = false;
                $isHuNI = ($_POST['dbtype']=='1');
                $isFAIMS = ($_POST['dbtype']=='2');

                /* TODO: verify that database name is unique
                $list = mysql__getdatabases();
                $dbname = $_POST['uname']."_".$_POST['dbname'];
                if(array_key_exists($dbname, $list)){
                echo "<h3>Database '".$dbname."' already exists. Choose different name</h3>";
                }else{
                */

                //ob_flush();
                //flush();

                echo_flush( '<script type="text/javascript">showProgress(true);</script>' );

                makeDatabase(); // this does all the work <<<*************************************************

                echo_flush( '<script type="text/javascript">hideProgress();</script>' );
           }

            if($isCreateNew){
        ?>

            <div id="challengeForDB" style="<?='display:'.(($passwordForDatabaseCreation=='')?'none':'block')?>;">
                <h3>Enter the password set by your system administrator for new database creation:</h3>
                <input type="password" maxlength="64" size="25" id="pwd">
                <input type="button" onclick="challengeForDB()" value="OK" style="font-weight: bold;" >
            </div>


            <div id="createDBForm" style="<?='display:'.($passwordForDatabaseCreation==''?'block':'none')?>;padding-top:20px;">
                <form action="createNewDB.php?db=<?= HEURIST_DBNAME ?>&popup=<?=@$_REQUEST['popup']?>"
                   method="POST" name="NewDBName" onsubmit="return onBeforeSubmit()">


                    <?php if(!is_logged_in()) { ?>
                        <!-- what on earth are we doing setting up a new user at this point. This is redundant code.
                             The least they can do is login to the sandpit. Now checking at the start.
                        <div id="detailTypeValues" style="border-bottom: 1px solid #7f9db9;padding-bottom:10px;">
                            <div style="padding-left:160px">
                                Define master user for new database
                            </div>
                            <div class="input-row required">
                                <div class="input-header-cell" for="ugr_FirstName">Given name</div>
                                <div class="input-cell"><input id="ugr_FirstName" name="ugr_FirstName" style="width:50;" maxlength="40" /></div>
                            </div>

                            <div class="input-row required">
                                <div class="input-header-cell" for="ugr_LastName">Family name</div>
                                <div class="input-cell"><input id="ugr_LastName" name="ugr_LastName" style="width:50;" maxlength="63" /></div>
                            </div>
                            <div class="input-row required">
                                <div class="input-header-cell" for="ugr_eMail">Email</div>
                                <div class="input-cell"><input id="ugr_eMail" name="ugr_eMail" style="width:200;" maxlength="100"
                                    onKeyUp="document.getElementById('ugr_Name').value=this.value;"/></div>
                            </div>
                            <div class="input-row required">
                                <div class="input-header-cell" for="ugr_Name">Login name</div>
                                <div class="input-cell"><input id="ugr_Name" name="ugr_Name" style="width:200;" maxlength="63"
                                    onkeypress="{onKeyPress(event)}" onblur="{setUserPartOfName()}"/></div>
                            </div>
                            <div class="input-row required">
                                <div class="input-header-cell" for="ugr_Password">Password</div>
                                <div class="input-cell"><input id="ugr_Password" name="ugr_Password" type="password" style="width:50;"
                                    maxlength="40" />
                                    <div class="help prompt help1" >
                                        <div style="color:red;">Warning: http traffic is not encrypted.
                                            please don't use an important password such as institutional login</div>
                                    </div>
                                </div>
                            </div>
                            <div class="input-row required">
                                <div class="input-header-cell" for="ugr_Password2">Repeat password</div>
                                <div class="input-cell"><input id="ugr_Password2" type="password" style="width:50;" maxlength="40" />
                                </div>
                            </div>
                        </div>
                        -->
                        <?php } ?>


                    <div style="border-bottom: 1px solid #7f9db9;padding-bottom:10px; padding-top: 10px;">
                        <input type="radio" name="dbtype" value="0" id="rb1" checked="true" /><label for="rb1"
                            class="labelBold">Standard database</label>
                        <div style="padding-left: 38px;padding-bottom:10px">
                            Gives an uncluttered database with essential record and field types. Recommended for general use
                            </div>
                        <input type="radio" name="dbtype" value="1" id="rb2" /><label for="rb2" class="labelBold">HuNI Core schema</label>
                        <div style="padding-left: 38px;">The <a href="http://huni.net.au" target=_blank>
                            Humanities Networked Infrastructure (HuNI)</a>
                            core entities and field definitions, facilitating harvesting into the HuNI aggregate
                            </div>
                        <input type="radio" name="dbtype" value="2" id="rb3" disabled="true"/><label for="rb3" class="labelBold">
                        FAIMS Core schema (not yet available)</label>
                        <div style="padding-left: 38px;">The <a href="http://fedarch.org" target=_blank>
                            Federated Archaeological Information Management System (FAIMS)</a>
                            core entities and field definitions, providing a minimalist framework for archaeological fieldwork databases</div>

                        <p><ul>
                        <li>After the database is created, we suggest visiting Browse Templates and Import Structure menu entries to
                            download pre-configured templates or individual record types and fields.</li>
                        <li>New database creation may take up to 20 seconds. New databases are created on the current server.</li>
                            <li>You will become the owner and administrator of the new database.</li>
                        </ul><p>
                    </div>

                    <h3>Enter a name for the new database:</h3>
                    <div style="margin-left: 40px;">
                        <!-- user name used as prefix -->
                        <b><?= HEURIST_DB_PREFIX ?>
                            <input type="text" maxlength="20" size="6" name="uname" id="uname" onkeypress="{onKeyPress(event)}"
                                style="padding-left:3px; font-weight:bold;" value=<?=(is_logged_in()?prepareDbName():'')?> > _  </b>
                        <input type="text" maxlength="64" size="25" name="dbname"  onkeypress="{onKeyPress(event);}">
                        <input type="submit" name="submit" value="Create database" style="font-weight: bold;"  >
                        <p>The user name prefix is editable, and may be blank, but we suggest using a consistent prefix <br>
                        for personal databases so that all your personal databases appear together in the list of databases.<p></p>
                    </div>
                </form>
            </div> <!-- createDBForm -->
            <?php
            }


            function echo_flush($msg){
                 ob_start();
                 print $msg;
                 ob_flush();
                 flush();
            }

            function makeDatabase() { // Creates a new database and populates it with triggers, constraints and core definitions

                global $newDBName, $isNewDB, $done, $isCreateNew, $isHuNI, $isFAIMS, $errorCreatingTables;

                $error = false;
                $warning=false;

                if (isset($_POST['dbname'])) {

                    // Check that there is a current administrative user who can be made the owner of the new database
                    $message = "DB Admin username and password have not been set in configIni.php<br/> '+
                                'Please do so before trying to create a new database.<br>";
                    if(ADMIN_DBUSERNAME == "") {
                        if(ADMIN_DBUSERPSWD == "") {
                            echo $message;
                            return;
                        }
                        echo $message;
                        return;
                    }
                    if(ADMIN_DBUSERPSWD == "") {
                        echo $message;
                        return;
                    } // checking for current administrative user


                    // Create a new blank database
                    $newDBName = trim($_POST['uname']).'_';

                    if ($newDBName == '_') {$newDBName='';}; // don't double up underscore if no user prefix
                    $newDBName = $newDBName . trim($_POST['dbname']);
                    $newname = HEURIST_DB_PREFIX . $newDBName; // all databases have common prefix then user prefix


                    if(!db_create($newname)){
                        $isCreateNew = true;
                        return false;
                    }

                    echo_flush ("<p>Create Database Structure (tables)</p>");
                    if(db_script($newname, dirname(__FILE__)."/blankDBStructure.sql")){
                        echo_flush ('<p style="padding-left:20px">SUCCESS</p>');
                    }else{
                        db_drop($newname);
                        return false;
                    }

                    echo_flush ("<p>Addition Referential Constraints</p>");
                    if(db_script($newname, dirname(__FILE__)."/addReferentialConstraints.sql")){
                        echo_flush ('<p style="padding-left:20px">SUCCESS</p>');
                    }else{
                        db_drop($newname);
                        return false;
                    }

                    echo_flush ("<p>Addition Procedures and Triggers</p>");
                    if(db_script($newname, dirname(__FILE__)."/addProceduresTriggers.sql")){
                        echo_flush ('<p style="padding-left:20px">SUCCESS</p>');
                    }else{
                        db_drop($newname);
                        return false;
                    }

/*
                    //OLD COMMAND LINE APPROACH
                    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                        $cmdline = "mysql -h".HEURIST_DBSERVER_NAME." -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -e\"create database `$newname`\"";
                    } else {
                        $cmdline = "mysql -h".HEURIST_DBSERVER_NAME." -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -e'create database `$newname`'";
                    }

                    $output1 = exec($cmdline . ' 2>&1', $output, $res1);
                    if ($res1 != 0 ) {
                        echo ("<p class='error'>Error code $res1 on MySQL exec: Unable to create database $newname<br>&nbsp;<br>");
                        echo("\n\n");

                        if(is_array($output)){
                            $isExists = (strpos($output[0],"1007")>0);
                        }else{
                            $sqlErrorCode = split(" ", $output);
                            $isExists = (count($sqlErrorCode) > 1 &&  $sqlErrorCode[1] == "1007");
                        }
                        if($isExists){
                            echo "<strong>A database with that name already exists.</strong>";
                        }
                        echo "</p>";
                        $isCreateNew = true;
                        return false;
                    }

                    // At this point a database exists, so need cleanup if anythign goes wrong later

                    // Create the Heurist structure for the newly created database, using the template SQL file
                    // This file sets up the table definitions and inserts a few critical values
                    // it does not set referential integrity constraints or triggers
                    $cmdline="mysql -h".HEURIST_DBSERVER_NAME." -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -D$newname < blankDBStructure.sql";
                    $output2 = exec($cmdline . ' 2>&1', $output, $res2);

                    if ($res2 != 0 ) {
                        echo ("<p class='error'>Error $res2 on MySQL exec: Unable to load blankDBStructure.sql into database $newname<br>");
                        echo ("Please check whether this file is valid; consult Heurist support if needed<br>&nbsp;<br></p>");
                        echo($output2);
                        cleanupNewDB($newname);
                        return false;
                    }

                    // Add referential constraints
                    $cmdline="mysql -h".HEURIST_DBSERVER_NAME." -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -D$newname < addReferentialConstraints.sql";
                    $output2 = exec($cmdline . ' 2>&1', $output, $res2);

                    if ($res2 != 0 ) {
                        echo ("<p class='error'>Error $res2 on MySQL exec: Unable to load addReferentialConstraints.sql into database $newname<br>");
                        echo ("Please check whether this file is valid; consult Heurist support if needed<br>&nbsp;<br></p>");
                        echo($output2);
                        cleanupNewDB($newname);
                        return false;
                    }

                    // Add procedures and triggers
                    $cmdline = "mysql -h".HEURIST_DBSERVER_NAME." -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -D$newname < addProceduresTriggers.sql";
                    $output2 = exec($cmdline . ' 2>&1', $output, $res2);

                    if ($res2 != 0 ) {
                        echo ("<p class='error'>Error $res2 on MySQL exec: Unable to load addProceduresTriggers.sql for database $newname<br>");
                        echo ("Please check whether this file is valid; consult Heurist support if needed<br>&nbsp;<br></p>");
                        echo($output2);
                        cleanupNewDB($newname);
                        return false;
                    }
*/


                    // Run buildCrosswalks to import minimal definitions from coreDefinitions.txt into the new DB
                    // yes, this is badly structured, but it works - if it ain't broke ...
                    $isNewDB = true; // flag of context for buildCrosswalks, tells it to use coreDefinitions.txt

                    require_once('../../structure/import/buildCrosswalks.php');

                    // errorCreatingTables is set to true by buildCrosswalks if an error occurred
                    if($errorCreatingTables) {
                        echo ("<p class='error'>Error importing core definitions from ".
                            ($isHuNI?"coreDefinitionsHuNI.txt":(($isFAIMS)?"coreDefinitionsFAIMS.txt":"coreDefinitions.txt")).
                            " for database $newname<br>");
                        echo ("Please check whether this file is valid; consult Heurist support if needed</p>");
                        cleanupNewDB($newname);
                        return false;
                    }

                    // Get and clean information for the user creating the database
                    if(!is_logged_in()) {
                        $longName = "";
                        $firstName = $_REQUEST['ugr_FirstName'];
                        $lastName = $_REQUEST['ugr_LastName'];
                        $eMail = $_REQUEST['ugr_eMail'];
                        $name = $_REQUEST['ugr_Name'];
                        $password = $_REQUEST['ugr_Password'];
                        $department = '';
                        $organisation = '';
                        $city = '';
                        $state = '';
                        $postcode = '';
                        $interests = '';

                        $s = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789./';
                        $salt = $s[rand(0, strlen($s)-1)] . $s[rand(0, strlen($s)-1)];
                        $password = crypt($password, $salt);

                    }else{
                        mysql_connection_insert(DATABASE);
                        $query = mysql_query("SELECT ugr_LongName, ugr_FirstName, ugr_LastName, ugr_eMail, ugr_Name, ugr_Password, " .
                            "ugr_Department, ugr_Organisation, ugr_City, ugr_State, ugr_Postcode, ugr_Interests FROM sysUGrps WHERE ugr_ID=".                                    get_user_id());
                        $details = mysql_fetch_row($query);
                        $longName = mysql_real_escape_string($details[0]);
                        $firstName = mysql_real_escape_string($details[1]);
                        $lastName = mysql_real_escape_string($details[2]);
                        $eMail = mysql_real_escape_string($details[3]);
                        $name = mysql_real_escape_string($details[4]);
                        $password = mysql_real_escape_string($details[5]);
                        $department = mysql_real_escape_string($details[6]);
                        $organisation = mysql_real_escape_string($details[7]);
                        $city = mysql_real_escape_string($details[8]);
                        $state = mysql_real_escape_string($details[9]);
                        $postcode = mysql_real_escape_string($details[10]);
                        $interests = mysql_real_escape_string($details[11]);
                    }

                    //	 todo: code location of upload directory into sysIdentification, remove from edit form (should not be changed)
                    //	 todo: might wish to control ownership rather than leaving it to the O/S, although this works well at present

                    $warnings = 0;

                    // Create a default upload directory for uploaded files eg multimedia, images etc.
                    $uploadPath = HEURIST_UPLOAD_ROOT.$newDBName;
                    $cmdline = "mkdir -p -m a=rwx ".$uploadPath;
                    $output2 = exec($cmdline . ' 2>&1', $output, $res2);
                    if ($res2 != 0 ) { // TODO: need to properly trap the error and distiguish different versions.
                        // Old uplaod directories hanging around could cause problems if upload file IDs are duplicated,
                        // so should probably NOT allow their re-use
                        echo ("<h3>Warning:</h3> Unable to create $uploadPath directory for database $newDBName<br>&nbsp;<br>");
                        echo ("This may be because the directory already exists or the parent folder is not writable<br>");
                        echo ("Please check/create directory by hand. Consult Heurist helpdesk if needed<br>");
                        echo($output2);
                        $warnings = 1;
                    } else {
                        add_index_html($uploadpath); // index file to block directory browsing
                    }

                    // copy icon and thumbnail directories from default set in the program code (sync. with H3CoreDefinitions)
                    $cmdline = "cp -R ../rectype-icons $uploadPath"; // creates directories and copies icons and thumbnails
                    $output2 = exec($cmdline . ' 2>&1', $output, $res2);
                    if ($res2 != 0 ) {
                        echo ("<h3>Warning:</h3> Unable to create/copy record type icons folder rectype-icons to $uploadPath<br>");
                        echo ("If upload directory was created OK, this is probably due to incorrect file permissions on new folders<br>");
                        echo($output2);
                        $warnings = 1;
                    } else {
                        add_index_html($uploadpath."rectype-icons"); // index file to block directory browsing
                        add_index_html($uploadpath."rectype_icons/thumb");
                    }

                    // copy smarty template directory from default set in the program code
                    $cmdline = "cp -R ../smarty-templates $uploadPath";
                    $output2 = exec($cmdline . ' 2>&1', $output, $res2);
                    if ($res2 != 0 ) {
                        echo ("<h3>Warning:</h3> Unable to create/copy smarty-templates folder to $uploadPath<br>");
                        echo($output2);
                        $warnings = 1;
                    } else {
                        add_index_html($uploadpath."smarty-templates"); // index file to block directory browsing
                    }

                    // copy xsl template directories from default set in the program code
                    $cmdline = "cp -R ../xsl-templates $uploadPath";
                    $output2 = exec($cmdline . ' 2>&1', $output, $res2);
                    if ($res2 != 0 ) {
                        echo ("<h3>Warning:</h3> Unable to create/copy xsl-templates folder to $uploadPath<br>");
                        echo($output2);
                        $warnings = 1;
                    } else {
                        add_index_html($uploadpath."xsl-templates"); // index file to block directory browsing
                    }

                    // Create all the other standard folders required for the database
                    // index.html files are added by createFolder to block index browsing
                    $warnings =+ createFolder("settings","used to store import mappings and the like");
                    $warnings =+ createFolder("scratch","used to store temporary files");
                    $warnings =+ createFolder("hml-output","used to write published records as hml files");
                    $warnings =+ createFolder("html-output","used to write published records as generic html files");
                    $warnings =+ createFolder("generated-reports","used to write generated reports");
                    $warnings =+ createFolder("backup","used to write files for user data dump");
                    $warnings =+ createFolder("term-images","used for images illustrating terms");

                    if ($warnings > 0) {
                        echo "<h2>Please take note of warnings above</h2>";
                        echo "You must create the folders indicated or uploads, icons, templates, publishing, term images etc. will not work<br>";
                        echo "If upload folder is created but icons and template folders are not, look at file permissions on new folder creation";
                    }

                    // Prepare to write to the newly created database
                    mysql_connection_insert($newname);

                    // Make the current user the owner and admin of the new database
                    mysql_query('UPDATE sysUGrps SET ugr_LongName="'.$longName.'", ugr_FirstName="'.$firstName.'",
                        ugr_LastName="'.$lastName.'", ugr_eMail="'.$eMail.'", ugr_Name="'.$name.'",
                        ugr_Password="'.$password.'", ugr_Department="'.$department.'", ugr_Organisation="'.$organisation.'",
                        ugr_City="'.$city.'", ugr_State="'.$state.'", ugr_Postcode="'.$postcode.'",
                        ugr_interests="'.$interests.'" WHERE ugr_ID=2');
                    // TODO: error check, although this is unlikely to fail


                    echo "<hr>";
                    echo "<h2>New database '$newDBName' created successfully</h2>";

                    echo "<p><strong>Admin username:</strong> ".$name."<br />";
                    echo "<strong>Admin password:</strong> &#60;<i>same as account currently logged in to</i>&#62;</p>";

                    echo "<p>Click here to log in to your new database: <a href=\"".
                        HEURIST_BASE_URL."?db=".$newDBName."\" title=\"\" target=\"_new\">".HEURIST_BASE_URL."?db=".$newDBName.
                        "</a> <i>(we suggest bookmarking this link)</i></p>";
                    echo "<p>Use Database > Structure > Record types/fields in the menu at top right of the main database page to".
                         "set up record types, fields, terms and other settings for your new database</p>";

                    // TODO: automatically redirect to the new database in a new window
                    // this is a point at which people tend to get lost

                    return false;
                } // isset

            } //makedatabase

            //
            //
            //
            function createFolder($name, $msg){
                global 	$newDBName;
                $uploadPath = HEURIST_UPLOAD_ROOT.$newDBName;
                $folder = $uploadPath."/".$name;

                if(file_exists($folder) && !is_dir($folder)){
                    if(!unlink($folder)){
                        echo ("<h3>Warning:</h3> Unable to remove folder $folder. We need to create a folder with this name ($msg)<br>");
                        return 1;
                    }
                }

                if(!file_exists($folder)){
                    if (!mkdir($folder, 0777, true)) {
                        echo ("<h3>Warning:</h3> Unable to create folder $folder ($msg)<br>");
                        return 1;
                    }
                } else if (!is_writable($folder)) {
                    echo ("<h3>Warning:</h3> Folder $folder already exists and it is not writeable. Check permissions! ($msg)<br>");
                    return 1;
                }

                add_index_html($folder); // index file to block directory browsing

                return 0;
            }

        ?>
    </body>
</html>


