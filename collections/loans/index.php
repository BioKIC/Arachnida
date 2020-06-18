<?php
include_once('../../config/symbini.php');
include_once($SERVER_ROOT.'/classes/OccurrenceLoans.php');
header("Content-Type: text/html; charset=".$CHARSET);
if(!$SYMB_UID) header('Location: '.$CLIENT_ROOT.'/profile/index.php?refurl=../collections/loans/index.php?'.htmlspecialchars($_SERVER['QUERY_STRING'], ENT_QUOTES));

$collid = $_REQUEST['collid'];
$searchTerm = array_key_exists('searchterm',$_POST)?$_POST['searchterm']:'';
$displayAll = array_key_exists('displayall',$_POST)?$_POST['displayall']:0;
$formSubmit = array_key_exists('formsubmit',$_POST)?$_POST['formsubmit']:'';
$tabIndex = array_key_exists('tabindex',$_REQUEST)?$_REQUEST['tabindex']:0;

$isEditor = 0;
if($SYMB_UID && $collid){
	if($IS_ADMIN || (array_key_exists("CollAdmin",$USER_RIGHTS) && in_array($collid,$USER_RIGHTS["CollAdmin"]))
		|| (array_key_exists("CollEditor",$USER_RIGHTS) && in_array($collid,$USER_RIGHTS["CollEditor"]))){
		$isEditor = 1;
	}
}

$loanManager = new OccurrenceLoans();
if($collid) $loanManager->setCollId($collid);

$statusStr = '';
if($isEditor){
	if($formSubmit){
		if($formSubmit == 'createLoanIn'){
			$loanId = $loanManager->createNewLoanIn($_POST);
			if(!$loanId) $statusStr = $loanManager->getErrorMessage();
		}
		elseif($formSubmit == 'Delete Loan'){
			$status = $loanManager->deleteLoan($loanId);
			if($status) $loanId = 0;
		}
		elseif($formSubmit == 'Delete Exchange'){
			$status = $loanManager->deleteExchange($exchangeId);
		}
		elseif($formSubmit == 'Save Incoming'){
			$statusStr = $loanManager->editLoanIn($_POST);
		}
	}
}
?>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php echo $CHARSET;?>">
	<title><?php echo $DEFAULT_TITLE; ?> Loan Management</title>
	<?php
	$activateJQuery = true;
	if(file_exists($SERVER_ROOT.'/includes/head.php')){
		include_once($SERVER_ROOT.'/includes/head.php');
	}
	else{
		echo '<link href="'.$CLIENT_ROOT.'/css/jquery-ui.css" type="text/css" rel="stylesheet" />';
		echo '<link href="'.$CLIENT_ROOT.'/css/base.css?ver=1" type="text/css" rel="stylesheet" />';
		echo '<link href="'.$CLIENT_ROOT.'/css/main.css?ver=1" type="text/css" rel="stylesheet" />';
	}
	?>
	<script type="text/javascript" src="../../js/jquery.js"></script>
	<script type="text/javascript" src="../../js/jquery-ui.js"></script>
	<script type="text/javascript">
		$(document).ready(function() {
			if(!navigator.cookieEnabled){
				alert("Your browser cookies are disabled. To be able to login and access your profile, they must be enabled for this domain.");
			}
			$('#tabs').tabs({ active: <?php echo $tabIndex; ?> });
		});

		function verifyLoanInAddForm(f){
			if(f.iidowner.options[f.iidowner.selectedIndex].value == 0){
				alert("Select an institution");
				return false;
			}
			if(f.loanidentifierborr.value == ""){
				alert("Enter a loan identifier");
				return false;
			}
			$.ajax({
				method: "POST",
				data: { ident: f.loanidentifierborr.value, collid: f.collid.value, type: "in" },
				dataType: "text",
				url: "rpc/identifierCheck.php"
			})
			.done(function(retCode) {
				if(retCode == 1) alert("There is already a transaction with that identifier, please enter a different one.");
				else f.submit();
			});
			return false;
		}

		function verfifyLoanOutAddForm(f){
			if(f.reqinstitution.options[f.reqinstitution.selectedIndex].value == 0){
				alert("Select an institution");
				return false;
			}
			if(f.loanidentifierown.value == ""){
				alert("Enter a loan identifier");
				return false;
			}
			$.ajax({
				method: "POST",
				data: { ident: f.loanidentifierown.value, collid: f.collid.value, type: "out" },
				dataType: "text",
				url: "rpc/identifierCheck.php"
			})
			.done(function(retCode) {
				if(retCode == 1) alert("There is already a transaction with that identifier, please enter a different one.");
				else f.submit();
			});
			return false;
		}

		function displayNewLoanOut(){
			if(document.getElementById("loanoutToggle")){
				toggle('newloanoutdiv');
			}
			var f = document.newloanoutform;
			if(f.loanidentifierown.value == ""){
				generateNewId(f.collid.value,f.loanidentifierown,"out");
			}
		}

		function displayNewLoanIn(){
			if(document.getElementById("loaninToggle")){
				toggle('newloanindiv');
			}
			var f = document.newloaninform;
			if(f.loanidentifierborr.value == ""){
				generateNewId(f.collid.value,f.loanidentifierborr,"in");
			}
		}

		function generateNewId(collId,targetObj,idType){
			$.ajax({
				method: "POST",
				data: { idtype: idType, collid: collId },
				dataType: "text",
				url: "rpc/generateNextID.php"
			})
			.done(function(retID) {
				targetObj.value = retID;
			})
			.fail(function() {
				alert("Generation of new ID failed");
			});
		}
	</script>
	<style>
		fieldset{ padding:10px; }
		fieldset legend{ font-weight:bold }
	</style>
</head>
<body>
	<?php
	$displayLeftMenu = false;
	include($SERVER_ROOT.'/includes/header.php');
	?>
	<div class='navpath'>
		<a href='../../index.php'>Home</a> &gt;&gt;
		<a href="../misc/collprofiles.php?collid=<?php echo $collid; ?>&emode=1">Collection Management Menu</a> &gt;&gt;
		<a href="index.php?collid=<?php echo $collid; ?>"><b>Loan Index</b></a>
	</div>
	<!-- This is inner text! -->
	<div id="innertext">
		<?php
		if($isEditor && $collid){
			if($statusStr){
				$colorStr = 'red';
				if(stripos($statusStr,'SUCCESS') !== false) $colorStr = 'green';
				?>
				<hr/>
				<div style="margin:15px;color:<?php echo $colorStr; ?>;">
					<?php echo $statusStr; ?>
				</div>
				<hr/>
				<?php
			}
			?>
			<div id="tabs" style="margin:0px;">
			    <ul>
					<li><a href="#loanoutdiv"><span>Outgoing Loans</span></a></li>
					<li><a href="#loanindiv"><span>Incoming Loans</span></a></li>
					<li><a href="exchangetab.php?collid=<?php echo $collid; ?>"><span>Gifts/Exchanges</span></a></li>
				</ul>
				<div id="loanoutdiv" style="">
					<div style="float:right;">
						<form name='optionform' action='index.php' method='post'>
							<fieldset>
								<legend>Options</legend>
								<div>
									<b>Search: </b>
									<input type="text" autocomplete="off" name="searchterm" value="<?php echo $searchTerm;?>" size="20" />
								</div>
								<div>
									<input type="radio" name="displayall" value="0"<?php echo ($displayAll==0?'checked':'');?> /> Display outstanding loans only
								</div>
								<div>
									<input type="radio" name="displayall" value="1"<?php echo ($displayAll?'checked':'');?> /> Display all loans
								</div>
								<div style="float:right;">
									<input type="hidden" name="collid" value="<?php echo $collid; ?>" />
									<input type="submit" name="formsubmit" value="Refresh List" />
								</div>
							</fieldset>
						</form>
					</div>
					<?php
					$loanOutList = $loanManager->getLoanOutList($searchTerm,$displayAll);
					if($loanOutList){
						?>
						<div id="loanoutToggle" style="float:right;margin:10px;">
							<a href="#" onclick="displayNewLoanOut();">
								<img src="../../images/add.png" alt="Create New Loan" />
							</a>
						</div>
						<?php
					}
					?>
					<div id="newloanoutdiv" style="display:<?php echo ($loanOutList?'none':'block'); ?>;">
						<form name="newloanoutform" action="outgoing.php" method="post" onsubmit="return verfifyLoanOutAddForm(this);">
							<fieldset>
								<legend>New Outgoing Loan</legend>
								<div style="padding-top:4px;float:left;">
									<span>
										Entered By:
									</span><br />
									<span>
										<input type="text" autocomplete="off" name="createdbyown" tabindex="96" maxlength="32" style="width:100px;" value="<?php echo $PARAMS_ARR['un']; ?>" />
									</span>
								</div>
								<div style="padding-top:15px;float:right;">
									<span>
										<b>Loan Identifier: </b><input type="text" autocomplete="off" name="loanidentifierown" maxlength="255" style="width:120px;border:2px solid black;text-align:center;font-weight:bold;color:black;" value="" />
									</span>
								</div>
								<div style="clear:both;padding-top:6px;float:left;">
									<span>
										Send to Institution:
									</span><br />
									<span>
										<select name="reqinstitution" style="width:400px;">
											<option value="">Select Institution</option>
											<option value="">------------------------------------------</option>
											<?php
											$instArr = $loanManager->getInstitutionArr();
											foreach($instArr as $k => $v){
												echo '<option value="'.$k.'">'.$v.'</option>';
											}
											?>
										</select>
									</span>
									<span>
										<a href="../admin/institutioneditor.php?emode=1" target="_blank" title="Add a New Institution">
											<img src="../../images/add.png" style="width:15px;" />
										</a>
									</span>
								</div>
								<div style="clear:both;padding-top:8px;float:right;">
									<input name="collid" type="hidden" value="<?php echo $collid; ?>" />
									<input name="formsubmit" type="hidden" value="createLoanOut" />
									<button name="submitButton" type="submit">Create Loan</button>
								</div>
							</fieldset>
						</form>
					</div>
					<?php
					if(!$loanOutList){
						echo '<script type="text/javascript">displayNewLoanOut();</script>';
					}
					?>
					<div>
						<?php
						if($loanOutList){
							echo '<h3>Outgoing Loan Records</h3>';
							echo '<ul>';
							foreach($loanOutList as $k => $loanArr){
								echo '<li>';
								echo '<a href="outgoing.php?collid='.$collid.'&loanid='.$k.'">';
								echo $loanArr['loanidentifierown'];
								echo '</a>: '.$loanArr['institutioncode'].' ('.$loanArr['forwhom'].')';
								echo ' - '.($loanArr['dateclosed']?'Closed: '.$loanArr['dateclosed']:'<b>OPEN</b>');
								echo '</li>';
							}
							echo '</ul>';
						}
						else{
							echo '<div style="font-weight:bold;font-size:120%;margin-top:10px;">There are no loans out registered for this collection</div>';
						}
						?>
					</div>
					<div style="clear:both;">&nbsp;</div>
				</div>
				<div id="loanindiv" style="">
					<div style="float:right;">
						<form name='optionform' action='index.php' method='post'>
							<fieldset>
								<legend>Options</legend>
								<div>
									<b>Search: </b><input type="text" autocomplete="off" name="searchterm" value="<?php echo $searchTerm;?>" size="20" />
								</div>
								<div>
									<input type="radio" name="displayall" value="0"<?php echo ($displayAll==0?'checked':'');?> /> Display outstanding loans only
								</div>
								<div>
									<input type="radio" name="displayall" value="1"<?php echo ($displayAll?'checked':'');?> /> Display all loans
								</div>
								<div style="float:right;">
									<input type="hidden" name="collid" value="<?php echo $collid; ?>" />
									<input type="submit" name="formsubmit" value="Refresh List" />
								</div>
							</fieldset>
						</form>
					</div>
					<?php
					$loansOnWay = $loanManager->getLoanOnWayList();
					$loanInList = $loanManager->getLoanInList($searchTerm,$displayAll);
					?>
					<div id="loaninToggle" style="float:right;margin:10px;">
						<a href="#" onclick="displayNewLoanIn();">
							<img src="../../images/add.png" alt="Create New Loan" />
						</a>
					</div>
					<div id="newloanindiv" style="display:<?php echo (($loansOnWay || $loanInList)?'none':'block'); ?>;">
						<form name="newloaninform" action="incoming.php" method="post" onsubmit="return verifyLoanInAddForm(this);">
							<fieldset>
								<legend>New Incoming Loan</legend>
								<div style="padding-top:4px;float:left;">
									<span>
										Entered By:
									</span><br />
									<span>
										<input type="text" autocomplete="off" name="createdbyborr" tabindex="96" maxlength="32" style="width:100px;" value="<?php echo $PARAMS_ARR['un']; ?>" />
									</span>
								</div>
								<div style="padding-top:15px;float:right;">
									<span>
										<b>Loan Identifier: </b>
										<input type="text" autocomplete="off" id="loanidentifierborr" name="loanidentifierborr" maxlength="255" style="width:120px;border:2px solid black;text-align:center;font-weight:bold;color:black;" value="" />
									</span>
								</div>
								<div style="clear:both;padding-top:6px;float:left;">
									<span>
										Sent From:
									</span><br />
									<span>
										<select name="iidowner" style="width:400px;">
											<option value="0">Select Institution</option>
											<option value="0">------------------------------------------</option>
											<?php
											$instArr = $loanManager->getInstitutionArr();
											foreach($instArr as $k => $v){
												echo '<option value="'.$k.'">'.$v.'</option>';
											}
											?>
										</select>
									</span>
									<span>
										<a href="../admin/institutioneditor.php?emode=1" target="_blank" title="Add a New Institution">
											<img src="../../images/add.png" style="width:15px;" />
										</a>
									</span>
								</div>
								<div style="clear:both;padding-top:8px;float:right;">
									<input name="collid" type="hidden" value="<?php echo $collid; ?>" />
									<input name="formsubmit" type="hidden" value="createLoanIn" />
									<button name="submitbutton" type="submit" value="Create Loan In">Create Loan</button>
								</div>
							</fieldset>
						</form>
					</div>
					<div>
						<h3>Loans Received</h3>
						<ul>
						<?php
						if($loanInList){
							foreach($loanInList as $k => $loanArr){
								echo '<li>';
								echo '<a href="incoming.php?collid='.$collid.'&loanid='.$k.'">';
								echo $loanArr['loanidentifierborr'];
								echo '</a>: '.$loanArr['institutioncode'].' ('.$loanArr['forwhom'].')';
								echo ' - '.($loanArr['dateclosed']?'Closed: '.$loanArr['dateclosed']:'<b>OPEN</b>');
								echo '</li>';
							}
						}
						else{
							echo '<li>There are no loans received</li>';
						}
						?>
						</ul>
					</div>
					<div style="margin-top:50px">
						<?php
						if($loansOnWay){
							echo '<h3>Loans to be Checked-in</h3>';
							echo '<ul>';
							foreach($loansOnWay as $k => $loanArr){
								echo '<li>';
								echo '<a href="incoming.php?collid='.$collid.'&loanid='.$k.'">';
								echo $loanArr['loanidentifierown'];
								echo ' from '.$loanArr['collectionname'].'</a>';
								echo '</li>';
							}
							echo '</ul>';
						}
						?>
					</div>
					<div style="clear:both;">&nbsp;</div>
				</div>
			</div>
			<?php
		}
		else{
			if(!$isEditor) echo '<h2>You are not authorized to manage loans for this collection</h2>';
			else echo '<h2>ERROR: unknown error, please contact system administrator</h2>';
		}
		?>
	</div>
	<?php
	include($SERVER_ROOT.'/includes/footer.php');
	?>
</body>
</html>