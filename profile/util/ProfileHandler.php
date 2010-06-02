<?php
/*
 * Created on 26 Feb 2009
 * By E.E. Gilbert
 */
//error_reporting(E_ALL);
//ini_set("display_errors", 1);
include_once("../util/dbconnection.php");
include_once("Person.php");

//include_once('includes_php/common.inc.php');

class ProfileHandler{

    private $rememberMe = false;
	private $uid;
	private $userName;
	private $displayName;
    private $visitId;
    private $userRights = Array();
    private $con;
    
    public function __construct(){
    	$this->userRights = Array();
		$this->con = MySQLiConnectionFactory::getCon("readonly");
    }
    
 	public function __destruct(){
		if(!($this->con === null)) $this->con->close();
	}
	
	private function getConnection($type){
		return MySQLiConnectionFactory::getCon($type);
	}
    
    public function reset(){
		//Delete cookies
        setcookie("SymbiotaBase", "", time() - 3600, $GLOBALS["clientRoot"]);
        setcookie("SymbiotaRights", "", time() - 3600, $GLOBALS["clientRoot"]);
    }
    
    public function setCookies(){
        $cookieStr = "un=".$this->userName;
        $cookieStr .= "&dn=".$this->displayName;
        $cookieStr .= "&uid=".$this->uid;
        $cookieExpire = 0;
        if($this->rememberMe){
        	$cookieExpire = time()+60*60*24*30;
        }
        setcookie("SymbiotaBase", $cookieStr, $cookieExpire, $GLOBALS["clientRoot"]);
        //Set admin cookie
        if($this->userRights){
        	setcookie("SymbiotaRights", implode("&",$this->userRights), $cookieExpire, $GLOBALS["clientRoot"]);
    	}
    }
    
    public function authenticate($userNameStr, $pwdStr){
        $authStatus = false;
        $this->userName = $userNameStr;
		//check login
        $sql = "SELECT u.uid, u.firstname, u.lastname ".
			"FROM users u INNER JOIN userlogin ul ON u.uid = ul.uid ".
            "WHERE (ul.username = '".$userNameStr."') ".
			"AND (ul.password = PASSWORD('".$pwdStr."'))";
		//echo $sql;
        $result = $this->con->query($sql);
        if($row = $result->fetch_object()){
        	$this->uid = $row->uid;
        	$this->displayName = $row->firstname." ".$row->lastname;
            $authStatus = true;
        }
		
        if($authStatus){
			//Get Admin Rights 
	        $sql = "SELECT up.pname FROM userpermissions up WHERE up.uid = ".$this->uid;
	        //echo $sql;
	        $result = $this->con->query($sql);
    	    while($row = $result->fetch_object()){
	    	    $this->userRights[] = $row->pname;
	        }
            
	        $this->setCookies();
            
	        //Upadate last login data
	        $conn = $this->getConnection("write");
	        $sql = "UPDATE userlogin SET lastlogindate = NOW() WHERE username = '".$userNameStr."'";
	        $conn->query($sql); 
	        
        	return "success";
        }
        else{
                //Check and see why authentication failed
        	$sqlStr = "SELECT u.uid, u.firstname, u.lastname, u.email, ul.password ".
            	"FROM userlogin ul INNER JOIN users u ON ul.uid = u.uid ".
				"WHERE (ul.username = '".$userNameStr."')";
            //echo $sqlStr;
	        $result = $this->con->query($sqlStr);
			if($row = $result->fetch_object()){
                    return "badPassword";
	        }
                return "badUserId";
        }
    }
    
    public function getPersonByUid($userId){
        $sqlStr = "SELECT u.uid, u.firstname, u.lastname, u.title, u.institution, u.department, ".
        	"u.address, u.city, u.state, u.zip, u.country, u.phone, u.email, ".
        	"u.url, u.biography, u.ispublic, u.notes, ul.username ".
            "FROM users u LEFT JOIN userlogin ul ON u.uid = ul.uid ".
            "WHERE (u.uid = ".$userId.")";
        return $this->getPersonBySql($sqlStr);
    }
        
    public function getPerson ($userName){
        $sqlStr = "SELECT u.uid, u.firstname, u.lastname, u.title, u.institution, u.department, ".
        	"u.address, u.city, u.state, u.zip, u.country, u.phone, u.email, ".
        	"u.url, u.biography, u.ispublic, u.notes, ul.username ".
            "FROM userlogin ul INNER JOIN users u ON ul.uid = u.uid ".
            "WHERE (ul.username = '".$userName."')";
        return $this->getPersonBySql($sqlStr);
    }
        
    private function getPersonBySql($sqlStr){
		$person;
		//echo $sqlStr;
    	$result = $this->con->query($sqlStr);
        if($row = $result->fetch_object()){
	        $person = new Person();
            $person->setUid($row->uid);
            $person->setFirstName($row->firstname);
            $person->setLastName($row->lastname);
            $person->setTitle($row->title);
            $person->setInstitution($row->institution);
            $person->setDepartment($row->department);
            $person->setAddress($row->address);
            $person->setCity($row->city);
            $person->setState($row->state);
            $person->setZip($row->zip);
            $person->setCountry($row->country);
            $person->setPhone($row->phone);
            $person->setEmail($row->email);
            $person->setUrl($row->url);
            $person->setBiography($row->biography);
            $person->setIsPublic($row->ispublic);
            $person->addLogin($row->username);
            while($row = $result->fetch_object()){
            	$person->addLogin($row->username);
            }
        }
        $result->free();
        //if($person) $this->setPersonProps();
        return $person;
    }

    private function setPersonProps(){
		$this->person->setUserDirectoryPath($userDirectoryRoot.$this->person->getUserName()."/");
        $this->visitId = $this->person->getUserName()."_".microtime();
        $this->logVisit();
    }
    
    private function logVisit(){
        /*try{
            File uDir = new File(person.getUserDirectoryPath());
            if(!uDir.exists()) uDir.mkdir();
            Date d = new Date();
            DateFormat df = DateFormat.getDateInstance(DateFormat.LONG);

            //open the visit file and add a new visit
            File uVisitLog = new File(person.getUserDirectoryPath() + "user.log");
            FileWriter fileWriter = new FileWriter(uVisitLog);
            fileWriter.write($this->visitId + " " + df);
            fileWriter.close();
            
        }catch(Exception e){
            e.printStackTrace();
        }*/
    }

    public function updateProfile($person){
        $success = false;
    	if($person){
        	$editCon = $this->getConnection("write");
    		$fields = "UPDATE users SET ";
            $where = "WHERE uid = ".$person->getUid();
            $values = "firstname = '".$person->getFirstName()."'";
            $values .= ", lastname= '".$person->getLastName()."'";

            $values .= ", title= '".$person->getTitle()."'";
            $values .= ", institution='".$person->getInstitution()."'";
            $values .= ", department= '".$person->getDepartment()."'";
            $values .= ", address= '".$person->getAddress()."'";
            $values .= ", city='".$person->getCity()."'";
            $values .= ", state='".$person->getState()."'";
            $values .= ", zip='".$person->getZip()."'";
            $values .= ", country= '".$person->getCountry()."'";
            $values .= ", phone='".$person->getPhone()."'";
            $values .= ", email='".$person->getEmail()."'";
            $values .= ", url='".$person->getUrl()."'";
            $values .= ", biography='".$person->getBiography()."'";
            $values .= ", ispublic=".$person->getIsPublic()." ";
            $sql = $fields." ".$values." ".$where;
			//echo $sql;
            $success = $editCon->query($sql);
            $editCon->close();
        }
        return $success;
    }

    public function deleteProfile($uid, $reset = 0){
        $success = false;
        if($uid){
        	$editCon = $this->getConnection("write");
        	$sql = "DELETE FROM users WHERE uid = ".$uid;
			$success = $editCon->query($sql);
        	$editCon->close();
        }
        if($reset) $this->reset();
        return $success;
    }
    
    public function changePassword ($id, $newPwd, $oldPwd = "", $isSelf = 0) {
        $success = false;
    	if($newPwd){
        	$editCon = $this->getConnection("write");
        	if($isSelf){
	        	$sqlTest = "SELECT ul.uid FROM userlogin ul WHERE ul.username = '".$id."' AND ul.password = PASSWORD('".$oldPwd."')";
	        	$rsTest = $editCon->query($sqlTest);
	        	if(!$rsTest->num_rows) return false;
        	}
    		$sql = "UPDATE userlogin ul SET ul.password = PASSWORD('".$newPwd."') "; 
    		if($isSelf){
    			$sql .= "WHERE ul.username = '".$id."'";
    		}
    		else{
    			$sql .= "WHERE uid = ".$id;
    		}
			$successCnt = $editCon->query($sql);
        	$editCon->close();
        	if($successCnt > 0) $success = true;
    	}
        return $success;
    }
    
    public function resetPassword($un){
        $newPassword = $this->generateNewPassword();
        $status = false;
        $returnStr = "";
        if($un){
        	$editCon = $this->getConnection("write");
        	$sql = "UPDATE userlogin ul SET ul.password = PASSWORD('".$newPassword."') ". 
                    "WHERE ul.username = '".$un."'";
			$status = $editCon->query($sql);
        	$editCon->close();
        }
		if($status){
			//Get email address
			$emailStr = ""; 
        	$sql = "SELECT u.email FROM users u INNER JOIN userlogin ul ON u.uid = ul.uid ".
        		"WHERE ul.username = '".$un."'";
			$result = $this->con->query($sql);
			if($row = $result->fetch_object()){
				$emailStr = $row->email;
			}
			$result->free();

			//Send email
			$subject = "Your password";
			$bodyStr = "Your ".$defaultTitle." password has been reset to: ".$newPassword." ";
			$bodyStr .= "\r\n\nAfter logging in, you can reset your password clicking on View Profile link and then selecting edit.";
			$bodyStr .= "\r\nIf you have problems with the new password, contact the System Administrator ";
			if(isset($adminEmail)){
				$bodyStr .= "<".$adminEmail.">";
			}
			$headerStr = "MIME-Version: 1.0 \r\n".
				"Content-type: text/html; charset=iso-8859-1 \r\n".
				"To: ".$emailStr." \r\n";
			if(isset($adminEmail)){
				$headerStr .= "From: Admin <".$adminEmail."> \r\n";
			}
			mail($emailStr,$subject,$bodyStr,$headerStr);
			
			$returnStr = "Your new password was just emailed to: ".$emailStr;
		}
		else{
            $returnStr = "Reset Failed! Contact Administrator";
		}
        return $returnStr;
    }
    
    private function generateNewPassword(){
        // generate new random password
        $newPassword = "";
        $alphabet = str_split("0123456789abcdefghijklmnopqrstuvwxyz");
        for($i = 0; $i<5; $i++) {
            $newPassword .= $alphabet[rand(0,count($alphabet)-1)];
        }
        return $newPassword;
    }
    
    public function register($person){
        $returnStr = "";
        $userNew = true;

        //Test to see if user already exists
        if($person->getEmail()){
        	$sql = "SELECT u.uid FROM users u WHERE u.email = '".$person->getEmail()."' AND u.lastname = '".$person->getLastName()."'";
			$result = $this->con->query($sql);
			if($row = $result->fetch_object()){
				$person->setUid($row->uid);
				//$returnStr = "Note: Using profile already in system that matched submitted name and email address. ";
                $userNew = false;
            }
            $result->free();
        }
        
        //If newuser, add to users table
        if($userNew){
			$fields = "INSERT INTO users (";
			$values = "VALUES (";
			$fields .= "firstname ";
            $values .= "'".$person->getFirstName()."'";
			$fields .= ", lastname";
			$values .= ", '".$person->getLastName()."'";
            if($person->getTitle()){
	            $fields .=", title";
                $values .= ", '".$person->getTitle()."'";
            }
            if($person->getInstitution()){
            	$fields .=", institution";
                $values .= ", '".$person->getInstitution()."'";
            }

            if($person->getDepartment()){
            	$fields .=", department";
                $values .= ", '".$person->getDepartment()."'";
            }
            if($person->getAddress()){
				$fields .=", address";
                $values .= ", '".$person->getAddress()."'";
            }
            if($person->getCity()){
				$fields .=", city";
                $values .= ", '".$person->getCity()."'";
            }
            if($person->getState()){
				$fields .=", state";
                $values .= ", '".$person->getState()."'";
            }
            if($person->getZip()){
            	$fields .=", zip";
				$values .= ", '".$person->getZip()."'";
            }
            if($person->getCountry()){
            	$fields .=", country";
                $values .= ", '".$person->getCountry()."'";
            }
            if($person->getPhone()){
            	$fields .=", phone";
                $values .= ", '".$person->getPhone()."'";
            }
            if($person->getEmail()){
	            $fields .= ", email";
                $values .= ", '".$person->getEmail()."'";
            }
            if($person->getUrl()){
            	$fields .=", url";
				$values .= ", '".$person->getUrl()."'";
            }
            if($person->getBiography()){
            	$fields .=", biography";
				$values .= ", '".$person->getBiography()."'";
            }
            if($person->getIsPublic()){
            	$fields .=", ispublic";
				$values .= ", ".$person->getIsPublic();
            }
            
			$sql = $fields.") ".$values.")";
            //echo "SQL: ".sql;
        	$editCon = $this->getConnection("write");
			if($editCon->query($sql)){
				$person->setUid($editCon->insert_id);
            }
            $editCon->close();
        }
        
        //Add userlogin
        $sql = "INSERT INTO userlogin (uid, username, password) ".
			"VALUES (".$person->getUid().", '".$person->getUserName()."', PASSWORD('".$person->getPassword()."'))";
        $editCon = $this->getConnection("write");
        $insertStatus = $editCon->query($sql);
        $editCon->close();
        if($insertStatus > 0){
        	$returnStr = "SUCCESS: new user added successfully. ".$returnStr;
        }
        else{
        	$returnStr = "FAILED: Unable to create user.<div style='margin-left:55px;'>Please contact system administrator for assistance.</div>";
        }
        return $returnStr;
    }
    
    public function lookupLogin($emailAddr){
    	$returnStr = "";
    	$sql = "SELECT u.uid, ul.username ".
			"FROM users u INNER JOIN userlogin ul ON u.uid = ul.uid ".
			"WHERE (u.email = '".$emailAddr."')";
    	$result = $this->con->query($sql);
    	if($row = $result->fetch_object()){
    		$returnStr = $row->username;
    	}
   		return $returnStr;
    }
    
    public function createNewLogin($userId, $newLogin, $newPwd){
    	$statusStr = "<span color='red'>Creation of New Login failed!</span>";
    	$newLogin = trim($newLogin);
    	
    	//Test if login exists
    	$sqlTestLogin = "SELECT ul.uid FROM userlogin ul WHERE ul.username = '".$newLogin."' ";
    	$rs = $this->con->query($sqlTestLogin);
    	$numRows = $rs->num_rows;
    	$rs->close();
    	if($numRows) return "<span color='red'>FAILED! Login $newLogin is already being used by another user. Please try a new login.</span>";
    	
    	//Create new login
    	$sql = "INSERT INTO userlogin (uid, username, password) ".
    		"VALUES ($userId,'".$newLogin."',PASSWORD('".$newPwd."'))";
    	//echo $sql;
        $editCon = $this->getConnection("write");
    	if($editCon->query($sql)) $statusStr = "<span color='green'>Creation of New Login successful!</span>";
    	$editCon->close();
    	return $statusStr;
    }
    
    public function checkLogin($username, $email){
        //Check to see if userlogin already exists 
        $returnStr = "";
       	$sql = "SELECT u.uid, u.email ".
			"FROM users u INNER JOIN userlogin ul ON u.uid = ul.uid ".
			"WHERE (ul.username = '".$username."')";
		$result = $this->con->query($sql);
        if($row = $result->fetch_object()){
            $loginEmail = $row->email;
            if($loginEmail == $email){
                $returnStr = "FAILED: Login already associated with this email address.<br/> ".
                	"Click <a href='index.php?resetpwd=1&username=".$username."'>here</a> to reset password for this username.<br/>".
                	"Or change username below and resubmit form.";
            }
            else{
        		$returnStr = "FAILED: username <b>".$username."</b> login is already being used.<br> ".
        		"Please choose a different username and resubmit form.";
            }
        }
        $result->free();
        return $returnStr;
    }
    
    public function getUserRights(){
        return $this->userRights;
    }
    public function setRememberMe($test){
        $this->rememberMe = $test;
    }

    public function getRememberMe(){
        return $this->rememberMe;
    }
} 
?>