<?php

/*
	This is the currently active handler
	
*/

include "dbc.php";
include "sendalert.php";




function checkTime($row)
{
	
	  				
	
	
	//$row is database record from the employees' schedule

//now are you within the time?

				$hour=date('H');
				$minute=date('i');
				
				$startTime=$row['inTime'];
				$endTime=$row['outTime'];
				$startTimeArr=explode(":",$startTime);
				$endTimeArr=explode(":",$endTime);
				
				
				//we need to get offset hour
				
				$query="select departments.companies_id from schedules left join departments on schedules.dept_id=departments.id where schedules.id=".$row['id'];
				$goget=mysql_query($query);
				
				$rowget=mysql_fetch_array($goget);
				$companyID=$rowget['companies_id'];
			
				
				 //now we have company id, so let's check offset
				 $query="select timeZoneAdjustment from companies where id=$companyID";
				 $goget=mysql_query($query);
				 $rowget=mysql_fetch_array($goget);
				 
				 $HRAdjustment=$rowget['timeZoneAdjustment'];
				 $hrOffset = $HRAdjustment;
				
					if($HRAdjustment == "")
						$HRAdjustment="0";
				
				
				 
				
				$pass=0;
				
				
				
			
				
				
				
				//$hour==current hour
				if(($hour+$HRAdjustment) >= $startTimeArr[0] && ($hour+$HRAdjustment) <= $endTimeArr[0] )	//within hour!
				{
					
					
					if($hour+$HRAdjustment == $startTimeArr[0])		//if clock in is the same as start hour, make sure, clock in minute is same or higher!
					{
					
				
						if($minute >= $startTimeArr[1])
							$pass=1;
						else
							$pass=0;
							
						
					
					}					
					else if($hour+$HRAdjustment == $endTimeArr[0] )	//not same hour, it's the same as the clock out hour.
					{
						
					
						if($minute <= $endTimeArr[1])
							$pass=1;
						else
							$pass=0;
						
					
					}
					else
					{
						
						$pass=1;
						
					
						
					}
					
				}
				
				
				

return $pass;
}


function recognize($company,$picURL, $pin)
{
 			
     	//get album key (stored in companies table
		$query="select * from companies where id=$company";
		$go=mysql_query($query);
		$row=mysql_fetch_array($go);
		$hrOffset=$row['timeZoneAdjustment'];
		
		
   			//$response=file_get_contents("http://www.ifacepunch.co/3.0/detect.php?company=$company&pic=http://www.ifacepunch.co/ios/uploads/$picURL");
			//echo "http://www.ifacepunch.co/3.0/recognize.php?album=$albumname&key=$key&pic=http://www.ifacepunch.co/ios/uploads/$picURL###";
			//$respArr=explode(",",$response);
			
			//temporary only identify via pin
			$query="select * from employees where pin=$pin";
			$gopin=mysql_query($query);
			$rowpin=mysql_fetch_array($gopin);
			
			$respArr[0]=$rowpin['id'];
			
			
			$uid=$respArr[0];
			$confidence=$respArr[1];
			
			
			
			$query="select * from employees where id=$uid";
			$gogo=mysql_query($query);
			$rowrow=mysql_fetch_array($gogo);
			
					
			
			
			if($rowrow['pin'] == $pin && $confidence >= .85)
			{
				//now punch in / out
				$type="";
				//first get employee id
				$eID[0]=$uid;
				
				
				
				$query="select * from employees where id=".$eID[0];
				$go=mysql_query($query);
				$row=mysql_fetch_array($go);
				
				
				if($row['middleName'] == "")
					$name=$row['firstName']." ".$row['lastName'];
				else
					$name=$row['firstName']." ".$row['middleName']." ".$row['lastName'];
					
				
				
				//check if they can clock in or out on this day:
				$qry="select * from employee_schedules where employees_id=".$eID[0];
				$goa=mysql_query($qry);
				$pass=0;
				
				
				for($a=0; $a<mysql_num_rows($goa); $a++)
				{
					
					
					
					$rowa=mysql_fetch_array($goa);
					
					$qry2="select * from schedules where id=".$rowa['schedules_id'];
					$gob=mysql_query($qry2);
					$rowb=mysql_fetch_array($gob);
					
					//used in case employee clocked OUT after this time, it stores it as the clock out time
					//and asks manager permission for real clock-out time
					$outTime = $rowb['outTime'];
					
				
						$today=date('N');
						$pass=0;
					
					
					
						switch($today)
						{
							case 1:
									if($rowb['M']==1)
										$pass=checkTime($rowb);
									break;
							case 2:
									if($rowb['T']==1)
										$pass=checkTime($rowb);
									break;
							case 3:
									if($rowb['W']==1)
										$pass=checkTime($rowb);
									break;
							case 4:
									if($rowb['TH']==1)
										$pass=checkTime($rowb);
									break;
							case 5:
									if($rowb['F']==1)
										$pass=checkTime($rowb);
									break;
							case 6:
									if($rowb['S']==1)
										$pass=checkTime($rowb);
									break;
							case 7:
									if($rowb['SUN']==1)
										$pass=checkTime($rowb);
									break;
						}
				
				
				//passed the test, continue normally
				if($pass==1)
				{
					
					$query="select * from punchcard where employees_id=".$eID[0]." order by str_to_date(datee, '%m/%d/%Y') desc, str_to_date(timee, '%k:%i') desc";
					$go=mysql_query($query);
					
					if(mysql_num_rows($go) > 0)
					{
						$row=mysql_fetch_array($go);
						if($row['typee']=="IN")
						{
							//check if they clocked out yesterday...or on previous day.
							include "includes/previouspunchoutcheck.php";
							if(previousPunchOutCheck($row,$type)==0)
							{
								$qry="update punchcard set problemWithRecord=1 where id=".$row['id'];
								$goUp=mysql_query($qry);
								
								$type="IN";
							}
							else						
								$type="OUT";
						}
						else
							$type="IN";
					
					}
					else
						$type="IN";
						
						
					$gps=$_POST['gps'];
					$gps=mysql_real_escape_string($gps);
					
					if($gps == "")
						$gps="0";
								
									//now post to database
									$query="insert into punchcard(employees_id, datee, timee, typee, gps, picture) values(".$eID[0].", '".date('m/d/Y')."', '".date('H:i')."', '$type', '$gps', '$picURL')";
									$go=mysql_query($query);
									
									$query="select * from employees where id=".$eID[0];
									$go=mysql_query($query);
									$row=mysql_fetch_array($go);
									
									
										
									echo "SUCCESS,$name,$type, confidence; $confidence";
									exit;
								
				  } //if pass==1
				  else
				  {		
				  
				  
				  // failed, so if it's a clock-out allow it if employee is allowed to, flag it, and email manager
					  
					//check if employee has authorization to punch out after  
					  
					$query	=	"select needsAfterSchedApproval from employees where id=".$eID[0];
					$goE	=	mysql_query($query);
					$rowE	=	mysql_fetch_array($goE);  
					  
					 if($rowE['needsAfterSchedApproval'] == 1)
					 { 
					  
					  
					  
					$query="select * from punchcard where employees_id=".$eID[0]." order by str_to_date(datee, '%m/%d/%Y') desc, str_to_date(timee, '%k:%i') desc";
					$go=mysql_query($query);
					
					if(mysql_num_rows($go) > 0)
					{
						$row=mysql_fetch_array($go);
						if($row['typee']=="IN")
						{
					  		//check if they clocked out yesterday...or on previous day.
							include "includes/previouspunchoutcheck.php";
							if(previousPunchOutCheck($row,$type)==0)
							{
								$qry="update punchcard set problemWithRecord=1 where id=".$row['id'];
								$goUp=mysql_query($qry);
								
								$type="IN";
							}
							else						
								$type="OUT";
								
								
								
							$gps=$_POST['gps'];
							$gps=mysql_real_escape_string($gps);
					
          			$outTimeUnedited = $outTime;
					$outTimeArr = explode(":", $outTime);
					$outTimeHr = $outTimeArr[0];
					$outTimeHr = $outTimeHr + ($hrOffset * -1); 
					$outTime = $outTimeHr.":".$outTimeArr[1];
					
					
					


					$timee = date('H:i');
					
					//now post to database with flag needing approval
					$query="insert into punchcard(employees_id, datee, timee, typee, gps, picture, timee_notapproved) values(".$eID[0].", '".date('m/d/Y')."', '$outTime', '$type', '$gps', '$picURL', '".date('H:i')."')";
					$go=mysql_query($query);
					
					echo "SUCCESS,$name,$type, confidence; $confidence";
					
					$query="select last_insert_id();";
					$go=mysql_query($query);
					$row=mysql_fetch_array($go);
					$lastID=$row[0];
					
					$query="select * from employees where id=".$eID[0];
					$go=mysql_query($query);
					$row=mysql_fetch_array($go);
					
					
					
					//dispatch approval email now
					//get all approval email addresses
					
					$query="select approvalemails from companies where id = ". $row['companies_id'];
					$goEmails = mysql_query($query);
					$rowEmails = mysql_fetch_array($goEmails);
					$emailArr = explode(",", $rowEmails['approvalemails']);
					
					
					//calculate real clock out according to local time:
							$timeArr=explode(":",$timee);
							$hr=$timeArr[0]+$hrOffset;
							$punchout=$hr.":".$timeArr[1];
					
					
					for($z = 0; $z<count($emailArr); $z++)
					{
					
						$to		  = $emailArr[$z];
						$subject = $row['firstName']." ".$row['lastName']." needs approval to punch out";
						$message = "<img src='http://www.ifacepunch.co/ios/uploads/$picURL'><BR>".$row['firstName']." ".$row['lastName']."<BR><strong>Clocked out at: $punchout<BR>Schedule ended at: $outTimeUnedited</strong><BR><BR><a href='http://www.ifacepunch.co/handleapproval.php?id=$lastID&action=approve'><img src='http://www.ifacepunch.co/approve.png'></a>&nbsp;<a href='http://www.ifacepunch.co/handleapproval.php?id=$lastID&action=deny'><img src='http://www.ifacepunch.co/deny.png'></a> ";
						
						sendalert("masterkey",$to,$subject,$message);
					}
						

					exit;
					
						} //needsApprov
								
						}
								
					  
					}
					  
					  
					  
					  
				  }
				  
				
				
			} //for every schedule
				
				
				
				
			if(mysql_num_rows($goa) <= 0 || $pass==0)
			{
				
				echo "FAIL,Schedule,$name";
			
				
			}
				
				
				
		}
		else //did not detect
		{
					
				$url="$picURL";
				$today=date('m/d/Y');
				
				$query="insert into alerts(companies_id, datee, alert, details) values($company, '$today', 'Failed to detect user','viewfailed.php?pic=$picURL&date=$today')";
				$go=mysql_query($query);
				
				
				echo "FAIL,$url, ID returned: $uid, user pin: ". $rowrow['pin']. ", pin entered: $pin, ";
				
			
		}
			
}

//function
//###########################################################################
//						main
//###########################################################################


$date=date('Y-m-d');
$company=$_POST['company'];
$pin = $_POST['pin'];

if($company=="")
	$company=$_GET['company'];


$filename="$company"."-".$date."-".date('His');
$target_path = "c:/inetpub/wwwroot/ifacepunch/ios/uploads/";
mkdir("$target_path/$date", 0777);

$target_path = $target_path.$date.'/'.$filename.".jpg"; 

if(move_uploaded_file($_FILES['file']['tmp_name'], $target_path)) 
{


	


	$picURL=$date."/".$filename.".jpg";

  
	recognize($company,$picURL, $pin);
	
	


} 
else
{
    echo "There was an error uploading the file, please try again!";
}
 

?>